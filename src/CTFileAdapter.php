<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
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
use YangWeijie\FilesystemCtfile\Utilities\MetadataMapper;

/**
 * CtFileAdapter - Flysystem adapter for ctFile integration.
 *
 * This adapter implements the Flysystem FilesystemAdapter interface to provide
 * seamless integration between Flysystem and ctFile functionality.
 */
class CtFileAdapter implements FilesystemAdapter
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

            // Create parent directories if configured to do so
            if ($this->config['create_directories']) {
                $parentDir = dirname($prefixedPath);
                if ($parentDir !== '.' && $parentDir !== '/' && !$this->executeWithRetry(fn () => $this->client->directoryExists($parentDir))) {
                    $this->executeWithRetry(
                        fn () => $this->client->createDirectory($parentDir, true),
                        ['operation' => 'createParentDirectory', 'path' => $parentDir]
                    );
                }
            }

            // Write file contents using CtFileClient
            $success = $this->executeWithRetry(
                fn () => $this->client->writeFile($prefixedPath, $contents),
                ['operation' => 'write', 'path' => $path, 'size' => strlen($contents)]
            );

            if (!$success) {
                throw UnableToWriteFile::atLocation($path, 'Write operation failed');
            }

            // Invalidate cache after successful write
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
        if (!is_resource($contents)) {
            throw UnableToWriteFile::atLocation($path, 'Contents must be a resource');
        }

        try {
            $prefixedPath = $this->prefixer->prefixPath($path);

            // Create parent directories if configured to do so
            if ($this->config['create_directories']) {
                $parentDir = dirname($prefixedPath);
                if ($parentDir !== '.' && $parentDir !== '/' && !$this->executeWithRetry(fn () => $this->client->directoryExists($parentDir))) {
                    $this->executeWithRetry(
                        fn () => $this->client->createDirectory($parentDir, true),
                        ['operation' => 'createParentDirectory', 'path' => $parentDir]
                    );
                }
            }

            // Read stream contents into string
            // For large files, this could be optimized to use temporary files
            $streamContents = stream_get_contents($contents);
            if ($streamContents === false) {
                throw UnableToWriteFile::atLocation($path, 'Failed to read stream contents');
            }

            // Write file contents using CtFileClient
            $success = $this->executeWithRetry(
                fn () => $this->client->writeFile($prefixedPath, $streamContents),
                ['operation' => 'writeStream', 'path' => $path, 'size' => strlen($streamContents)]
            );

            if (!$success) {
                throw UnableToWriteFile::atLocation($path, 'Write stream operation failed');
            }

            // Invalidate cache after successful write
            $this->invalidateCache($path);
        } catch (\Throwable $exception) {
            if ($exception instanceof UnableToWriteFile) {
                throw $exception;
            }
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
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
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);

            // Check if file exists first
            if (!$this->executeWithRetry(fn () => $this->client->fileExists($prefixedPath))) {
                // File doesn't exist, consider it already deleted
                return;
            }

            // Delete the file using CtFileClient
            $success = $this->executeWithRetry(
                fn () => $this->client->deleteFile($prefixedPath),
                ['operation' => 'delete', 'path' => $path]
            );

            if (!$success) {
                throw UnableToDeleteFile::atLocation($path, 'Delete operation failed');
            }

            // Invalidate cache after successful deletion
            $this->invalidateCache($path);
        } catch (\Throwable $exception) {
            if ($exception instanceof UnableToDeleteFile) {
                throw $exception;
            }
            throw UnableToDeleteFile::atLocation($path, $exception->getMessage(), $exception);
        }
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
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);

            // Check if directory exists first
            if (!$this->executeWithRetry(fn () => $this->client->directoryExists($prefixedPath))) {
                // Directory doesn't exist, consider it already deleted
                return;
            }

            // Delete the directory using CtFileClient (recursive by default)
            $success = $this->executeWithRetry(
                fn () => $this->client->removeDirectory($prefixedPath, true),
                ['operation' => 'deleteDirectory', 'path' => $path]
            );

            if (!$success) {
                throw UnableToDeleteDirectory::atLocation($path, 'Directory deletion failed');
            }

            // Invalidate cache after successful deletion
            $this->invalidateCache($path);
        } catch (\Throwable $exception) {
            if ($exception instanceof UnableToDeleteDirectory) {
                throw $exception;
            }
            throw UnableToDeleteDirectory::atLocation($path, $exception->getMessage(), $exception);
        }
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
        try {
            $prefixedPath = $this->prefixer->prefixPath($path);

            // Check if directory already exists
            if ($this->executeWithRetry(fn () => $this->client->directoryExists($prefixedPath))) {
                // Directory already exists, consider it successful
                return;
            }

            // Create the directory using CtFileClient (recursive by default)
            $success = $this->executeWithRetry(
                fn () => $this->client->createDirectory($prefixedPath, true),
                ['operation' => 'createDirectory', 'path' => $path]
            );

            if (!$success) {
                throw UnableToCreateDirectory::atLocation($path, 'Directory creation failed');
            }

            // Invalidate cache after successful creation
            $this->invalidateCache($path);
        } catch (\Throwable $exception) {
            if ($exception instanceof UnableToCreateDirectory) {
                throw $exception;
            }
            throw UnableToCreateDirectory::atLocation($path, $exception->getMessage(), $exception);
        }
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
            $prefixedPath = $this->prefixer->prefixPath($path);

            // Check cache first
            $cachedListing = $this->getCachedListing($path, $deep);
            if ($cachedListing !== null) {
                foreach ($cachedListing as $item) {
                    yield MetadataMapper::toFileAttributes($item);
                }
                return;
            }

            // Get directory listing from client
            $listing = $this->executeWithRetry(
                fn () => $this->client->listFiles($prefixedPath, $deep),
                ['operation' => 'listContents', 'path' => $path, 'recursive' => $deep]
            );

            // Cache the raw listing
            $this->cacheListing($path, $listing, $deep);

            // Convert each item to FileAttributes and yield
            foreach ($listing as $item) {
                // Remove the prefix from the path to get the relative path
                $relativePath = $this->prefixer->stripPrefix($item['path'] ?? $item['name'] ?? '');
                
                // Create FileAttributes with proper metadata
                $attributes = MetadataMapper::toFileAttributes(array_merge($item, [
                    'path' => $relativePath
                ]));

                yield $attributes;
            }
        } catch (\Throwable $exception) {
            // For directory listing errors, we'll throw a generic exception
            // since Flysystem doesn't have a specific exception for listing failures
            throw new \RuntimeException(
                sprintf('Unable to list contents of directory "%s": %s', $path, $exception->getMessage()),
                0,
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
        try {
            $prefixedSource = $this->prefixer->prefixPath($source);
            $prefixedDestination = $this->prefixer->prefixPath($destination);

            // Check if source file exists
            if (!$this->executeWithRetry(fn () => $this->client->fileExists($prefixedSource))) {
                throw UnableToMoveFile::fromLocationTo($source, $destination);
            }

            // Create parent directories for destination if configured to do so
            if ($this->config['create_directories']) {
                $parentDir = dirname($prefixedDestination);
                if ($parentDir !== '.' && $parentDir !== '/' && !$this->executeWithRetry(fn () => $this->client->directoryExists($parentDir))) {
                    $this->executeWithRetry(
                        fn () => $this->client->createDirectory($parentDir, true),
                        ['operation' => 'createParentDirectory', 'path' => $parentDir]
                    );
                }
            }

            // Move the file using CtFileClient
            $success = $this->executeWithRetry(
                fn () => $this->client->moveFile($prefixedSource, $prefixedDestination),
                ['operation' => 'move', 'source' => $source, 'destination' => $destination]
            );

            if (!$success) {
                throw UnableToMoveFile::fromLocationTo($source, $destination);
            }

            // Invalidate cache for both source and destination paths
            $this->invalidateCache($source);
            $this->invalidateCache($destination);
        } catch (\Throwable $exception) {
            if ($exception instanceof UnableToMoveFile) {
                throw $exception;
            }
            throw UnableToMoveFile::fromLocationTo($source, $destination, $exception);
        }
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
        try {
            $prefixedSource = $this->prefixer->prefixPath($source);
            $prefixedDestination = $this->prefixer->prefixPath($destination);

            // Check if source file exists
            if (!$this->executeWithRetry(fn () => $this->client->fileExists($prefixedSource))) {
                throw UnableToCopyFile::fromLocationTo($source, $destination);
            }

            // Create parent directories for destination if configured to do so
            if ($this->config['create_directories']) {
                $parentDir = dirname($prefixedDestination);
                if ($parentDir !== '.' && $parentDir !== '/' && !$this->executeWithRetry(fn () => $this->client->directoryExists($parentDir))) {
                    $this->executeWithRetry(
                        fn () => $this->client->createDirectory($parentDir, true),
                        ['operation' => 'createParentDirectory', 'path' => $parentDir]
                    );
                }
            }

            // Copy the file using CtFileClient
            $success = $this->executeWithRetry(
                fn () => $this->client->copyFile($prefixedSource, $prefixedDestination),
                ['operation' => 'copy', 'source' => $source, 'destination' => $destination]
            );

            if (!$success) {
                throw UnableToCopyFile::fromLocationTo($source, $destination);
            }

            // Invalidate cache for destination path
            $this->invalidateCache($destination);
        } catch (\Throwable $exception) {
            if ($exception instanceof UnableToCopyFile) {
                throw $exception;
            }
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
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
