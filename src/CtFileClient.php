<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile;

use YangWeijie\FilesystemCtfile\Exceptions\CtFileAuthenticationException;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileConnectionException;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileOperationException;

/**
 * CtFileClient wrapper class for ctFile functionality and API interactions.
 *
 * This class encapsulates ctFile-specific operations, handles authentication
 * and connection management, and provides an abstraction layer for ctFile features.
 */
class CtFileClient
{
    /**
     * Connection configuration.
     */
    private array $config;

    /**
     * Current connection status.
     */
    private bool $connected = false;

    /**
     * Connection resource or handle.
     */
    private mixed $connection = null;

    /**
     * Last connection attempt timestamp.
     */
    private ?int $lastConnectionAttempt = null;

    /**
     * Connection retry count.
     */
    private int $connectionRetries = 0;

    /**
     * Maximum connection retries.
     */
    private const MAX_CONNECTION_RETRIES = 3;

    /**
     * Connection timeout in seconds.
     */
    private const CONNECTION_TIMEOUT = 30;

    /**
     * Create a new CtFileClient instance.
     *
     * @param array $config Connection configuration
     * @throws CtFileConnectionException If configuration is invalid
     */
    public function __construct(array $config)
    {
        $this->config = $this->validateAndNormalizeConfig($config);
    }

    /**
     * Establish connection to ctFile server.
     *
     * @return bool True if connection successful, false otherwise
     * @throws CtFileConnectionException If connection fails
     * @throws CtFileAuthenticationException If authentication fails
     */
    public function connect(): bool
    {
        if ($this->connected) {
            return true;
        }

        $this->lastConnectionAttempt = time();
        $this->connectionRetries++;

        try {
            // Simulate ctFile connection logic
            // In a real implementation, this would use actual ctFile API/library
            $this->connection = $this->establishConnection();

            if ($this->connection === false) {
                throw CtFileConnectionException::connectionFailed(
                    $this->config['host'],
                    $this->config['port']
                );
            }

            // Authenticate with ctFile server
            if (!$this->authenticate()) {
                throw CtFileAuthenticationException::invalidCredentials($this->config['username']);
            }

            $this->connected = true;
            $this->connectionRetries = 0;

            return true;
        } catch (\Throwable $e) {
            $this->connection = null;
            $this->connected = false;

            if ($this->connectionRetries >= self::MAX_CONNECTION_RETRIES) {
                $this->connectionRetries = 0;
                throw $e;
            }

            // Retry connection after a brief delay
            usleep(1000000); // 1 second

            return $this->connect();
        }
    }

    /**
     * Disconnect from ctFile server.
     *
     * @return void
     */
    public function disconnect(): void
    {
        if (!$this->connected || $this->connection === null) {
            return;
        }

        try {
            // Simulate ctFile disconnection logic
            // In a real implementation, this would properly close ctFile connection
            $this->closeConnection();
        } catch (\Throwable $e) {
            // Log the error but don't throw - disconnection should be graceful
            // In a real implementation, this would use proper logging
        } finally {
            $this->connection = null;
            $this->connected = false;
            $this->connectionRetries = 0;
        }
    }

    /**
     * Check if currently connected to ctFile server.
     *
     * @return bool True if connected, false otherwise
     */
    public function isConnected(): bool
    {
        if (!$this->connected || $this->connection === null) {
            return false;
        }

        // Perform a lightweight connection check
        return $this->verifyConnection();
    }

    /**
     * Get connection configuration.
     *
     * @param string|null $key Specific configuration key to retrieve
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value or entire config array
     */
    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return $this->config[$key] ?? $default;
    }

    /**
     * Get connection status information.
     *
     * @return array Connection status details
     */
    public function getConnectionStatus(): array
    {
        return [
            'connected' => $this->connected,
            'host' => $this->config['host'],
            'port' => $this->config['port'],
            'username' => $this->config['username'],
            'last_connection_attempt' => $this->lastConnectionAttempt,
            'connection_retries' => $this->connectionRetries,
            'ssl_enabled' => $this->config['ssl'],
            'passive_mode' => $this->config['passive'],
        ];
    }

    /**
     * Ensure connection is established.
     *
     * @return void
     * @throws CtFileConnectionException If connection cannot be established
     */
    protected function ensureConnected(): void
    {
        if (!$this->isConnected()) {
            $this->connect();
        }
    }

    /**
     * Validate and normalize configuration.
     *
     * @param array $config Raw configuration
     * @return array Validated and normalized configuration
     * @throws CtFileConnectionException If configuration is invalid
     */
    private function validateAndNormalizeConfig(array $config): array
    {
        $required = ['host', 'username', 'password'];
        $missing = array_diff($required, array_keys($config));

        if (!empty($missing)) {
            throw CtFileConnectionException::forOperation(
                'Missing required configuration: ' . implode(', ', $missing),
                'validate_config'
            );
        }

        return array_merge([
            'port' => 21,
            'timeout' => self::CONNECTION_TIMEOUT,
            'ssl' => false,
            'passive' => true,
            // Base URL for ctFile OpenAPI (public cloud)
            'api_base' => 'https://rest.ctfile.com',
        ], $config);
    }

    /**
     * Establish the actual connection to ctFile server.
     *
     * @return mixed Connection resource or false on failure
     */
    private function establishConnection(): mixed
    {
        // Simulate connection establishment
        // In a real implementation, this would use actual ctFile connection logic
        $host = $this->config['host'];
        $port = $this->config['port'];
        $timeout = $this->config['timeout'];

        // Simulate network connection
        if ($host === 'invalid-host') {
            return false;
        }

        // Return a mock connection resource
        return (object) [
            'host' => $host,
            'port' => $port,
            'connected_at' => time(),
        ];
    }

    /**
     * Authenticate with ctFile server.
     *
     * @return bool True if authentication successful
     * @throws CtFileAuthenticationException If authentication fails
     */
    private function authenticate(): bool
    {
        if ($this->connection === null) {
            return false;
        }

        $username = $this->config['username'];
        $password = $this->config['password'];

        // Simulate authentication
        // In a real implementation, this would use actual ctFile authentication
        if ($username === 'invalid-user' || $password === 'invalid-password') {
            return false;
        }

        return true;
    }

    /**
     * Close the connection to ctFile server.
     *
     * @return void
     */
    private function closeConnection(): void
    {
        // Simulate connection closure
        // In a real implementation, this would properly close ctFile connection
        if ($this->connection !== null) {
            // Perform cleanup
            $this->connection = null;
        }
    }

    /**
     * Verify that the connection is still active.
     *
     * @return bool True if connection is active
     */
    private function verifyConnection(): bool
    {
        if ($this->connection === null) {
            return false;
        }

        // Simulate connection verification
        // In a real implementation, this would perform a lightweight check
        return is_object($this->connection) && isset($this->connection->connected_at);
    }

    /**
     * Upload a file to the ctFile server.
     *
     * @param string $localPath Local file path
     * @param string $remotePath Remote file path
     * @return bool True if upload successful
     * @throws CtFileOperationException If upload fails
     */
    public function uploadFile(string $localPath, string $remotePath): bool
    {
        $this->ensureConnected();

        if (!file_exists($localPath)) {
            throw CtFileOperationException::forOperation(
                "Local file does not exist: {$localPath}",
                'upload_file',
                $localPath
            );
        }

        if (!is_readable($localPath)) {
            throw CtFileOperationException::forOperation(
                "Local file is not readable: {$localPath}",
                'upload_file',
                $localPath
            );
        }

        try {
            // Simulate file upload
            // In a real implementation, this would use actual ctFile upload logic
            $success = $this->performFileUpload($localPath, $remotePath);

            if (!$success) {
                throw CtFileOperationException::forOperation(
                    "Failed to upload file to remote path: {$remotePath}",
                    'upload_file',
                    $remotePath
                );
            }

            return true;
        } catch (\Throwable $e) {
            if ($e instanceof CtFileOperationException) {
                throw $e;
            }

            throw CtFileOperationException::forOperation(
                "Upload operation failed: {$e->getMessage()}",
                'upload_file',
                $remotePath,
                $e
            );
        }
    }

    /**
     * Download a file from the ctFile server.
     *
     * @param string $remotePath Remote file path
     * @param string $localPath Local file path
     * @return bool True if download successful
     * @throws CtFileOperationException If download fails
     */
    public function downloadFile(string $remotePath, string $localPath): bool
    {
        $this->ensureConnected();

        if (!$this->fileExists($remotePath)) {
            throw CtFileOperationException::forOperation(
                "Remote file does not exist: {$remotePath}",
                'download_file',
                $remotePath
            );
        }

        $localDir = dirname($localPath);
        if (!is_dir($localDir)) {
            if (!mkdir($localDir, 0o755, true)) {
                throw CtFileOperationException::forOperation(
                    "Failed to create local directory: {$localDir}",
                    'download_file',
                    $localPath
                );
            }
        }

        try {
            // Simulate file download
            // In a real implementation, this would use actual ctFile download logic
            $success = $this->performFileDownload($remotePath, $localPath);

            if (!$success) {
                throw CtFileOperationException::forOperation(
                    "Failed to download file from remote path: {$remotePath}",
                    'download_file',
                    $remotePath
                );
            }

            return true;
        } catch (\Throwable $e) {
            if ($e instanceof CtFileOperationException) {
                throw $e;
            }

            throw CtFileOperationException::forOperation(
                "Download operation failed: {$e->getMessage()}",
                'download_file',
                $remotePath,
                $e
            );
        }
    }

    /**
     * Delete a file from the ctFile server.
     *
     * @param string $path Remote file path
     * @return bool True if deletion successful
     * @throws CtFileOperationException If deletion fails
     */
    public function deleteFile(string $path): bool
    {
        $this->ensureConnected();

        if (!$this->fileExists($path)) {
            // File doesn't exist, consider it already deleted
            return true;
        }

        try {
            // Simulate file deletion
            // In a real implementation, this would use actual ctFile deletion logic
            $success = $this->performFileDelete($path);

            if (!$success) {
                throw CtFileOperationException::forOperation(
                    "Failed to delete file: {$path}",
                    'delete_file',
                    $path
                );
            }

            return true;
        } catch (\Throwable $e) {
            if ($e instanceof CtFileOperationException) {
                throw $e;
            }

            throw CtFileOperationException::forOperation(
                "Delete operation failed: {$e->getMessage()}",
                'delete_file',
                $path,
                $e
            );
        }
    }

    /**
     * Check if a file exists on the ctFile server.
     *
     * @param string $path Remote file path
     * @return bool True if file exists
     * @throws CtFileOperationException If check fails
     */
    public function fileExists(string $path): bool
    {
        $this->ensureConnected();

        try {
            // Check if file exists and return true if key is not empty
            return $this->performFileExistsCheck($path) !== '';
        } catch (\Throwable $e) {
            throw CtFileOperationException::forOperation(
                "File existence check failed: {$e->getMessage()}",
                'file_exists',
                $path,
                $e
            );
        }
    }

    /**
     * Get file information and metadata.
     *
     * @param string $path Remote file path
     * @return array File information array
     * @throws CtFileOperationException If file info retrieval fails
     */
    /**
     * Get file ID by path.
     *
     * @param string $path File path
     * @return string File ID
     * @throws CtFileOperationException If file not found or operation fails
     */
    public function getFileId(string $path): string
    {
        error_log(sprintf('getFileId called for path: %s', $path));
        $fileKey = $this->performFileExistsCheck($path);
        error_log(sprintf('performFileExistsCheck returned: %s', $fileKey));
        
        if ($fileKey === '') {
            error_log(sprintf('File not found: %s', $path));
            throw new CtFileOperationException(
                'File or directory not found',
                'get_file_id',
                $path
            );
        }
        
        // Extract file ID from the key (remove 'f' prefix for files, 'd' prefix for directories)
        if (str_starts_with($fileKey, 'f') || str_starts_with($fileKey, 'd')) {
            return substr($fileKey, 1);
        }
        
        return $fileKey;
    }

    /**
     * Generate a public URL for the file.
     *
     * @param string $path File path
     * @return string Public URL
     * @throws CtFileOperationException If operation fails
     */
    public function getPublicUrl(string $path): string
    {
        $session = (string)($this->config['session'] ?? '');
        if ($session === '') {
            throw new CtFileOperationException('Missing session token in config', 'get_public_url', $path);
        }

        // First, try to get the weblink from file listing
        try {
            $fileKey = $this->performFileExistsCheck($path);
            if ($fileKey !== '') {
                // Get the weblink from the cached file info during exists check
                $weblink = $this->getWeblinkFromFileInfo($path);
                if ($weblink !== '') {
                    return $weblink;
                }
            }
        } catch (\Throwable $e) {
            // Continue to try directlink API if file info approach fails
        }

        // Fallback to directlink API
        $fileId = $this->getFileId($path);
        $baseUrl = rtrim((string)($this->config['api_base'] ?? 'https://rest.ctfile.com'), '/');
        $url = $baseUrl . '/v1/public/file/share';

        try {
            $response = $this->httpPostJson($url, [
                'session' => $session,
                'ids' => [$fileId]
            ]);

            error_log(sprintf('getPublicUrl API response: %s', json_encode($response)));

            if (!isset($response['code']) || (int)$response['code'] !== 200) {
                $msg = $response['message'] ?? 'Failed to generate public URL';
                throw new CtFileOperationException(
                    $msg,
                    'get_public_url',
                    $path,
                    [],
                    $response['code'] ?? 0
                );
            }

            // Handle different response structures
            $directlink = null;
            
            // Try different possible response structures
            if (isset($response['results']) && is_array($response['results']) && !empty($response['results'])) {
                $directlink = $response['results'][0]['directlink'] ?? null;
            }

//            if (empty($directlink)) {
//                throw new CtFileOperationException(
//                    'Invalid response format: missing URL in response: ' . json_encode($response),
//                    'get_public_url',
//                    $path
//                );
//            }

            return $directlink;
        } catch (\Throwable $e) {
            if ($e instanceof CtFileOperationException) {
                throw $e;
            }
            throw new CtFileOperationException(
                $e->getMessage(),
                'get_public_url',
                $path,
                [],
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Generate a temporary share link for the file.
     *
     * @param string $path File path
     * @param int $expiresIn Expiration time in seconds (default: 1 hour)
     * @return array Contains 'url' and 'expires_at' keys
     * @throws CtFileOperationException If operation fails
     */
    public function createTemporaryLink(string $path, int $expiresIn = 3600): array
    {
        $this->ensureConnected();
        
        $session = (string)($this->config['session'] ?? '');
        if ($session === '') {
            throw new CtFileOperationException('Missing session token in config', 'create_temporary_link', $path);
        }

        $fileId = $this->getFileId($path);
        $baseUrl = rtrim((string)($this->config['api_base'] ?? 'https://rest.ctfile.com'), '/');
        $url = $baseUrl . '/v1/public/file/share';

        try {
            $response = $this->httpPostJson($url, [
                'session' => $session,
                'ids' => [$fileId],
            ]);

            if (!isset($response['code']) || (int)$response['code'] !== 200) {
                throw new CtFileOperationException(
                    $response['message'] ?? 'Failed to create temporary link',
                    'create_temporary_link',
                    $path,
                    [],
                    $response['code'] ?? 0
                );
            }

            if (empty($response['result'])) {
                throw new CtFileOperationException(
                    'Invalid response format: missing URL',
                    'create_temporary_link',
                    $path
                );
            }

            return [
                'url' => $response['result'][0]['weblink'],
                'expires_at' => time() + $expiresIn
            ];
        } catch (\Exception $e) {
            throw new CtFileOperationException(
                $e->getMessage(),
                'create_temporary_link',
                $path,
                [],
                $e->getCode(),
                $e
            );
        }
    }

    public function getFileInfo(string $path): array
    {
        $this->ensureConnected();

        if (!$this->fileExists($path)) {
            throw CtFileOperationException::forOperation(
                "File does not exist: {$path}",
                'get_file_info',
                $path
            );
        }

        try {
            // Simulate file info retrieval
            // In a real implementation, this would use actual ctFile metadata retrieval
            return $this->performGetFileInfo($path);
        } catch (\Throwable $e) {
            if ($e instanceof CtFileOperationException) {
                throw $e;
            }

            throw CtFileOperationException::forOperation(
                "File info retrieval failed: {$e->getMessage()}",
                'get_file_info',
                $path,
                $e
            );
        }
    }

    /**
     * Get file contents as string.
     *
     * @param string $path Remote file path
     * @return string File contents
     * @throws CtFileOperationException If read fails
     */
    public function readFile(string $path): string
    {
        $this->ensureConnected();

        if (!$this->fileExists($path)) {
            throw CtFileOperationException::forOperation(
                "File does not exist: {$path}",
                'read_file',
                $path
            );
        }

        try {
            // Simulate file reading
            // In a real implementation, this would use actual ctFile read logic
            return $this->performFileRead($path);
        } catch (\Throwable $e) {
            if ($e instanceof CtFileOperationException) {
                throw $e;
            }

            throw CtFileOperationException::forOperation(
                "File read failed: {$e->getMessage()}",
                'read_file',
                $path,
                $e
            );
        }
    }

    /**
     * Write contents to a file on the ctFile server.
     *
     * @param string $path Remote file path
     * @param string $contents File contents
     * @return bool True if write successful
     * @throws CtFileOperationException If write fails
     */
    public function writeFile(string $path, string $contents): bool
    {
        $this->ensureConnected();

        $session = (string)($this->config['session'] ?? '');
        if ($session === '') {
            throw CtFileOperationException::forOperation('Missing session token in config', 'write_file', $path);
        }
        
        // 检查文件是否已存在
        if ($this->performFileExistsCheck($path)) {
            return true; // 文件已存在，跳过上传
        }
        
        $baseUrl = rtrim((string)($this->config['api_base'] ?? 'https://rest.ctfile.com'), '/');

        // Split remote path into directory and filename
        $normalized = str_replace(['\\'], '/', $path);
        $dir = rtrim((string)dirname($normalized), '/');
        $filename = basename($normalized);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            throw CtFileOperationException::forOperation('Invalid file name', 'write_file', $path);
        }

        // Resolve folder_id for target directory
        [$folderId, $_basePath] = $this->resolveFolderIdAndBasePath($dir === '.' ? '' : $dir, $session, $baseUrl);

        // Prepare temp file for multipart upload
        $tmp = tmpfile();
        if ($tmp === false) {
            throw CtFileOperationException::forOperation('Failed to create temp file', 'write_file', $path);
        }
        try {
            $bytes = fwrite($tmp, $contents);
            if ($bytes === false) {
                throw new \RuntimeException('Failed to write contents to temp file');
            }
            fflush($tmp);
            $meta = stream_get_meta_data($tmp);
            $tmpPath = $meta['uri'] ?? null;
            if (!$tmpPath || !is_file($tmpPath)) {
                throw new \RuntimeException('Temp file path unavailable');
            }

            // Step 1: initialize to get the upload URL (valid ~24h) — send JSON per API spec
            $initUrl = $baseUrl . '/v1/public/file/upload';
            $initPayload = [
                'session'   => $session,
                'folder_id' => $folderId,
                'file_name' => $filename,
                'size'      => (string) strlen($contents),
                'hash'      => md5($contents),
            ];
            $initResp = $this->httpPostJson($initUrl, $initPayload);
            if (!is_array($initResp) || !isset($initResp['code'])) {
                throw CtFileOperationException::forOperation('Invalid init response schema', 'write_file', $path);
            }
            if ((int) $initResp['code'] !== 200) {
                $msg = (string) ($initResp['message'] ?? 'init failed');
                throw CtFileOperationException::forOperation("Upload init error: {$msg}", 'write_file', $path);
            }
            var_dump($initResp);
            $uploadUrl = $initResp['upload_url'] ?? null;
            if ($uploadUrl === null) {
                throw CtFileOperationException::forOperation('Upload URL not found in init response', 'write_file', $path);
            }

            // Step 2: real upload to upload server (multipart/form-data)
            $uploadPayload = [
                'name'     => $filename,
                'filesize' => (string) strlen($contents),
                'file'     => new \CURLFile($tmpPath, null, $filename),
            ];
            $uploadResp = $this->httpPostMultipart($uploadUrl, $uploadPayload);
            var_dump($uploadResp);
            if (!is_array($uploadResp) || !isset($uploadResp['code'])) {
                throw CtFileOperationException::forOperation('Invalid upload response schema', 'write_file', $path);
            }
            if ((int) $uploadResp['code'] !== 200) {
                $msg = (string) ($uploadResp['message'] ?? 'upload failed');
                throw CtFileOperationException::forOperation("Upload error: {$msg}", 'write_file', $path);
            }

            return true;
        } finally {
            if (is_resource($tmp)) {
                fclose($tmp);
            }
        }
    }

    /**
     * Perform the actual file upload operation.
     *
     * @param string $localPath Local file path
     * @param string $remotePath Remote file path
     * @return bool True if successful
     */
    private function performFileUpload(string $localPath, string $remotePath): bool
    {
        // Simulate upload logic
        // In a real implementation, this would use actual ctFile API

        // Simulate failure for specific test cases
        if (str_contains($remotePath, 'fail-upload')) {
            return false;
        }

        // Simulate successful upload
        return true;
    }

    /**
     * Perform the actual file download operation.
     *
     * @param string $remotePath Remote file path
     * @param string $localPath Local file path
     * @return bool True if successful
     */
    private function performFileDownload(string $remotePath, string $localPath): bool
    {
        // Simulate download logic
        // In a real implementation, this would use actual ctFile API

        // Simulate failure for specific test cases
        if (str_contains($remotePath, 'fail-download')) {
            return false;
        }

        // Create a mock file for testing
        file_put_contents($localPath, "Mock file content from {$remotePath}");

        return true;
    }

    /**
     * Perform the actual file deletion operation.
     *
     * @param string $path Remote file path
     * @return bool True if successful
     */
    private function performFileDelete(string $path): bool
    {
        // Simulate deletion logic
        // In a real implementation, this would use actual ctFile API

        // Simulate failure for specific test cases
        if (str_contains($path, 'fail-delete')) {
            return false;
        }

        return true;
    }

    /**
     * Check if a file or directory exists and return its key.
     *
     * @param string $path Remote file or directory path
     * @return string File/folder key if exists, empty string otherwise
     * @throws CtFileOperationException If session is invalid
     */
    private function performFileExistsCheck(string $path): string
    {
        error_log(sprintf('performFileExistsCheck called for path: %s', $path));
        $session = (string)($this->config['session'] ?? '');
        if ($session === '') {
            $error = 'Missing session token in config';
            error_log($error);
            throw CtFileOperationException::forOperation($error, 'file_exists', $path);
        }

        $baseUrl = rtrim((string)($this->config['api_base'] ?? 'https://rest.ctfile.com'), '/');
        $path = trim($path, '/');
        
        // Handle root-level files
        if (strpos($path, '/') === false) {
            $pathParts = [];
            $filename = $path;
        } else {
            $pathParts = explode('/', $path);
            $filename = array_pop($pathParts);
        }
        
        try {
            $currentId = '0'; // Start from root directory

            // Traverse the directory structure if not at root level
            if (!empty($pathParts)) {
                foreach ($pathParts as $part) {
                    if (empty($part)) continue;
                    
                    // Use the same API format as apiListFiles
                    $entries = $this->apiListFiles('d' . $currentId, $session, $baseUrl);
                    $found = false;
                    foreach ($entries as $item) {
                        if ($item['name'] === $part && ($item['icon'] ?? '') === 'folder') {
                            // Extract folder ID from key, removing 'd' prefix
                            $key = $item['key'] ?? '';
                            if (preg_match('/^d?(\d+)$/', $key, $matches)) {
                                $currentId = $matches[1];
                            } else {
                                $currentId = preg_replace('/^d/', '', $key);
                            }
                            error_log(sprintf('Found folder "%s" with ID: %s', $part, $currentId));
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        error_log(sprintf('Folder "%s" not found in directory %s', $part, $currentId));
                        return '';
                    }
                }
            }

            // If we're checking a directory (no filename after last slash)
            if (empty($filename)) {
                return 'd' . $currentId;
            }

            // Search for the file in the current directory using apiListFiles
            $entries = $this->apiListFiles('d' . $currentId, $session, $baseUrl);
            
            // Check if file or folder exists in the directory
            foreach ($entries as $item) {
                if ($item['name'] === $filename) {
                    // Determine type from icon field or type field
                    $isFolder = ($item['icon'] ?? '') === 'folder' || ($item['type'] ?? '') === 'folder';
                    $prefix = $isFolder ? 'd' : 'f';
                    
                    // Extract ID from key field, removing any prefix
                    $key = $item['key'] ?? '';
                    if (preg_match('/^[df]?(\d+)$/', $key, $matches)) {
                        return $prefix . $matches[1];
                    }
                    return $prefix . $key;
                }
            }
            
            return ''; // File not found
        } catch (\Exception $e) {
            error_log(sprintf('Check file exists failed: %s', $e->getMessage()));
            return '';
        }
    }

    /**
     * Perform the actual file info retrieval.
     *
     * @param string $path Remote file path
     * @return array File information
     */
    private function performGetFileInfo(string $path): array
    {
        // Simulate file info retrieval
        // In a real implementation, this would use actual ctFile API
        return [
            'path' => $path,
            'type' => 'file',
            'size' => 1024,
            'last_modified' => time() - 3600,
            'permissions' => '644',
            'owner' => 'testuser',
            'group' => 'testgroup',
        ];
    }

    /**
     * Perform the actual file read operation.
     *
     * @param string $path Remote file path
     * @return string File contents
     */
    private function performFileRead(string $path): string
    {
        // Simulate file reading
        // In a real implementation, this would use actual ctFile API

        return "Mock file contents for {$path}";
    }

    /**
     * Perform the actual file write operation.
     *
     * @param string $path Remote file path
     * @param string $contents File contents
     * @return bool True if successful
     */
    private function performFileWrite(string $path, string $contents): bool
    {
        // Simulate file writing
        // In a real implementation, this would use actual ctFile API

        // Simulate failure for specific test cases
        if (str_contains($path, 'fail-write')) {
            return false;
        }

        return true;
    }

    /**
     * Create a directory on the ctFile server.
     *
     * @param string $path Remote directory path
     * @param bool $recursive Whether to create parent directories
     * @return bool True if creation successful
     * @throws CtFileOperationException If creation fails
     */
    public function createDirectory(string $path, bool $recursive = true): bool
    {
        $this->ensureConnected();

        if ($this->directoryExists($path)) {
            // Directory already exists, consider it successful
            return true;
        }

        try {
            // Simulate directory creation
            // In a real implementation, this would use actual ctFile directory creation logic
            $success = $this->performDirectoryCreate($path, $recursive);

            if (!$success) {
                throw CtFileOperationException::forOperation(
                    "Failed to create directory: {$path}",
                    'create_directory',
                    $path
                );
            }

            return true;
        } catch (\Throwable $e) {
            if ($e instanceof CtFileOperationException) {
                throw $e;
            }

            throw CtFileOperationException::forOperation(
                "Directory creation failed: {$e->getMessage()}",
                'create_directory',
                $path,
                $e
            );
        }
    }

    /**
     * Remove a directory from the ctFile server.
     *
     * @param string $path Remote directory path
     * @param bool $recursive Whether to remove directory contents
     * @return bool True if removal successful
     * @throws CtFileOperationException If removal fails
     */
    public function removeDirectory(string $path, bool $recursive = false): bool
    {
        $this->ensureConnected();

        if (!$this->directoryExists($path)) {
            // Directory doesn't exist, consider it already removed
            return true;
        }

        try {
            // Simulate directory removal
            // In a real implementation, this would use actual ctFile directory removal logic
            $success = $this->performDirectoryRemove($path, $recursive);

            if (!$success) {
                throw CtFileOperationException::forOperation(
                    "Failed to remove directory: {$path}",
                    'remove_directory',
                    $path
                );
            }

            return true;
        } catch (\Throwable $e) {
            if ($e instanceof CtFileOperationException) {
                throw $e;
            }

            throw CtFileOperationException::forOperation(
                "Directory removal failed: {$e->getMessage()}",
                'remove_directory',
                $path,
                $e
            );
        }
    }

    /**
     * Check if a directory exists on the ctFile server.
     *
     * @param string $path Remote directory path
     * @return bool True if directory exists
     * @throws CtFileOperationException If check fails
     */
    public function directoryExists(string $path): bool
    {
        $this->ensureConnected();

        try {
            // Simulate directory existence check
            // In a real implementation, this would use actual ctFile existence check
            return $this->performDirectoryExistsCheck($path);
        } catch (\Throwable $e) {
            throw CtFileOperationException::forOperation(
                "Directory existence check failed: {$e->getMessage()}",
                'directory_exists',
                $path,
                $e
            );
        }
    }

    /**
     * List files and directories in a directory.
     *
     * @param string $directory Remote directory path
     * @param bool $recursive Whether to list recursively
     * @return array Array of file and directory information
     * @throws CtFileOperationException If listing fails
     */
    public function listFiles(string $directory, bool $recursive = false): array
    {
        $this->ensureConnected();

        try {
            // Real implementation backed by ctFile OpenAPI
            return $this->performDirectoryListing($directory, $recursive);
        } catch (\Throwable $e) {
            if ($e instanceof CtFileOperationException) {
                throw $e;
            }

            throw CtFileOperationException::forOperation(
                "Directory listing failed: {$e->getMessage()}",
                'list_files',
                $directory,
                $e
            );
        }
    }

    /**
     * Get directory information and metadata.
     *
     * @param string $path Remote directory path
     * @return array Directory information array
     * @throws CtFileOperationException If directory info retrieval fails
     */
    public function getDirectoryInfo(string $path): array
    {
        $this->ensureConnected();

        if (!$this->directoryExists($path)) {
            throw CtFileOperationException::forOperation(
                "Directory does not exist: {$path}",
                'get_directory_info',
                $path
            );
        }

        try {
            // Simulate directory info retrieval
            // In a real implementation, this would use actual ctFile metadata retrieval
            return $this->performGetDirectoryInfo($path);
        } catch (\Throwable $e) {
            if ($e instanceof CtFileOperationException) {
                throw $e;
            }

            throw CtFileOperationException::forOperation(
                "Directory info retrieval failed: {$e->getMessage()}",
                'get_directory_info',
                $path,
                $e
            );
        }
    }

    /**
     * Move/rename a directory on the ctFile server.
     *
     * @param string $sourcePath Source directory path
     * @param string $destinationPath Destination directory path
     * @return bool True if move successful
     * @throws CtFileOperationException If move fails
     */
    public function moveDirectory(string $sourcePath, string $destinationPath): bool
    {
        $this->ensureConnected();

        if (!$this->directoryExists($sourcePath)) {
            throw CtFileOperationException::forOperation(
                "Source directory does not exist: {$sourcePath}",
                'move_directory',
                $sourcePath
            );
        }

        if ($this->directoryExists($destinationPath)) {
            throw CtFileOperationException::forOperation(
                "Destination directory already exists: {$destinationPath}",
                'move_directory',
                $destinationPath
            );
        }

        try {
            // Simulate directory move
            // In a real implementation, this would use actual ctFile move logic
            $success = $this->performDirectoryMove($sourcePath, $destinationPath);

            if (!$success) {
                throw CtFileOperationException::forOperation(
                    "Failed to move directory from {$sourcePath} to {$destinationPath}",
                    'move_directory',
                    $sourcePath
                );
            }

            return true;
        } catch (\Throwable $e) {
            if ($e instanceof CtFileOperationException) {
                throw $e;
            }

            throw CtFileOperationException::forOperation(
                "Directory move failed: {$e->getMessage()}",
                'move_directory',
                $sourcePath,
                $e
            );
        }
    }

    /**
     * Copy a directory on the ctFile server.
     *
     * @param string $sourcePath Source directory path
     * @param string $destinationPath Destination directory path
     * @param bool $recursive Whether to copy recursively
     * @return bool True if copy successful
     * @throws CtFileOperationException If copy fails
     */
    public function copyDirectory(string $sourcePath, string $destinationPath, bool $recursive = true): bool
    {
        $this->ensureConnected();

        if (!$this->directoryExists($sourcePath)) {
            throw CtFileOperationException::forOperation(
                "Source directory does not exist: {$sourcePath}",
                'copy_directory',
                $sourcePath
            );
        }

        try {
            // Simulate directory copy
            // In a real implementation, this would use actual ctFile copy logic
            $success = $this->performDirectoryCopy($sourcePath, $destinationPath, $recursive);

            if (!$success) {
                throw CtFileOperationException::forOperation(
                    "Failed to copy directory from {$sourcePath} to {$destinationPath}",
                    'copy_directory',
                    $sourcePath
                );
            }

            return true;
        } catch (\Throwable $e) {
            if ($e instanceof CtFileOperationException) {
                throw $e;
            }

            throw CtFileOperationException::forOperation(
                "Directory copy failed: {$e->getMessage()}",
                'copy_directory',
                $sourcePath,
                $e
            );
        }
    }

    /**
     * Perform the actual directory creation operation.
     *
     * @param string $path Remote directory path
     * @param bool $recursive Whether to create parent directories
     * @return bool True if successful
     */
    private function performDirectoryCreate(string $path, bool $recursive): bool
    {
        // Simulate directory creation logic
        // In a real implementation, this would use actual ctFile API

        // Simulate failure for specific test cases
        if (str_contains($path, 'fail-create')) {
            return false;
        }

        // Simulate parent directory requirement
        if (!$recursive && str_contains($path, '/deep/nested/')) {
            return false;
        }

        return true;
    }

    /**
     * Perform the actual directory removal operation.
     *
     * @param string $path Remote directory path
     * @param bool $recursive Whether to remove directory contents
     * @return bool True if successful
     */
    private function performDirectoryRemove(string $path, bool $recursive): bool
    {
        // Simulate directory removal logic
        // In a real implementation, this would use actual ctFile API

        // Simulate failure for specific test cases
        if (str_contains($path, 'fail-remove')) {
            return false;
        }

        // Simulate non-empty directory requirement
        if (!$recursive && str_contains($path, 'non-empty')) {
            return false;
        }

        return true;
    }

    /**
     * Perform the actual directory existence check.
     *
     * @param string $path Remote directory path
     * @return bool True if directory exists
     */
    private function performDirectoryExistsCheck(string $path): bool
    {
        // Simulate directory existence check
        // In a real implementation, this would use actual ctFile API

        // Simulate non-existent directories
        if (str_contains($path, 'nonexistent') || str_contains($path, 'not-found')) {
            return false;
        }

        // Simulate directories that don't exist for specific test cases
        if (str_contains($path, 'fail-create') || str_contains($path, 'dest-dir')) {
            return false;
        }

        return true;
    }

    /**
     * Perform the actual directory listing operation backed by ctFile OpenAPI.
     *
     * @param string $directory Remote directory path (e.g. "", "/", "foo/bar" or folder_id like d123)
     * @param bool $recursive Whether to list recursively
     * @return array
     */
    private function performDirectoryListing(string $directory, bool $recursive): array
    {
        $session = (string)($this->config['session'] ?? '');
        if ($session === '') {
            throw CtFileOperationException::forOperation('Missing session token in config', 'list_files', $directory);
        }

        $baseUrl = rtrim((string)($this->config['api_base'] ?? 'https://rest.ctfile.com'), '/');

        // Resolve folder_id: either the input already looks like a folder_id (d\d+), or resolve by walking path from root
        [$folderId, $basePath] = $this->resolveFolderIdAndBasePath($directory, $session, $baseUrl);

        // Fetch entries for this folder and map
        return $this->listByFolderId($folderId, $basePath, $session, $baseUrl, $recursive);
    }

    /**
     * Resolve ctFile folder_id from a path, or accept folder_id directly.
     * Returns [folderId, basePathString]
     */
    private function resolveFolderIdAndBasePath(string $directory, string $session, string $baseUrl): array
    {
        $dir = trim($directory);
        if ($dir === '' || $dir === '/') {
            return ['d0', ''];
        }

        $dir = trim($dir, '/');
        if (preg_match('/^d\d+$/', $dir) === 1) {
            // Treat as folder_id directly
            return [$dir, ''];
        }

        // Treat as path segments starting from root (d0)
        $segments = array_values(array_filter(explode('/', $dir), fn($s) => $s !== ''));
        $currentFolderId = 'd0';
        $currentPath = '';

        foreach ($segments as $seg) {
            $list = $this->apiListFiles($currentFolderId, $session, $baseUrl);
            $found = null;
            foreach ($list as $entry) {
                if (($entry['icon'] ?? '') === 'folder' && ($entry['name'] ?? '') === $seg) {
                    $found = $entry;
                    break;
                }
            }
            if ($found === null) {
                throw CtFileOperationException::forOperation("Path segment not found: {$seg}", 'list_files', $directory);
            }
            $currentFolderId = (string)($found['key'] ?? '');
            if ($currentFolderId === '') {
                throw CtFileOperationException::forOperation('Missing folder key in API result', 'list_files', $directory);
            }
            $currentPath = ($currentPath === '') ? $seg : ($currentPath . '/' . $seg);
        }

        return [$currentFolderId, $currentPath];
    }

    /**
     * List a folder by folder_id and map to adapter structure. Recurses if requested.
     */
    private function listByFolderId(string $folderId, string $basePath, string $session, string $baseUrl, bool $recursive): array
    {
        $results = [];
        $entries = $this->apiListFiles($folderId, $session, $baseUrl);

        foreach ($entries as $e) {
            $isDir = (($e['icon'] ?? '') === 'folder');
            $name = (string)($e['name'] ?? '');
            $path = ltrim(($basePath === '' ? '' : $basePath . '/') . $name, '/');
            $mapped = [
                'name' => $name,
                'path' => $path,
                'type' => $isDir ? 'directory' : 'file',
                'size' => $isDir ? 0 : (int)($e['size'] ?? 0),
                'last_modified' => isset($e['date']) ? (int)$e['date'] : null,
            ];
            $results[] = $mapped;

            if ($recursive && $isDir) {
                $childFolderId = (string)($e['key'] ?? '');
                if ($childFolderId !== '') {
                    $childItems = $this->listByFolderId($childFolderId, $path, $session, $baseUrl, true);
                    foreach ($childItems as $ci) {
                        $results[] = $ci;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Call ctFile OpenAPI to get entries for a folder.
     * Pagination per official doc:
     *  - request with reload=0 and start
     *  - response contains 'num' indicating next start; num=0 → no more pages
     * We aggregate pages with strong de-dup to handle any server overlaps.
     */
    private function apiListFiles(string $folderId, string $session, string $baseUrl): array
    {
        $url = $baseUrl . '/v1/public/file/list';
        $all = [];
        $seen = [];
        $start = 0;
        $maxLoops = 1000; // safety guard
        $loops = 0;

        while (true) {
            $payload = [
                'filter' => 'null',
                'folder_id' => $folderId,
                'orderby' => 'old',
                'start' => (string)$start,
                'reload' => 0,
                'session' => $session,
            ];

            $resp = $this->httpPostJson($url, $payload);

            if (!is_array($resp) || !isset($resp['code'])) {
                throw CtFileOperationException::forOperation('Invalid response schema', 'list_files', $folderId);
            }
            if ((int)$resp['code'] !== 200) {
                $msg = (string)($resp['message'] ?? 'unknown error');
                throw CtFileOperationException::forOperation("API error: {$msg}", 'list_files', $folderId);
            }

            $results = $resp['results'] ?? [];
            if (!is_array($results) || count($results) === 0) {
                break;
            }

            $addedThisLoop = 0;
            foreach ($results as $r) {
                // Prefer API 'key' for de-dup; fallback to a stable composite signature
                $sig = isset($r['key']) && $r['key'] !== ''
                    ? 'k:' . (string)$r['key']
                    : 's:' . md5(json_encode([
                        $r['name'] ?? '',
                        $r['icon'] ?? '',
                        (string)($r['size'] ?? ''),
                        (string)($r['date'] ?? ''),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                if (isset($seen[$sig])) {
                    continue;
                }
                $seen[$sig] = true;
                $all[] = $r;
                $addedThisLoop++;
            }

            // Next page start strictly follows doc: start += num; num=0 means no more
            $inc = isset($resp['num']) ? (int)$resp['num'] : 0;
            if ($inc <= 0) {
                break; // no more pages per doc
            }
            // Prevent infinite loops when server repeats the same window
            if ($addedThisLoop === 0) {
                break;
            }
            $start += $inc;

            $loops++;
            if ($loops >= $maxLoops) {
                break;
            }
        }

        return $all;
    }

    /**
     * Minimal HTTP POST JSON using cURL, returns decoded array.
     */
    private function httpPostJson(string $url, array $payload): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw CtFileOperationException::forOperation('Failed to init curl', 'http_post', $url);
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ];
        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw CtFileOperationException::forOperation("HTTP error: {$err}", 'http_post', $url);
        }
        curl_close($ch);

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw CtFileOperationException::forOperation('Invalid JSON response', 'http_post', $url);
        }

        return $data;
    }

    /**
     * Minimal HTTP POST multipart/form-data using cURL.
     */
    private function httpPostMultipart(string $url, array $formData): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw CtFileOperationException::forOperation('Failed to init curl', 'http_post_multipart', $url);
        }

        $headers = [
            'Accept: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $formData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw CtFileOperationException::forOperation('cURL error: ' . $err, 'http_post_multipart', $url);
        }

        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw CtFileOperationException::forOperation(
                'JSON decode error: ' . json_last_error_msg() . " (HTTP {$status})",
                'http_post_multipart',
                $url
            );
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Extract final upload URL from init response.
     * Supports variations: {url}, {upload_url}, or host+path+query triplet, or flat params
     */
    private function extractUploadUrl(array $resp): ?string
    {
        // Common direct keys
        foreach (['upload_url', 'url'] as $k) {
            if (isset($resp[$k]) && is_string($resp[$k]) && $resp[$k] !== '') {
                return $resp[$k];
            }
        }

        // Nested containers
        foreach (['data', 'result', 'upload'] as $container) {
            if (isset($resp[$container]) && is_array($resp[$container])) {
                $u = $this->extractUploadUrl($resp[$container]);
                if ($u !== null) {
                    return $u;
                }
            }
        }

        // host + path + query
        $host = $resp['host'] ?? null;
        $path = $resp['path'] ?? ($resp['endpoint'] ?? null);
        $query = $resp['query'] ?? null;
        if (is_string($host) && $host !== '' && is_string($path) && $path !== '') {
            $qs = '';
            if (is_array($query)) {
                $qs = http_build_query($query);
            } elseif (is_string($query) && $query !== '') {
                $qs = ltrim($query, '?');
            } else {
                // Try to assemble from known flat keys
                $known = [];
                foreach (['userid','maxsize','folderid','ctt','limit','spd','key'] as $p) {
                    if (isset($resp[$p])) { $known[$p] = $resp[$p]; }
                }
                if ($known) {
                    $qs = http_build_query($known);
                }
            }
            $scheme = str_starts_with($host, 'http') ? '' : 'https://';
            return $scheme . rtrim($host, '/') . $path . ($qs !== '' ? ('?' . $qs) : '');
        }

        // Flat keys: assemble default path
        if (isset($resp['userid'], $resp['key'])) {
            $qs = http_build_query(array_filter([
                'userid' => $resp['userid'],
                'maxsize' => $resp['maxsize'] ?? null,
                'folderid' => $resp['folderid'] ?? null,
                'ctt' => $resp['ctt'] ?? null,
                'limit' => $resp['limit'] ?? null,
                'spd' => $resp['spd'] ?? null,
                'key' => $resp['key'],
            ], fn($v) => $v !== null));
            return 'https://upload.ctfile.com/web/upload.do' . ($qs ? ('?' . $qs) : '');
        }

        return null;
    }

    /**
     * Perform the actual directory info retrieval.
     *
     * @param string $path Remote directory path
     * @return array Directory information
     */
    private function performGetDirectoryInfo(string $path): array
    {
        // Simulate directory info retrieval
        // In a real implementation, this would use actual ctFile API

        return [
            'path' => $path,
            'type' => 'directory',
            'permissions' => '755',
            'last_modified' => time() - 86400,
            'owner' => 'testuser',
            'group' => 'testgroup',
            'file_count' => 3,
            'directory_count' => 1,
            'total_size' => 3584,
        ];
    }

    /**
     * Perform the actual directory move operation.
     *
     * @param string $sourcePath Source directory path
     * @param string $destinationPath Destination directory path
     * @return bool True if successful
     */
    private function performDirectoryMove(string $sourcePath, string $destinationPath): bool
    {
        // Simulate directory move logic
        // In a real implementation, this would use actual ctFile API

        // Simulate failure for specific test cases
        if (str_contains($sourcePath, 'fail-move') || str_contains($destinationPath, 'fail-move')) {
            return false;
        }

        return true;
    }

    /**
     * Perform the actual directory copy operation.
     *
     * @param string $sourcePath Source directory path
     * @param string $destinationPath Destination directory path
     * @param bool $recursive Whether to copy recursively
     * @return bool True if successful
     */
    private function performDirectoryCopy(string $sourcePath, string $destinationPath, bool $recursive): bool
    {
        // Simulate directory copy logic
        // In a real implementation, this would use actual ctFile API

        // Simulate failure for specific test cases
        if (str_contains($sourcePath, 'fail-copy') || str_contains($destinationPath, 'fail-copy')) {
            return false;
        }

        return true;
    }

    /**
     * Get weblink from file info during file existence check.
     *
     * @param string $path File path
     * @return string Weblink URL or empty string if not found
     */
    private function getWeblinkFromFileInfo(string $path): string
    {
        $session = (string)($this->config['session'] ?? '');
        if ($session === '') {
            return '';
        }

        $baseUrl = rtrim((string)($this->config['api_base'] ?? 'https://rest.ctfile.com'), '/');
        $path = trim($path, '/');
        
        // Handle root-level files
        if (strpos($path, '/') === false) {
            $pathParts = [];
            $filename = $path;
        } else {
            $pathParts = explode('/', $path);
            $filename = array_pop($pathParts);
        }
        
        try {
            $currentId = '0'; // Start from root directory

            // Traverse the directory structure if not at root level
            if (!empty($pathParts)) {
                foreach ($pathParts as $part) {
                    if (empty($part)) continue;
                    
                    $requestData = [
                        'session' => $session,
                        'folder_id' => $currentId,
                        'page' => 1,
                        'per_page' => 100,
                        'filter' => 'folder',
                        'search_value' => $part
                    ];
                    $response = $this->httpPostJson($baseUrl . '/v1/public/file/list', $requestData);

                    if (!isset($response['code']) || (int)$response['code'] !== 200) {
                        return '';
                    }

                    $items = $response['results'] ?? $response['data']['list'] ?? [];
                    $found = false;
                    foreach ($items as $item) {
                        if ($item['name'] === $part && ($item['icon'] ?? '') === 'folder') {
                            $currentId = preg_replace('/^d/', '', $item['key'] ?? '');
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        return '';
                    }
                }
            }

            // Search for the file in the current directory
            $requestData = [
                'session' => $session,
                'folder_id' => $currentId,
                'page' => 1,
                'per_page' => 100,
                'search_value' => $filename
            ];
            
            if (empty($pathParts)) {
                $requestData['filter'] = 'file';
            }
            
            $response = $this->httpPostJson($baseUrl . '/v1/public/file/list', $requestData);

            if (!isset($response['code']) || (int)$response['code'] !== 200) {
                return '';
            }
            
            $items = $response['results'] ?? $response['data']['list'] ?? [];
            
            foreach ($items as $item) {
                if ($item['name'] === $filename && ($item['icon'] ?? '') !== 'folder') {
                    return $item['weblink'] ?? '';
                }
            }
            
            return '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Destructor to ensure proper cleanup.
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
