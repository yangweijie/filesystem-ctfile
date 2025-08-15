<?php

declare(strict_types=1);

namespace Yangweijie\FilesystemCtlife;

use Yangweijie\FilesystemCtlife\Exceptions\ApiException;
use Yangweijie\FilesystemCtlife\Exceptions\CTFileException;
use Yangweijie\FilesystemCtlife\Exceptions\AuthenticationException;
use Yangweijie\FilesystemCtlife\Exceptions\NetworkException;
use Yangweijie\FilesystemCtlife\Exceptions\RateLimitException;

/**
 * CTFile API HTTP 客户端
 * 
 * 封装与 CTFile REST API 的 HTTP 通信，包括请求构建、响应解析、
 * 错误处理、认证管理、重试机制等核心功能。
 */
class CTFileClient
{
    /**
     * 配置实例
     */
    private CTFileConfig $config;

    /**
     * 构造函数
     *
     * @param CTFileConfig $config 配置实例
     */
    public function __construct(CTFileConfig $config)
    {
        $this->config = $config;
    }

    /**
     * 发送 HTTP 请求
     *
     * @param string $method HTTP 方法
     * @param string $endpoint API 端点
     * @param array $data 请求数据
     * @param array $headers 额外的请求头
     * @return array 响应数据
     * @throws ApiException 当请求失败时
     */
    public function request(string $method, string $endpoint, array $data = [], array $headers = []): array
    {
        $url = $this->buildUrl($endpoint);
        $attempts = 0;
        $maxAttempts = $this->config->getRetryAttempts() + 1;

        while ($attempts < $maxAttempts) {
            try {
                return $this->executeRequest($method, $url, $data, $headers);
            } catch (ApiException $e) {
                $attempts++;
                
                // 如果是最后一次尝试或者是不可重试的错误，直接抛出异常
                if ($attempts >= $maxAttempts || !$this->isRetryableError($e)) {
                    throw $e;
                }
                
                // 等待一段时间后重试
                usleep(1000000 * $attempts); // 1秒 * 尝试次数
            }
        }

        throw new ApiException('Maximum retry attempts exceeded');
    }

    /**
     * 发送 GET 请求
     *
     * @param string $endpoint API 端点
     * @param array $params 查询参数
     * @return array 响应数据
     */
    public function get(string $endpoint, array $params = []): array
    {
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }
        
        return $this->request('GET', $endpoint);
    }

    /**
     * 发送 POST 请求
     *
     * @param string $endpoint API 端点
     * @param array $data 请求数据
     * @return array 响应数据
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * 上传文件
     *
     * @param string $url 上传 URL
     * @param string $filePath 文件路径或内容
     * @param array $params 上传参数
     * @return array 响应数据
     */
    public function upload(string $url, string $filePath, array $params = []): array
    {
        $ch = curl_init();
        $tempFile = null;

        try {
            // 准备上传数据
            $postData = $params;

            if (is_file($filePath)) {
                $postData['file'] = new \CURLFile($filePath);
            } else {
                // 如果不是文件路径，则作为文件内容处理
                $tempFile = tmpfile();
                fwrite($tempFile, $filePath);
                $tempPath = stream_get_meta_data($tempFile)['uri'];
                $postData['file'] = new \CURLFile($tempPath);
            }

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->config->getTimeout(),
                CURLOPT_CONNECTTIMEOUT => $this->config->getConnectTimeout(),
                CURLOPT_HTTPHEADER => $this->buildHeaders(false), // 上传时不需要 JSON 头部
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            if ($response === false) {
                $this->handleNetworkError($error, $url, 'POST');
            }

            if ($httpCode >= 400) {
                $errorMessage = "HTTP {$httpCode} error for POST {$url}";
                $responseData = [];

                // 尝试解析响应数据以获取更详细的错误信息
                if (!empty($response)) {
                    $decoded = json_decode($response, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $responseData = $decoded;
                        $errorMessage = $decoded['message'] ?? $decoded['error'] ?? $errorMessage;
                    }
                }

                // 使用新的错误映射系统
                $this->mapApiError($httpCode, $errorMessage, $url, 'POST', $responseData);
            }

            return $this->handleResponse($response);
        } finally {
            curl_close($ch);
            if ($tempFile) {
                fclose($tempFile);
            }
        }
    }

    /**
     * 下载文件
     *
     * @param string $url 下载 URL
     * @return resource 文件流资源
     */
    public function download(string $url)
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $this->config->getTimeout(),
            CURLOPT_CONNECTTIMEOUT => $this->config->getConnectTimeout(),
            CURLOPT_HTTPHEADER => $this->buildHeaders(false),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new ApiException("Download failed: {$error}", 0, $url, 'GET');
        }

        if ($httpCode >= 400) {
            throw ApiException::fromHttpResponse($httpCode, $response, $url, 'GET');
        }

        // 创建内存流
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $response);
        rewind($stream);
        
        return $stream;
    }

    /**
     * 构建完整的 URL
     *
     * @param string $endpoint API 端点
     * @return string 完整 URL
     */
    private function buildUrl(string $endpoint): string
    {
        $baseUrl = $this->config->getApiBaseUrl();
        $endpoint = ltrim($endpoint, '/');
        
        return "{$baseUrl}/{$endpoint}";
    }

    /**
     * 构建请求头
     *
     * @param bool $includeJson 是否包含 JSON 头部
     * @return array 请求头数组
     */
    private function buildHeaders(bool $includeJson = true): array
    {
        $headers = [
            'myapp-id: ' . $this->config->getAppId(),
            'session: ' . $this->config->getSession(),
        ];

        if ($includeJson) {
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Accept: application/json';
        }

        return $headers;
    }

    /**
     * 执行 HTTP 请求
     *
     * @param string $method HTTP 方法
     * @param string $url 请求 URL
     * @param array $data 请求数据
     * @param array $headers 额外的请求头
     * @return array 响应数据
     * @throws ApiException 当请求失败时
     */
    private function executeRequest(string $method, string $url, array $data, array $headers): array
    {
        $ch = curl_init();
        
        $requestHeaders = array_merge($this->buildHeaders(), $headers);
        
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config->getTimeout(),
            CURLOPT_CONNECTTIMEOUT => $this->config->getConnectTimeout(),
            CURLOPT_HTTPHEADER => $requestHeaders,
        ];

        switch (strtoupper($method)) {
            case 'POST':
                $curlOptions[CURLOPT_POST] = true;
                if (!empty($data)) {
                    $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;
            case 'PUT':
                $curlOptions[CURLOPT_CUSTOMREQUEST] = 'PUT';
                if (!empty($data)) {
                    $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;
            case 'DELETE':
                $curlOptions[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
            case 'GET':
            default:
                // GET 请求的参数已经在 URL 中处理
                break;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->handleNetworkError($error, $url, $method);
        }

        if ($httpCode >= 400) {
            $errorMessage = "HTTP {$httpCode} error for {$method} {$url}";
            $responseData = [];

            // 尝试解析响应数据以获取更详细的错误信息
            if (!empty($response)) {
                $decoded = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $responseData = $decoded;
                    $errorMessage = $decoded['message'] ?? $decoded['error'] ?? $errorMessage;
                }
            }

            // 使用新的错误映射系统
            $this->mapApiError($httpCode, $errorMessage, $url, $method, $responseData);
        }

        return $this->handleResponse($response);
    }

    /**
     * 处理响应数据
     *
     * @param string $response 原始响应
     * @return array 解析后的响应数据
     * @throws CTFileException 当响应格式无效时
     */
    private function handleResponse(string $response): array
    {
        if (empty($response)) {
            return [];
        }

        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new CTFileException('Invalid JSON response: ' . json_last_error_msg());
        }

        // 检查 CTFile API 的错误响应
        if (is_array($decoded) && isset($decoded['code']) && $decoded['code'] !== 200) {
            $this->handleError($decoded);
        }

        return $decoded ?? [];
    }

    /**
     * 处理错误响应
     *
     * @param array $response 错误响应数据
     * @throws CTFileException 总是抛出异常
     */
    private function handleError(array $response): void
    {
        $code = $response['code'] ?? 0;
        $message = $response['message'] ?? 'Unknown error';
        
        throw CTFileException::create($message, $code, $response);
    }

    /**
     * 判断是否为可重试的错误
     *
     * @param ApiException $exception 异常实例
     * @return bool 是否可重试
     */
    private function isRetryableError(ApiException $exception): bool
    {
        $httpCode = $exception->getHttpStatusCode();

        // 5xx 服务器错误和部分 4xx 错误可以重试
        return $httpCode >= 500 || in_array($httpCode, [408, 429], true);
    }

    /**
     * 映射 API 错误到具体异常
     *
     * @param int $httpCode HTTP 状态码
     * @param string $message 错误消息
     * @param string $requestUrl 请求URL
     * @param string $requestMethod 请求方法
     * @param array $responseData 响应数据
     * @throws CTFileException
     */
    private function mapApiError(int $httpCode, string $message, string $requestUrl, string $requestMethod, array $responseData = []): void
    {
        // 提取 CTFile 特定的错误码和消息
        $errorCode = $responseData['error_code'] ?? 0;
        $errorMessage = $responseData['error_message'] ?? $message;
        $errorDetails = $responseData['error_details'] ?? [];

        switch ($httpCode) {
            case 400:
                throw new CTFileException("Bad request: {$errorMessage}", $httpCode);

            case 401:
                $this->handleAuthenticationError($errorCode, $errorMessage, $errorDetails);
                break;

            case 403:
                $this->handleForbiddenError($errorCode, $errorMessage, $errorDetails);
                break;

            case 404:
                throw new CTFileException("Resource not found: {$errorMessage}", $httpCode);

            case 408:
                throw NetworkException::connectionTimeout($requestUrl, $this->config->getTimeout());

            case 413:
                throw new CTFileException("File too large: {$errorMessage}", $httpCode);

            case 429:
                $this->handleRateLimitError($errorMessage, $responseData);
                break;

            case 500:
                throw new CTFileException("Internal server error: {$errorMessage}", $httpCode);

            case 502:
                throw NetworkException::connectionFailed($requestUrl, 'Bad gateway');

            case 503:
                throw new CTFileException("Service unavailable: {$errorMessage}", $httpCode);

            case 504:
                throw NetworkException::connectionTimeout($requestUrl, $this->config->getTimeout());

            default:
                throw ApiException::createApiException(
                    $errorMessage,
                    $httpCode,
                    $requestUrl,
                    $requestMethod,
                    $errorCode,
                    $errorDetails
                );
        }
    }

    /**
     * 处理认证错误
     *
     * @param int $errorCode CTFile 错误码
     * @param string $errorMessage 错误消息
     * @param array $errorDetails 错误详情
     * @throws AuthenticationException
     */
    private function handleAuthenticationError(int $errorCode, string $errorMessage, array $errorDetails): void
    {
        switch ($errorCode) {
            case 10001:
                throw AuthenticationException::invalidSession($this->config->getSession());
            case 10002:
                throw AuthenticationException::invalidAppId($this->config->getAppId());
            case 10003:
                throw AuthenticationException::sessionExpired();
            default:
                throw new AuthenticationException($errorMessage, 401);
        }
    }

    /**
     * 处理权限错误
     *
     * @param int $errorCode CTFile 错误码
     * @param string $errorMessage 错误消息
     * @param array $errorDetails 错误详情
     * @throws AuthenticationException|CTFileException
     */
    private function handleForbiddenError(int $errorCode, string $errorMessage, array $errorDetails): void
    {
        switch ($errorCode) {
            case 20001:
                throw AuthenticationException::insufficientPermissions('file access');
            case 20002:
                throw AuthenticationException::accountDisabled();
            case 20003:
                throw AuthenticationException::insufficientPermissions('folder access');
            default:
                throw new CTFileException("Access forbidden: {$errorMessage}", 403);
        }
    }

    /**
     * 处理频率限制错误
     *
     * @param string $errorMessage 错误消息
     * @param array $responseData 响应数据
     * @throws RateLimitException
     */
    private function handleRateLimitError(string $errorMessage, array $responseData): void
    {
        $retryAfter = $responseData['retry_after'] ?? 60;
        $limitType = $responseData['limit_type'] ?? 'request';

        switch ($limitType) {
            case 'upload':
                throw RateLimitException::uploadRateLimit($retryAfter);
            case 'download':
                throw RateLimitException::downloadRateLimit($retryAfter);
            case 'storage':
                $usedSpace = $responseData['used_space'] ?? 0;
                $totalSpace = $responseData['total_space'] ?? 0;
                throw RateLimitException::storageQuotaExceeded($usedSpace, $totalSpace);
            case 'concurrent':
                $maxConnections = $responseData['max_connections'] ?? 10;
                throw RateLimitException::concurrentConnectionLimit($maxConnections);
            default:
                throw RateLimitException::requestRateLimit($retryAfter);
        }
    }

    /**
     * 处理网络错误
     *
     * @param string $error cURL 错误信息
     * @param string $url 请求URL
     * @param string $method 请求方法
     * @throws NetworkException
     */
    private function handleNetworkError(string $error, string $url, string $method): void
    {
        $lowerError = strtolower($error);

        if (str_contains($lowerError, 'timeout') || str_contains($lowerError, 'timed out')) {
            throw NetworkException::connectionTimeout($url, $this->config->getTimeout());
        }

        if (str_contains($lowerError, 'connection refused') || str_contains($lowerError, 'connection failed')) {
            throw NetworkException::connectionFailed($url, $error);
        }

        if (str_contains($lowerError, 'could not resolve host') || str_contains($lowerError, 'name resolution')) {
            $hostname = parse_url($url, PHP_URL_HOST) ?? $url;
            throw NetworkException::dnsResolutionFailed($hostname);
        }

        if (str_contains($lowerError, 'ssl') || str_contains($lowerError, 'certificate')) {
            throw NetworkException::sslVerificationFailed($url);
        }

        if (str_contains($lowerError, 'network unreachable') || str_contains($lowerError, 'no route to host')) {
            throw NetworkException::networkUnreachable($url);
        }

        // 通用网络错误
        throw new NetworkException("Network error for {$method} {$url}: {$error}");
    }

    /**
     * 获取文件列表
     *
     * @param string $folderId 文件夹ID（默认为根目录 d0）
     * @param int $page 页码
     * @param int $pageSize 每页大小
     * @param string $orderBy 排序字段
     * @param string $orderDirection 排序方向
     * @return array 文件列表响应
     */
    public function getFileList(
        string $folderId = 'd0',
        int $page = 1,
        int $pageSize = 50,
        string $orderBy = 'name',
        string $orderDirection = 'asc'
    ): array {
        return $this->get('files/list', [
            'folder_id' => $folderId,
            'page' => $page,
            'page_size' => $pageSize,
            'order_by' => $orderBy,
            'order_direction' => $orderDirection,
        ]);
    }

    /**
     * 获取文件信息
     *
     * @param string $fileId 文件ID
     * @return array 文件信息
     */
    public function getFileInfo(string $fileId): array
    {
        return $this->get("files/{$fileId}");
    }

    /**
     * 创建文件夹
     *
     * @param string $name 文件夹名称
     * @param string $parentId 父文件夹ID
     * @return array 创建结果
     */
    public function createFolder(string $name, string $parentId = 'd0'): array
    {
        return $this->post('folders/create', [
            'name' => $name,
            'parent_id' => $parentId,
        ]);
    }

    /**
     * 获取上传URL
     *
     * @param string $folderId 目标文件夹ID
     * @param string $filename 文件名
     * @param int $fileSize 文件大小
     * @param string $checksum 文件校验和
     * @return array 上传URL信息
     */
    public function getUploadUrl(string $folderId, string $filename, int $fileSize, string $checksum): array
    {
        return $this->post('files/upload-url', [
            'folder_id' => $folderId,
            'filename' => $filename,
            'file_size' => $fileSize,
            'checksum' => $checksum,
        ]);
    }

    /**
     * 获取下载URL
     *
     * @param string $fileId 文件ID
     * @return array 下载URL信息
     */
    public function getDownloadUrl(string $fileId): array
    {
        return $this->get("files/{$fileId}/download-url");
    }

    /**
     * 删除文件
     *
     * @param string $fileId 文件ID
     * @return array 删除结果
     */
    public function deleteFile(string $fileId): array
    {
        return $this->request('DELETE', "files/{$fileId}");
    }

    /**
     * 删除文件夹
     *
     * @param string $folderId 文件夹ID
     * @return array 删除结果
     */
    public function deleteFolder(string $folderId): array
    {
        return $this->request('DELETE', "folders/{$folderId}");
    }

    /**
     * 移动文件
     *
     * @param string $fileId 文件ID
     * @param string $targetFolderId 目标文件夹ID
     * @return array 移动结果
     */
    public function moveFile(string $fileId, string $targetFolderId): array
    {
        return $this->post("files/{$fileId}/move", [
            'target_folder_id' => $targetFolderId,
        ]);
    }

    /**
     * 复制文件
     *
     * @param string $fileId 文件ID
     * @param string $targetFolderId 目标文件夹ID
     * @param string|null $newName 新文件名（可选）
     * @return array 复制结果
     */
    public function copyFile(string $fileId, string $targetFolderId, ?string $newName = null): array
    {
        $data = ['target_folder_id' => $targetFolderId];
        if ($newName !== null) {
            $data['new_name'] = $newName;
        }

        return $this->post("files/{$fileId}/copy", $data);
    }

    /**
     * 重命名文件或文件夹
     *
     * @param string $id 文件或文件夹ID
     * @param string $newName 新名称
     * @param string $type 类型：'file' 或 'folder'
     * @return array 重命名结果
     */
    public function rename(string $id, string $newName, string $type = 'file'): array
    {
        $endpoint = $type === 'folder' ? "folders/{$id}/rename" : "files/{$id}/rename";

        return $this->post($endpoint, [
            'new_name' => $newName,
        ]);
    }
}
