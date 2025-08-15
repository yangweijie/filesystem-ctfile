<?php

declare(strict_types=1);

namespace Yangweijie\FilesystemCtlife\Exceptions;

/**
 * API 调用异常类
 * 
 * 用于处理 CTFile API 调用过程中的异常情况
 */
class ApiException extends CTFileException
{
    /**
     * HTTP 状态码
     */
    private int $httpStatusCode;

    /**
     * 请求 URL
     */
    private string $requestUrl;

    /**
     * 请求方法
     */
    private string $requestMethod;

    /**
     * 构造函数
     *
     * @param string $message 错误消息
     * @param int $httpStatusCode HTTP 状态码
     * @param string $requestUrl 请求 URL
     * @param string $requestMethod 请求方法
     * @param int $errorCode CTFile 错误代码
     * @param array $errorDetails 错误详细信息
     */
    public function __construct(
        string $message,
        int $httpStatusCode = 0,
        string $requestUrl = '',
        string $requestMethod = '',
        int $errorCode = 0,
        array $errorDetails = []
    ) {
        parent::__construct($message, $httpStatusCode, $errorCode, $errorDetails);
        $this->httpStatusCode = $httpStatusCode;
        $this->requestUrl = $requestUrl;
        $this->requestMethod = $requestMethod;
    }

    /**
     * 获取 HTTP 状态码
     *
     * @return int
     */
    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /**
     * 获取请求 URL
     *
     * @return string
     */
    public function getRequestUrl(): string
    {
        return $this->requestUrl;
    }

    /**
     * 获取请求方法
     *
     * @return string
     */
    public function getRequestMethod(): string
    {
        return $this->requestMethod;
    }

    /**
     * 创建一个 API 异常
     *
     * @param string $message 错误消息
     * @param int $httpStatusCode HTTP 状态码
     * @param string $requestUrl 请求 URL
     * @param string $requestMethod 请求方法
     * @param int $errorCode CTFile 错误代码
     * @param array $errorDetails 错误详细信息
     * @return static
     */
    public static function createApiException(
        string $message,
        int $httpStatusCode = 0,
        string $requestUrl = '',
        string $requestMethod = '',
        int $errorCode = 0,
        array $errorDetails = []
    ): static {
        return new static($message, $httpStatusCode, $requestUrl, $requestMethod, $errorCode, $errorDetails);
    }

    /**
     * 从 HTTP 响应创建异常
     *
     * @param int $httpStatusCode HTTP 状态码
     * @param string $responseBody 响应体
     * @param string $requestUrl 请求 URL
     * @param string $requestMethod 请求方法
     * @return static
     */
    public static function fromHttpResponse(
        int $httpStatusCode,
        string $responseBody,
        string $requestUrl,
        string $requestMethod
    ): static {
        $message = "HTTP {$httpStatusCode} error for {$requestMethod} {$requestUrl}";
        
        // 尝试解析响应体中的错误信息
        $errorDetails = [];
        $errorCode = 0;
        
        if (!empty($responseBody)) {
            $decoded = json_decode($responseBody, true);
            if (is_array($decoded)) {
                $errorDetails = $decoded;
                $errorCode = $decoded['code'] ?? $httpStatusCode;
                if (isset($decoded['message'])) {
                    $message = $decoded['message'];
                }
            }
        }

        return new static($message, $httpStatusCode, $requestUrl, $requestMethod, $errorCode, $errorDetails);
    }
}
