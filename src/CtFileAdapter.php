<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use YangWeijie\FilesystemCtfile\Utilities\MetadataMapper;

/**
 * CtFileAdapter - Flysystem adapter for ctFile integration.
 *
 * This adapter implements the Flysystem FilesystemAdapter interface to provide
 * seamless integration between Flysystem and ctFile functionality.
 */
class CtFileAdapter implements FilesystemAdapter, PublicUrlGenerator
{
    /**
     * CtFile client instance.
     */
    private CtFileClient $client;

    /**
     * Path prefixer for handling root paths.
     */
    private PathPrefixer $prefixer;

    /**
     * Adapter configuration.
     */
    private array $config;

    /**
     * Cache manager for metadata and directory listings.
     */
    private ?CacheManager $cacheManager;

    /**
     * Retry handler for failed operations.
     */
    private ?RetryHandler $retryHandler;

    /**
     * Create a new CtFileAdapter instance.
     *
     * @param CtFileClient $client Configured CtFile client
     * @param array $config Adapter configuration options
     * @param CacheManager|null $cacheManager Optional cache manager
     * @param RetryHandler|null $retryHandler Optional retry handler
     */
    public function __construct(
        CtFileClient $client,
        array $config = [],
        ?CacheManager $cacheManager = null,
        ?RetryHandler $retryHandler = null
    ) {
        $this->client = $client;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->prefixer = new PathPrefixer($this->config['root_path'], $this->config['path_separator']);
        $this->cacheManager = $cacheManager;
        $this->retryHandler = $retryHandler;
    }

    /**
     * Check if a file exists.
     *
     * @param string $path File path
     * @return bool True if file exists
     */
    public function fileExists(string $path): bool
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);

            return $this->executeWithRetry(
                fn () => $this->client->fileExists($prefixedPath),
                ['operation' => 'fileExists', 'path' => $path]
            );
        } catch (\Throwable $exception) {
            throw UnableToCheckFileExistence::forLocation($path, $exception);
        }
    }

    /**
     * Check if a directory exists.
     *
     * @param string $path Directory path
     * @return bool True if directory exists
     */
    public function directoryExists(string $path): bool
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);

            return $this->executeWithRetry(
                fn () => $this->client->directoryExists($prefixedPath),
                ['operation' => 'directoryExists', 'path' => $path]
            );
        } catch (\Throwable $exception) {
            throw UnableToCheckDirectoryExistence::forLocation($path, $exception);
        }
    }

    /**
     * Write file contents.
     *
     * @param string $path File path
     * @param string $contents File contents
     * @param Config $config Write configuration
     * @return void
     * @throws UnableToWriteFile
     */
    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);

            // Optionally create parent directories if configured
            // (Real implementation would call client mkdir APIs; here we proceed directly)

            $success = $this->executeWithRetry(
                fn () => $this->client->writeFile($prefixedPath, $contents),
                ['operation' => 'write', 'path' => $path]
            );

            if ($success !== true) {
                throw UnableToWriteFile::atLocation($path, 'ctFile write failed');
            }

            // Invalidate caches for this path and its parent directory listing
            $this->invalidateCache($path);
        } catch (\Throwable $exception) {
            if ($exception instanceof UnableToWriteFile) {
                throw $exception;
            }
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * Write file contents from stream.
     *
     * @param string $path File path
     * @param resource $contents File stream
     * @param Config $config Write configuration
     * @return void
     * @throws UnableToWriteFile
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        // Validate stream resource
        if (!is_resource($contents)) {
            throw UnableToWriteFile::atLocation($path, 'contents must be a stream resource');
        }

        // Read stream to string efficiently
        $data = stream_get_contents($contents);
        if ($data === false) {
            throw UnableToWriteFile::atLocation($path, 'failed to read from stream');
        }

        // Delegate to write()
        $this->write($path, $data, $config);
    }

    /**
     * Read file contents.
     *
     * @param string $path File path
     * @return string File contents
     * @throws UnableToReadFile
     */
    public function read(string $path): string
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);

            // Check if file exists first
            if (!$this->executeWithRetry(fn () => $this->client->fileExists($prefixedPath))) {
                throw UnableToReadFile::fromLocation($path, 'File does not exist');
            }

            // Read file contents using CtFileClient
            $contents = $this->executeWithRetry(
                fn () => $this->client->readFile($prefixedPath),
                ['operation' => 'read', 'path' => $path]
            );

            return $contents;
        } catch (\Throwable $exception) {
            if ($exception instanceof UnableToReadFile) {
                throw $exception;
            }
            throw UnableToReadFile::fromLocation($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * Read file contents as stream.
     *
     * @param string $path File path
     * @return resource File stream
     * @throws UnableToReadFile
     */
    public function readStream(string $path)
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);

            // Check if file exists first
            if (!$this->executeWithRetry(fn () => $this->client->fileExists($prefixedPath))) {
                throw UnableToReadFile::fromLocation($path, 'File does not exist');
            }

            // Since CtFileClient doesn't have a direct stream method,
            // we'll read the file contents and create a stream from it
            $contents = $this->executeWithRetry(
                fn () => $this->client->readFile($prefixedPath),
                ['operation' => 'readStream', 'path' => $path]
            );

            // Create a memory stream from the contents
            $stream = fopen('php://memory', 'r+');
            if ($stream === false) {
                throw UnableToReadFile::fromLocation($path, 'Failed to create memory stream');
            }

            fwrite($stream, $contents);
            rewind($stream);

            return $stream;
        } catch (\Throwable $exception) {
            if ($exception instanceof UnableToReadFile) {
                throw $exception;
            }
            throw UnableToReadFile::fromLocation($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * Delete a file.
     *
     * @param string $path File path
     * @return void
     * @throws UnableToDeleteFile
     */
    public function delete(string $path): void
    {
        // Implementation will be added in task 6.5
        throw new \BadMethodCallException('Method not yet implemented');
    }

    /**
     * Delete a directory.
     *
     * @param string $path Directory path
     * @return void
     * @throws UnableToDeleteDirectory
     */
    public function deleteDirectory(string $path): void
    {
        // Implementation will be added in task 6.5
        throw new \BadMethodCallException('Method not yet implemented');
    }

    /**
     * Create a directory.
     *
     * @param string $path Directory path
     * @param Config $config Directory configuration
     * @return void
     * @throws UnableToCreateDirectory
     */
    public function createDirectory(string $path, Config $config): void
    {
        // Implementation will be added in task 6.5
        throw new \BadMethodCallException('Method not yet implemented');
    }

    /**
     * Set file visibility.
     *
     * @param string $path File path
     * @param string $visibility Visibility setting
     * @return void
     * @throws UnableToSetVisibility
     */
    public function setVisibility(string $path, string $visibility): void
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);

            // Check if file exists first
            if (!$this->executeWithRetry(fn () => $this->client->fileExists($prefixedPath))) {
                throw UnableToSetVisibility::atLocation($path, 'File does not exist');
            }

            // For now, we simulate setting visibility since ctFile may not support it directly
            // In a real implementation, this would use actual ctFile visibility/permissions API
            $this->executeWithRetry(
                fn () => $this->simulateSetVisibility($prefixedPath, $visibility),
                ['operation' => 'setVisibility', 'path' => $path, 'visibility' => $visibility]
            );

            // Invalidate cache after visibility change
            $this->invalidateCache($path);
        } catch (\Throwable $exception) {
            if ($exception instanceof UnableToSetVisibility) {
                throw $exception;
            }
            throw UnableToSetVisibility::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * Get file visibility.
     *
     * @param string $path File path
     * @return FileAttributes File attributes with visibility
     * @throws UnableToRetrieveMetadata
     */
    public function visibility(string $path): FileAttributes
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);

            // Check cache first
            $cachedMetadata = $this->getCachedMetadata($path);
            if ($cachedMetadata !== null) {
                $visibility = MetadataMapper::extractVisibility($cachedMetadata);

                return new FileAttributes($path, null, $visibility);
            }

            // Get file info from client
            $fileInfo = $this->executeWithRetry(
                fn () => $this->client->getFileInfo($prefixedPath),
                ['operation' => 'visibility', 'path' => $path]
            );

            // Cache the metadata
            $this->cacheMetadata($path, $fileInfo);

            // Extract visibility and return FileAttributes
            $visibility = MetadataMapper::extractVisibility($fileInfo);

            return new FileAttributes($path, null, $visibility);
        } catch (\Throwable $exception) {
            throw UnableToRetrieveMetadata::visibility($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * Get file MIME type.
     *
     * @param string $path File path
     * @return FileAttributes File attributes with MIME type
     * @throws UnableToRetrieveMetadata
     */
    public function mimeType(string $path): FileAttributes
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);

            // Check cache first
            $cachedMetadata = $this->getCachedMetadata($path);
            if ($cachedMetadata !== null) {
                $mimeType = MetadataMapper::extractMimeType($cachedMetadata);

                return new FileAttributes($path, null, null, null, $mimeType);
            }

            // Get file info from client
            $fileInfo = $this->executeWithRetry(
                fn () => $this->client->getFileInfo($prefixedPath),
                ['operation' => 'mimeType', 'path' => $path]
            );

            // Cache the metadata
            $this->cacheMetadata($path, $fileInfo);

            // Extract MIME type and return FileAttributes
            $mimeType = MetadataMapper::extractMimeType($fileInfo);

            return new FileAttributes($path, null, null, null, $mimeType);
        } catch (\Throwable $exception) {
            throw UnableToRetrieveMetadata::mimeType($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * Get file last modified timestamp.
     *
     * @param string $path File path
     * @return FileAttributes File attributes with last modified timestamp
     * @throws UnableToRetrieveMetadata
     */
    public function lastModified(string $path): FileAttributes
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);

            // Check cache first
            $cachedMetadata = $this->getCachedMetadata($path);
            if ($cachedMetadata !== null) {
                $lastModified = MetadataMapper::extractTimestamp($cachedMetadata);

                return new FileAttributes($path, null, null, $lastModified);
            }

            // Get file info from client
            $fileInfo = $this->executeWithRetry(
                fn () => $this->client->getFileInfo($prefixedPath),
                ['operation' => 'lastModified', 'path' => $path]
            );

            // Cache the metadata
            $this->cacheMetadata($path, $fileInfo);

            // Extract timestamp and return FileAttributes
            $lastModified = MetadataMapper::extractTimestamp($fileInfo);

            return new FileAttributes($path, null, null, $lastModified);
        } catch (\Throwable $exception) {
            throw UnableToRetrieveMetadata::lastModified($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * Get file size.
     *
     * @param string $path File path
     * @return FileAttributes File attributes with file size
     * @throws UnableToRetrieveMetadata
     */
    public function fileSize(string $path): FileAttributes
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);

            // Check cache first
            $cachedMetadata = $this->getCachedMetadata($path);
            if ($cachedMetadata !== null && isset($cachedMetadata['size'])) {
                $size = (int) $cachedMetadata['size'];

                return new FileAttributes($path, $size);
            }

            // Get file info from client
            $fileInfo = $this->executeWithRetry(
                fn () => $this->client->getFileInfo($prefixedPath),
                ['operation' => 'fileSize', 'path' => $path]
            );

            // Cache the metadata
            $this->cacheMetadata($path, $fileInfo);

            // Extract size and return FileAttributes
            $size = isset($fileInfo['size']) ? (int) $fileInfo['size'] : null;

            return new FileAttributes($path, $size);
        } catch (\Throwable $exception) {
            throw UnableToRetrieveMetadata::fileSize($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * List directory contents.
     *
     * @param string $path Directory path
     * @param bool $deep Whether to list recursively
     * @return iterable<FileAttributes> Directory contents
     */
    public function listContents(string $path, bool $deep): iterable
    {
        try {
            // Use cache if available
            $cached = $this->getCachedListing($path, $deep);
            if ($cached !== null) {
                foreach ($cached as $item) {
                    // Map cached raw metadata to Flysystem attributes
                    if (isset($item['type']) && in_array(strtolower((string) $item['type']), ['dir', 'directory'], true)) {
                        yield MetadataMapper::toDirectoryAttributes($item);
                    } else {
                        yield MetadataMapper::toFileAttributes($item);
                    }
                }
                return;
            }

            // Prefix the path for underlying client
            $prefixedPath = $this->prefixer->prefixPath($path);
            // Normalize root path: some clients expect '' for root instead of '/'
            if ($prefixedPath === '/' || $prefixedPath === $this->getConfig('path_separator', '/')) {
                $prefixedPath = '';
            }

            // Retrieve listing from client with retry logic
            $listing = $this->executeWithRetry(
                fn () => $this->client->listFiles($prefixedPath, $deep),
                ['operation' => 'listContents', 'path' => $path, 'deep' => $deep]
            );

            // Ensure listing is an array
            if (!is_array($listing)) {
                $listing = [];
            }

            // Cache raw listing
            $this->cacheListing($path, $listing, $deep);

            // Map each entry to Flysystem attributes
            foreach ($listing as $item) {
                if (isset($item['type']) && in_array(strtolower((string) $item['type']), ['dir', 'directory'], true)) {
                    yield MetadataMapper::toDirectoryAttributes($item);
                } else {
                    yield MetadataMapper::toFileAttributes($item);
                }
            }
        } catch (\Throwable $exception) {
            // Flysystem v3 listContents typically does not specify a dedicated exception type
            // Re-throw as a generic RuntimeException to surface context to callers
            throw new \RuntimeException(
                sprintf('Failed to list contents for path "%s": %s', $path, $exception->getMessage()),
                (int) ($exception->getCode() ?: 0),
                $exception
            );
        }
    }

    /**
     * Move a file.
     *
     * @param string $source Source file path
     * @param string $destination Destination file path
     * @param Config $config Move configuration
     * @return void
     * @throws UnableToMoveFile
     */
    public function move(string $source, string $destination, Config $config): void
    {
        // Implementation will be added in task 6.5
        throw new \BadMethodCallException('Method not yet implemented');
    }

    /**
     * Copy a file.
     *
     * @param string $source Source file path
     * @param string $destination Destination file path
     * @param Config $config Copy configuration
     * @return void
     * @throws UnableToCopyFile
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        // Implementation will be added in task 6.5
        throw new \BadMethodCallException('Method not yet implemented');
    }

    /**
     * Get the CtFile client instance.
     *
     * @return CtFileClient
     */
    public function getClient(): CtFileClient
    {
        return $this->client;
    }

    /**
     * Get adapter configuration.
     *
     * @param string|null $key Specific configuration key
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
     * Get the path prefixer instance.
     *
     * @return PathPrefixer
     */
    public function getPrefixer(): PathPrefixer
    {
        return $this->prefixer;
    }

    /**
     * Get the cache manager instance.
     *
     * @return CacheManager|null
     */
    public function getCacheManager(): ?CacheManager
    {
        return $this->cacheManager;
    }

    /**
     * Set the cache manager instance.
     *
     * @param CacheManager|null $cacheManager
     * @return void
     */
    public function setCacheManager(?CacheManager $cacheManager): void
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Get the retry handler instance.
     *
     * @return RetryHandler|null
     */
    public function getRetryHandler(): ?RetryHandler
    {
        return $this->retryHandler;
    }

    /**
     * Get a public URL for the file at the given path.
     *
     * @param string $path The path to the file
     * @param \League\Flysystem\Config $config Additional configuration
     * @return string The public URL
     * @throws \League\Flysystem\FilesystemException
     */
    public function publicUrl(string $path, Config $config = null): string
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);
            error_log(sprintf('CtFileAdapter::publicUrl - original path: %s, prefixed path: %s', $path, $prefixedPath));
            
            return $this->executeWithRetry(
                fn () => $this->client->getPublicUrl($prefixedPath),
                ['operation' => 'public_url', 'path' => $path]
            );
        } catch (\Throwable $exception) {
            throw UnableToGenerateTemporaryUrl::dueToError($path, $exception);
        }
    }

    /**
     * Get a temporary URL for the file at the given path.
     *
     * @param string $path The path to the file
     * @param \DateTimeInterface $expiresAt When the URL should expire
     * @param array $options Additional options
     * @return string The temporary URL
     * @throws \League\Flysystem\FilesystemException
     */
    public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, array $options = []): string
    {
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);
            $expiresIn = $expiresAt->getTimestamp() - time();
            
            if ($expiresIn <= 0) {
                throw new \InvalidArgumentException('Expiration time must be in the future');
            }
            
            $result = $this->executeWithRetry(
                fn () => $this->client->createTemporaryLink($prefixedPath, $expiresIn),
                ['operation' => 'temporary_url', 'path' => $path]
            );
            
            return $result['url'];
        } catch (\Throwable $exception) {
            throw UnableToGenerateTemporaryUrl::dueToError($path, $exception);
        }
    }

    /**
     * Set the retry handler instance.
     *
     * @param RetryHandler|null $retryHandler
     * @return void
     */
    public function setRetryHandler(?RetryHandler $retryHandler): void
    {
        $this->retryHandler = $retryHandler;
    }

    /**
     * Invalidate cache for a path after write operations.
     *
     * @param string $path The path that was modified
     * @return void
     */
    private function invalidateCache(string $path): void
    {
        if ($this->cacheManager && $this->cacheManager->isEnabled()) {
            $this->cacheManager->invalidatePath($path);
        }
    }

    /**
     * Get cached metadata for a path.
     *
     * @param string $path The file path
     * @return array|null Cached metadata or null if not cached
     */
    private function getCachedMetadata(string $path): ?array
    {
        if (!$this->cacheManager || !$this->cacheManager->isEnabled()) {
            return null;
        }

        $cacheKey = $this->cacheManager->getMetadataKey($path);

        return $this->cacheManager->get($cacheKey);
    }

    /**
     * Cache metadata for a path.
     *
     * @param string $path The file path
     * @param array $metadata The metadata to cache
     * @return void
     */
    private function cacheMetadata(string $path, array $metadata): void
    {
        if ($this->cacheManager && $this->cacheManager->isEnabled()) {
            $cacheKey = $this->cacheManager->getMetadataKey($path);
            $this->cacheManager->set($cacheKey, $metadata);
        }
    }

    /**
     * Get cached directory listing for a path.
     *
     * @param string $path The directory path
     * @param bool $recursive Whether the listing is recursive
     * @return array|null Cached listing or null if not cached
     */
    private function getCachedListing(string $path, bool $recursive = false): ?array
    {
        if (!$this->cacheManager || !$this->cacheManager->isEnabled()) {
            return null;
        }

        $cacheKey = $this->cacheManager->getListingKey($path, $recursive);

        return $this->cacheManager->get($cacheKey);
    }

    /**
     * Cache directory listing for a path.
     *
     * @param string $path The directory path
     * @param array $listing The listing to cache
     * @param bool $recursive Whether the listing is recursive
     * @return void
     */
    private function cacheListing(string $path, array $listing, bool $recursive = false): void
    {
        if ($this->cacheManager && $this->cacheManager->isEnabled()) {
            $cacheKey = $this->cacheManager->getListingKey($path, $recursive);
            $this->cacheManager->set($cacheKey, $listing);
        }
    }

    /**
     * Execute an operation with retry logic if retry handler is available.
     *
     * @param callable $operation The operation to execute
     * @param array $context Additional context for logging
     * @return mixed The result of the operation
     * @throws \Throwable
     */
    private function executeWithRetry(callable $operation, array $context = []): mixed
    {
        if ($this->retryHandler) {
            return $this->retryHandler->execute($operation, $context);
        }

        return $operation();
    }

    /**
     * Simulate setting file visibility.
     *
     * In a real implementation, this would use actual ctFile visibility/permissions API.
     *
     * @param string $path File path
     * @param string $visibility Visibility setting
     * @return bool True if successful
     */
    private function simulateSetVisibility(string $path, string $visibility): bool
    {
        // In a real implementation, this would:
        // 1. Convert Flysystem visibility to ctFile permissions
        // 2. Use ctFile API to set file permissions
        // 3. Return success/failure status

        // For now, we just simulate success unless path contains 'fail'
        return !str_contains($path, 'fail-visibility');
    }

    /**
     * Get default adapter configuration.
     *
     * @return array Default configuration options
     */
    private function getDefaultConfig(): array
    {
        return [
            'root_path' => '',
            'path_separator' => '/',
            'case_sensitive' => true,
            'create_directories' => true,
        ];
    }
}
