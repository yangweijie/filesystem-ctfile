<?php

declare(strict_types=1);

/*
 * This file is part of the yangweijie/filesystem-ctfile package.
 *
 * (c) Yang Weijie <yangweijie@example.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YangWeijie\FilesystemCtfile\Tests\Fixtures;

/**
 * Mock ctFile server for isolated testing.
 *
 * This class simulates a ctFile server with configurable responses,
 * error conditions, and state management for comprehensive testing
 * without requiring an actual ctFile server connection.
 */
class MockCtFileServer
{
    /**
     * Virtual filesystem storage.
     */
    private array $files = [];

    /**
     * Virtual directory storage.
     */
    private array $directories = [];

    /**
     * Connection state.
     */
    private bool $connected = false;

    /**
     * Server configuration.
     */
    private array $config = [];

    /**
     * Error simulation settings.
     */
    private array $errorSimulation = [];

    /**
     * Operation counters for testing.
     */
    private array $operationCounts = [];

    /**
     * Response delays for testing timeouts.
     */
    private array $responseDelays = [];

    /**
     * Authentication state.
     */
    private bool $authenticated = false;

    /**
     * Create a new mock ctFile server.
     *
     * @param array $config Server configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->reset();
    }

    /**
     * Connect to the mock server.
     *
     * @param string $host Server host
     * @param int $port Server port
     * @param string $username Username
     * @param string $password Password
     * @return bool Connection success
     * @throws \RuntimeException If connection fails
     */
    public function connect(string $host, int $port, string $username, string $password): bool
    {
        $this->incrementOperationCount('connect');

        if ($this->shouldSimulateError('connect')) {
            throw new \RuntimeException($this->getErrorMessage('connect'));
        }

        $this->simulateDelay('connect');

        // Simulate authentication
        if (!$this->authenticate($username, $password)) {
            throw new \RuntimeException('Authentication failed');
        }

        $this->connected = true;
        $this->authenticated = true;

        return true;
    }

    /**
     * Disconnect from the mock server.
     *
     * @return bool Disconnection success
     */
    public function disconnect(): bool
    {
        $this->incrementOperationCount('disconnect');

        $this->connected = false;
        $this->authenticated = false;

        return true;
    }

    /**
     * Check if connected to the server.
     *
     * @return bool Connection status
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Check if a file exists.
     *
     * @param string $path File path
     * @return bool File existence
     * @throws \RuntimeException If operation fails
     */
    public function fileExists(string $path): bool
    {
        $this->incrementOperationCount('fileExists');
        $this->requireConnection();

        if ($this->shouldSimulateError('fileExists')) {
            throw new \RuntimeException($this->getErrorMessage('fileExists'));
        }

        $this->simulateDelay('fileExists');

        return isset($this->files[$this->normalizePath($path)]);
    }

    /**
     * Check if a directory exists.
     *
     * @param string $path Directory path
     * @return bool Directory existence
     * @throws \RuntimeException If operation fails
     */
    public function directoryExists(string $path): bool
    {
        $this->incrementOperationCount('directoryExists');
        $this->requireConnection();

        if ($this->shouldSimulateError('directoryExists')) {
            throw new \RuntimeException($this->getErrorMessage('directoryExists'));
        }

        $this->simulateDelay('directoryExists');

        $normalizedPath = $this->normalizePath($path);

        return isset($this->directories[$normalizedPath]) || $normalizedPath === '/';
    }

    /**
     * Upload a file to the server.
     *
     * @param string $localPath Local file path
     * @param string $remotePath Remote file path
     * @param string|null $content File content (if null, reads from localPath)
     * @return bool Upload success
     * @throws \RuntimeException If operation fails
     */
    public function uploadFile(string $localPath, string $remotePath, ?string $content = null): bool
    {
        $this->incrementOperationCount('uploadFile');
        $this->requireConnection();

        if ($this->shouldSimulateError('uploadFile')) {
            throw new \RuntimeException($this->getErrorMessage('uploadFile'));
        }

        $this->simulateDelay('uploadFile');

        // Create parent directories if needed
        $this->createParentDirectories($remotePath);

        // Store file content
        $fileContent = $content ?? ($this->config['simulate_file_content'] ? "Mock content for {$localPath}" : '');
        $this->files[$this->normalizePath($remotePath)] = [
            'content' => $fileContent,
            'size' => strlen($fileContent),
            'modified' => time(),
            'mime_type' => $this->guessMimeType($remotePath),
            'visibility' => 'private',
        ];

        return true;
    }

    /**
     * Download a file from the server.
     *
     * @param string $remotePath Remote file path
     * @param string $localPath Local file path
     * @return bool Download success
     * @throws \RuntimeException If operation fails
     */
    public function downloadFile(string $remotePath, string $localPath): bool
    {
        $this->incrementOperationCount('downloadFile');
        $this->requireConnection();

        if ($this->shouldSimulateError('downloadFile')) {
            throw new \RuntimeException($this->getErrorMessage('downloadFile'));
        }

        $this->simulateDelay('downloadFile');

        $normalizedPath = $this->normalizePath($remotePath);
        if (!isset($this->files[$normalizedPath])) {
            throw new \RuntimeException("File not found: {$remotePath}");
        }

        // In a real implementation, this would write to the local file
        // For testing, we just verify the operation is valid
        return true;
    }

    /**
     * Delete a file from the server.
     *
     * @param string $path File path
     * @return bool Deletion success
     * @throws \RuntimeException If operation fails
     */
    public function deleteFile(string $path): bool
    {
        $this->incrementOperationCount('deleteFile');
        $this->requireConnection();

        if ($this->shouldSimulateError('deleteFile')) {
            throw new \RuntimeException($this->getErrorMessage('deleteFile'));
        }

        $this->simulateDelay('deleteFile');

        $normalizedPath = $this->normalizePath($path);
        if (!isset($this->files[$normalizedPath])) {
            throw new \RuntimeException("File not found: {$path}");
        }

        unset($this->files[$normalizedPath]);

        return true;
    }

    /**
     * Create a directory on the server.
     *
     * @param string $path Directory path
     * @return bool Creation success
     * @throws \RuntimeException If operation fails
     */
    public function createDirectory(string $path): bool
    {
        $this->incrementOperationCount('createDirectory');
        $this->requireConnection();

        if ($this->shouldSimulateError('createDirectory')) {
            throw new \RuntimeException($this->getErrorMessage('createDirectory'));
        }

        $this->simulateDelay('createDirectory');

        $normalizedPath = $this->normalizePath($path);

        // Create parent directories recursively
        $this->createParentDirectories($path);

        $this->directories[$normalizedPath] = [
            'created' => time(),
            'modified' => time(),
            'visibility' => 'private',
        ];

        return true;
    }

    /**
     * Delete a directory from the server.
     *
     * @param string $path Directory path
     * @param bool $recursive Whether to delete recursively
     * @return bool Deletion success
     * @throws \RuntimeException If operation fails
     */
    public function deleteDirectory(string $path, bool $recursive = false): bool
    {
        $this->incrementOperationCount('deleteDirectory');
        $this->requireConnection();

        if ($this->shouldSimulateError('deleteDirectory')) {
            throw new \RuntimeException($this->getErrorMessage('deleteDirectory'));
        }

        $this->simulateDelay('deleteDirectory');

        $normalizedPath = $this->normalizePath($path);

        if (!isset($this->directories[$normalizedPath])) {
            throw new \RuntimeException("Directory not found: {$path}");
        }

        if ($recursive) {
            // Delete all files and subdirectories
            foreach ($this->files as $filePath => $fileData) {
                if (str_starts_with($filePath, $normalizedPath . '/')) {
                    unset($this->files[$filePath]);
                }
            }

            foreach ($this->directories as $dirPath => $dirData) {
                if (str_starts_with($dirPath, $normalizedPath . '/')) {
                    unset($this->directories[$dirPath]);
                }
            }
        } else {
            // Check if directory is empty
            foreach ($this->files as $filePath => $fileData) {
                if (str_starts_with($filePath, $normalizedPath . '/')) {
                    throw new \RuntimeException("Directory not empty: {$path}");
                }
            }

            foreach ($this->directories as $dirPath => $dirData) {
                if (str_starts_with($dirPath, $normalizedPath . '/')) {
                    throw new \RuntimeException("Directory not empty: {$path}");
                }
            }
        }

        unset($this->directories[$normalizedPath]);

        return true;
    }

    /**
     * List files and directories in a path.
     *
     * @param string $path Directory path
     * @param bool $recursive Whether to list recursively
     * @return array List of files and directories
     * @throws \RuntimeException If operation fails
     */
    public function listDirectory(string $path, bool $recursive = false): array
    {
        $this->incrementOperationCount('listDirectory');
        $this->requireConnection();

        if ($this->shouldSimulateError('listDirectory')) {
            throw new \RuntimeException($this->getErrorMessage('listDirectory'));
        }

        $this->simulateDelay('listDirectory');

        $normalizedPath = $this->normalizePath($path);
        $results = [];

        // List files
        foreach ($this->files as $filePath => $fileData) {
            if ($this->isInDirectory($filePath, $normalizedPath, $recursive)) {
                $results[] = [
                    'type' => 'file',
                    'path' => $filePath,
                    'size' => $fileData['size'],
                    'modified' => $fileData['modified'],
                    'mime_type' => $fileData['mime_type'],
                    'visibility' => $fileData['visibility'],
                ];
            }
        }

        // List directories
        foreach ($this->directories as $dirPath => $dirData) {
            if ($this->isInDirectory($dirPath, $normalizedPath, $recursive) && $dirPath !== $normalizedPath) {
                $results[] = [
                    'type' => 'directory',
                    'path' => $dirPath,
                    'created' => $dirData['created'],
                    'modified' => $dirData['modified'],
                    'visibility' => $dirData['visibility'],
                ];
            }
        }

        return $results;
    }

    /**
     * Get file information.
     *
     * @param string $path File path
     * @return array File information
     * @throws \RuntimeException If operation fails
     */
    public function getFileInfo(string $path): array
    {
        $this->incrementOperationCount('getFileInfo');
        $this->requireConnection();

        if ($this->shouldSimulateError('getFileInfo')) {
            throw new \RuntimeException($this->getErrorMessage('getFileInfo'));
        }

        $this->simulateDelay('getFileInfo');

        $normalizedPath = $this->normalizePath($path);
        if (!isset($this->files[$normalizedPath])) {
            throw new \RuntimeException("File not found: {$path}");
        }

        return array_merge($this->files[$normalizedPath], [
            'path' => $normalizedPath,
            'type' => 'file',
        ]);
    }

    /**
     * Read file content from the mock server.
     *
     * @param string $path File path
     * @return string File content
     * @throws \RuntimeException If operation fails
     */
    public function readFile(string $path): string
    {
        $this->incrementOperationCount('readFile');
        $this->requireConnection();

        if ($this->shouldSimulateError('readFile')) {
            throw new \RuntimeException($this->getErrorMessage('readFile'));
        }

        $this->simulateDelay('readFile');

        $normalizedPath = $this->normalizePath($path);
        if (!isset($this->files[$normalizedPath])) {
            throw new \RuntimeException("File not found: {$path}");
        }

        return $this->files[$normalizedPath]['content'];
    }

    /**
     * Add a file to the mock server.
     *
     * @param string $path File path
     * @param string $content File content
     * @param array $metadata Additional metadata
     * @return void
     */
    public function addFile(string $path, string $content, array $metadata = []): void
    {
        $this->createParentDirectories($path);

        $this->files[$this->normalizePath($path)] = array_merge([
            'content' => $content,
            'size' => strlen($content),
            'modified' => time(),
            'mime_type' => $this->guessMimeType($path),
            'visibility' => 'private',
        ], $metadata);
    }

    /**
     * Add a directory to the mock server.
     *
     * @param string $path Directory path
     * @param array $metadata Additional metadata
     * @return void
     */
    public function addDirectory(string $path, array $metadata = []): void
    {
        $this->createParentDirectories($path);

        $this->directories[$this->normalizePath($path)] = array_merge([
            'created' => time(),
            'modified' => time(),
            'visibility' => 'private',
        ], $metadata);
    }

    /**
     * Configure error simulation for specific operations.
     *
     * @param string $operation Operation name
     * @param bool $shouldError Whether to simulate error
     * @param string $errorMessage Custom error message
     * @return void
     */
    public function simulateError(string $operation, bool $shouldError = true, string $errorMessage = ''): void
    {
        $this->errorSimulation[$operation] = [
            'enabled' => $shouldError,
            'message' => $errorMessage ?: "Simulated error for {$operation}",
        ];
    }

    /**
     * Configure response delay for specific operations.
     *
     * @param string $operation Operation name
     * @param int $delayMicroseconds Delay in microseconds
     * @return void
     */
    public function setResponseDelay(string $operation, int $delayMicroseconds): void
    {
        $this->responseDelays[$operation] = $delayMicroseconds;
    }

    /**
     * Get operation count for testing.
     *
     * @param string $operation Operation name
     * @return int Operation count
     */
    public function getOperationCount(string $operation): int
    {
        return $this->operationCounts[$operation] ?? 0;
    }

    /**
     * Get all operation counts.
     *
     * @return array All operation counts
     */
    public function getAllOperationCounts(): array
    {
        return $this->operationCounts;
    }

    /**
     * Reset the mock server state.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->files = [];
        $this->directories = [
            '/' => [
                'created' => time(),
                'modified' => time(),
                'visibility' => 'private',
            ],
        ];
        $this->connected = false;
        $this->authenticated = false;
        $this->operationCounts = [];
        $this->errorSimulation = [];
        $this->responseDelays = [];
    }

    /**
     * Get current server state for debugging.
     *
     * @return array Server state
     */
    public function getState(): array
    {
        return [
            'connected' => $this->connected,
            'authenticated' => $this->authenticated,
            'files' => $this->files,
            'directories' => $this->directories,
            'operation_counts' => $this->operationCounts,
        ];
    }

    /**
     * Authenticate with the server.
     *
     * @param string $username Username
     * @param string $password Password
     * @return bool Authentication success
     */
    private function authenticate(string $username, string $password): bool
    {
        $validCredentials = $this->config['valid_credentials'] ?? [];

        foreach ($validCredentials as $cred) {
            if ($cred['username'] === $username && $cred['password'] === $password) {
                return true;
            }
        }

        // Default authentication for testing
        return $username === 'test' && $password === 'password';
    }

    /**
     * Check if connection is required and established.
     *
     * @throws \RuntimeException If not connected
     */
    private function requireConnection(): void
    {
        if (!$this->connected) {
            throw new \RuntimeException('Not connected to server');
        }
    }

    /**
     * Normalize a file path.
     *
     * @param string $path File path
     * @return string Normalized path
     */
    private function normalizePath(string $path): string
    {
        $path = trim($path, '/');

        return $path === '' ? '/' : '/' . $path;
    }

    /**
     * Create parent directories for a path.
     *
     * @param string $path File or directory path
     * @return void
     */
    private function createParentDirectories(string $path): void
    {
        $normalizedPath = $this->normalizePath($path);
        $parts = explode('/', trim($normalizedPath, '/'));

        if (count($parts) <= 1) {
            return;
        }

        // Remove the filename if this is a file path
        array_pop($parts);

        $currentPath = '';
        foreach ($parts as $part) {
            $currentPath .= '/' . $part;
            if (!isset($this->directories[$currentPath])) {
                $this->directories[$currentPath] = [
                    'created' => time(),
                    'modified' => time(),
                    'visibility' => 'private',
                ];
            }
        }
    }

    /**
     * Check if a path is in a directory.
     *
     * @param string $itemPath Item path
     * @param string $directoryPath Directory path
     * @param bool $recursive Whether to check recursively
     * @return bool Whether item is in directory
     */
    private function isInDirectory(string $itemPath, string $directoryPath, bool $recursive): bool
    {
        if ($directoryPath === '/') {
            if ($recursive) {
                return true;
            }

            return substr_count(trim($itemPath, '/'), '/') === 0;
        }

        if (!str_starts_with($itemPath, $directoryPath . '/')) {
            return false;
        }

        if ($recursive) {
            return true;
        }

        $relativePath = substr($itemPath, strlen($directoryPath) + 1);

        return strpos($relativePath, '/') === false;
    }

    /**
     * Guess MIME type from file extension.
     *
     * @param string $path File path
     * @return string MIME type
     */
    private function guessMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $mimeTypes = [
            'txt' => 'text/plain',
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Check if an error should be simulated for an operation.
     *
     * @param string $operation Operation name
     * @return bool Whether to simulate error
     */
    private function shouldSimulateError(string $operation): bool
    {
        return $this->errorSimulation[$operation]['enabled'] ?? false;
    }

    /**
     * Get error message for an operation.
     *
     * @param string $operation Operation name
     * @return string Error message
     */
    private function getErrorMessage(string $operation): string
    {
        return $this->errorSimulation[$operation]['message'] ?? "Error in {$operation}";
    }

    /**
     * Simulate response delay for an operation.
     *
     * @param string $operation Operation name
     * @return void
     */
    private function simulateDelay(string $operation): void
    {
        if (isset($this->responseDelays[$operation])) {
            usleep($this->responseDelays[$operation]);
        }
    }

    /**
     * Increment operation counter.
     *
     * @param string $operation Operation name
     * @return void
     */
    private function incrementOperationCount(string $operation): void
    {
        $this->operationCounts[$operation] = ($this->operationCounts[$operation] ?? 0) + 1;
    }

    /**
     * Get default server configuration.
     *
     * @return array Default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'simulate_file_content' => true,
            'valid_credentials' => [
                ['username' => 'test', 'password' => 'password'],
                ['username' => 'admin', 'password' => 'admin123'],
            ],
        ];
    }
}
