# API Documentation

## Overview

The `yangweijie/filesystem-ctfile` package provides a comprehensive Flysystem adapter for ctFile integration. This documentation covers all public classes, methods, and configuration options available in the package.

## Table of Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Core Classes](#core-classes)
  - [CtFileAdapter](#ctfileadapter)
  - [CtFileClient](#ctfileclient)
  - [ConfigurationManager](#configurationmanager)
  - [ErrorHandler](#errorhandler)
- [Utility Classes](#utility-classes)
  - [PathNormalizer](#pathnormalizer)
  - [MetadataMapper](#metadatamapper)
- [Advanced Features](#advanced-features)
  - [CacheManager](#cachemanager)
  - [RetryHandler](#retryhandler)
- [Exception Classes](#exception-classes)
- [Configuration Reference](#configuration-reference)

## Installation

```bash
composer require yangweijie/filesystem-ctfile
```

## Configuration

### Basic Configuration

```php
use YangWeijie\FilesystemCtfile\CtFileAdapter;
use YangWeijie\FilesystemCtfile\CtFileClient;
use League\Flysystem\Filesystem;

$config = [
    'host' => 'your-ctfile-server.com',
    'port' => 21,
    'username' => 'your-username',
    'password' => 'your-password',
    'ssl' => false,
    'passive' => true,
    'timeout' => 30
];

$client = new CtFileClient($config);
$adapter = new CtFileAdapter($client);
$filesystem = new Filesystem($adapter);
```

### Advanced Configuration with Caching and Retry

```php
use YangWeijie\FilesystemCtfile\CacheManager;
use YangWeijie\FilesystemCtfile\RetryHandler;
use YangWeijie\FilesystemCtfile\Cache\MemoryCache;

$cache = new MemoryCache();
$cacheManager = new CacheManager($cache, ['enabled' => true, 'ttl' => 300]);
$retryHandler = new RetryHandler(3, 1000, 2.0);

$adapter = new CtFileAdapter($client, [], $cacheManager, $retryHandler);
```

## Core Classes

### CtFileAdapter

The main adapter class implementing Flysystem's `FilesystemAdapter` interface.

#### Constructor

```php
public function __construct(
    CtFileClient $client, 
    array $config = [], 
    ?CacheManager $cacheManager = null,
    ?RetryHandler $retryHandler = null
)
```

**Parameters:**
- `$client` - Configured CtFile client instance
- `$config` - Adapter configuration options
- `$cacheManager` - Optional cache manager for metadata and directory listings
- `$retryHandler` - Optional retry handler for failed operations

#### File Operations

##### fileExists()

```php
public function fileExists(string $path): bool
```

Check if a file exists on the ctFile server.

**Parameters:**
- `$path` - File path to check

**Returns:** `bool` - True if file exists, false otherwise

**Throws:** `UnableToCheckFileExistence` - If the existence check fails

##### read()

```php
public function read(string $path): string
```

Read file contents as a string.

**Parameters:**
- `$path` - File path to read

**Returns:** `string` - File contents

**Throws:** `UnableToReadFile` - If the file cannot be read

##### readStream()

```php
public function readStream(string $path)
```

Read file contents as a stream resource.

**Parameters:**
- `$path` - File path to read

**Returns:** `resource` - File stream

**Throws:** `UnableToReadFile` - If the file cannot be read

##### write()

```php
public function write(string $path, string $contents, Config $config): void
```

Write string contents to a file.

**Parameters:**
- `$path` - File path to write to
- `$contents` - File contents as string
- `$config` - Write configuration options

**Throws:** `UnableToWriteFile` - If the file cannot be written

##### writeStream()

```php
public function writeStream(string $path, $contents, Config $config): void
```

Write stream contents to a file.

**Parameters:**
- `$path` - File path to write to
- `$contents` - File contents as stream resource
- `$config` - Write configuration options

**Throws:** `UnableToWriteFile` - If the file cannot be written

##### delete()

```php
public function delete(string $path): void
```

Delete a file from the ctFile server.

**Parameters:**
- `$path` - File path to delete

**Throws:** `UnableToDeleteFile` - If the file cannot be deleted

#### Directory Operations

##### directoryExists()

```php
public function directoryExists(string $path): bool
```

Check if a directory exists on the ctFile server.

**Parameters:**
- `$path` - Directory path to check

**Returns:** `bool` - True if directory exists, false otherwise

**Throws:** `UnableToCheckDirectoryExistence` - If the existence check fails

##### createDirectory()

```php
public function createDirectory(string $path, Config $config): void
```

Create a directory on the ctFile server.

**Parameters:**
- `$path` - Directory path to create
- `$config` - Directory creation configuration

**Throws:** `UnableToCreateDirectory` - If the directory cannot be created

##### deleteDirectory()

```php
public function deleteDirectory(string $path): void
```

Delete a directory from the ctFile server.

**Parameters:**
- `$path` - Directory path to delete

**Throws:** `UnableToDeleteDirectory` - If the directory cannot be deleted

##### listContents()

```php
public function listContents(string $path, bool $deep): iterable
```

List directory contents.

**Parameters:**
- `$path` - Directory path to list
- `$deep` - Whether to list recursively

**Returns:** `iterable<FileAttributes>` - Iterator of file and directory attributes

#### Metadata Operations

##### fileSize()

```php
public function fileSize(string $path): FileAttributes
```

Get file size information.

**Parameters:**
- `$path` - File path

**Returns:** `FileAttributes` - File attributes with size information

**Throws:** `UnableToRetrieveMetadata` - If metadata cannot be retrieved

##### lastModified()

```php
public function lastModified(string $path): FileAttributes
```

Get file last modified timestamp.

**Parameters:**
- `$path` - File path

**Returns:** `FileAttributes` - File attributes with timestamp information

**Throws:** `UnableToRetrieveMetadata` - If metadata cannot be retrieved

##### mimeType()

```php
public function mimeType(string $path): FileAttributes
```

Get file MIME type.

**Parameters:**
- `$path` - File path

**Returns:** `FileAttributes` - File attributes with MIME type information

**Throws:** `UnableToRetrieveMetadata` - If metadata cannot be retrieved

##### visibility()

```php
public function visibility(string $path): FileAttributes
```

Get file visibility (public/private).

**Parameters:**
- `$path` - File path

**Returns:** `FileAttributes` - File attributes with visibility information

**Throws:** `UnableToRetrieveMetadata` - If metadata cannot be retrieved

##### setVisibility()

```php
public function setVisibility(string $path, string $visibility): void
```

Set file visibility.

**Parameters:**
- `$path` - File path
- `$visibility` - Visibility setting (`Visibility::PUBLIC` or `Visibility::PRIVATE`)

**Throws:** `UnableToSetVisibility` - If visibility cannot be set

#### File Operations

##### move()

```php
public function move(string $source, string $destination, Config $config): void
```

Move/rename a file.

**Parameters:**
- `$source` - Source file path
- `$destination` - Destination file path
- `$config` - Move configuration options

**Throws:** `UnableToMoveFile` - If the file cannot be moved

##### copy()

```php
public function copy(string $source, string $destination, Config $config): void
```

Copy a file.

**Parameters:**
- `$source` - Source file path
- `$destination` - Destination file path
- `$config` - Copy configuration options

**Throws:** `UnableToCopyFile` - If the file cannot be copied

#### Utility Methods

##### getClient()

```php
public function getClient(): CtFileClient
```

Get the underlying CtFile client instance.

**Returns:** `CtFileClient` - The client instance

##### getConfig()

```php
public function getConfig(?string $key = null, mixed $default = null): mixed
```

Get adapter configuration.

**Parameters:**
- `$key` - Specific configuration key (optional)
- `$default` - Default value if key not found

**Returns:** `mixed` - Configuration value or entire config array

##### getCacheManager()

```php
public function getCacheManager(): ?CacheManager
```

Get the cache manager instance.

**Returns:** `CacheManager|null` - The cache manager or null if not set

##### setCacheManager()

```php
public function setCacheManager(?CacheManager $cacheManager): void
```

Set the cache manager instance.

**Parameters:**
- `$cacheManager` - Cache manager instance or null to disable

##### getRetryHandler()

```php
public function getRetryHandler(): ?RetryHandler
```

Get the retry handler instance.

**Returns:** `RetryHandler|null` - The retry handler or null if not set

##### setRetryHandler()

```php
public function setRetryHandler(?RetryHandler $retryHandler): void
```

Set the retry handler instance.

**Parameters:**
- `$retryHandler` - Retry handler instance or null to disable

### CtFileClient

Wrapper class for ctFile functionality and API interactions.

#### Constructor

```php
public function __construct(array $config)
```

**Parameters:**
- `$config` - Connection configuration array

**Throws:** `CtFileConnectionException` - If configuration is invalid

#### Connection Management

##### connect()

```php
public function connect(): bool
```

Establish connection to ctFile server.

**Returns:** `bool` - True if connection successful

**Throws:** 
- `CtFileConnectionException` - If connection fails
- `CtFileAuthenticationException` - If authentication fails

##### disconnect()

```php
public function disconnect(): void
```

Disconnect from ctFile server. This method is graceful and won't throw exceptions.

##### isConnected()

```php
public function isConnected(): bool
```

Check if currently connected to ctFile server.

**Returns:** `bool` - True if connected, false otherwise

##### getConnectionStatus()

```php
public function getConnectionStatus(): array
```

Get detailed connection status information.

**Returns:** `array` - Connection status details including host, port, username, etc.

#### File Operations

##### uploadFile()

```php
public function uploadFile(string $localPath, string $remotePath): bool
```

Upload a file to the ctFile server.

**Parameters:**
- `$localPath` - Local file path to upload
- `$remotePath` - Remote destination path

**Returns:** `bool` - True if upload successful

**Throws:** `CtFileOperationException` - If upload fails

##### downloadFile()

```php
public function downloadFile(string $remotePath, string $localPath): bool
```

Download a file from the ctFile server.

**Parameters:**
- `$remotePath` - Remote file path to download
- `$localPath` - Local destination path

**Returns:** `bool` - True if download successful

**Throws:** `CtFileOperationException` - If download fails

##### deleteFile()

```php
public function deleteFile(string $path): bool
```

Delete a file from the ctFile server.

**Parameters:**
- `$path` - Remote file path to delete

**Returns:** `bool` - True if deletion successful

**Throws:** `CtFileOperationException` - If deletion fails

##### fileExists()

```php
public function fileExists(string $path): bool
```

Check if a file exists on the ctFile server.

**Parameters:**
- `$path` - Remote file path to check

**Returns:** `bool` - True if file exists

**Throws:** `CtFileOperationException` - If check fails

##### getFileInfo()

```php
public function getFileInfo(string $path): array
```

Get file information and metadata.

**Parameters:**
- `$path` - Remote file path

**Returns:** `array` - File information including size, permissions, timestamps, etc.

**Throws:** `CtFileOperationException` - If file info retrieval fails

##### readFile()

```php
public function readFile(string $path): string
```

Get file contents as string.

**Parameters:**
- `$path` - Remote file path

**Returns:** `string` - File contents

**Throws:** `CtFileOperationException` - If read fails

##### writeFile()

```php
public function writeFile(string $path, string $contents): bool
```

Write contents to a file on the ctFile server.

**Parameters:**
- `$path` - Remote file path
- `$contents` - File contents to write

**Returns:** `bool` - True if write successful

**Throws:** `CtFileOperationException` - If write fails

#### Directory Operations

##### createDirectory()

```php
public function createDirectory(string $path, bool $recursive = true): bool
```

Create a directory on the ctFile server.

**Parameters:**
- `$path` - Remote directory path to create
- `$recursive` - Whether to create parent directories

**Returns:** `bool` - True if creation successful

**Throws:** `CtFileOperationException` - If creation fails

##### removeDirectory()

```php
public function removeDirectory(string $path, bool $recursive = false): bool
```

Remove a directory from the ctFile server.

**Parameters:**
- `$path` - Remote directory path to remove
- `$recursive` - Whether to remove directory contents

**Returns:** `bool` - True if removal successful

**Throws:** `CtFileOperationException` - If removal fails

##### directoryExists()

```php
public function directoryExists(string $path): bool
```

Check if a directory exists on the ctFile server.

**Parameters:**
- `$path` - Remote directory path to check

**Returns:** `bool` - True if directory exists

**Throws:** `CtFileOperationException` - If check fails

##### listFiles()

```php
public function listFiles(string $directory, bool $recursive = false): array
```

List files and directories in a directory.

**Parameters:**
- `$directory` - Remote directory path to list
- `$recursive` - Whether to list recursively

**Returns:** `array` - Array of file and directory information

**Throws:** `CtFileOperationException` - If listing fails

##### getDirectoryInfo()

```php
public function getDirectoryInfo(string $path): array
```

Get directory information and metadata.

**Parameters:**
- `$path` - Remote directory path

**Returns:** `array` - Directory information including permissions, timestamps, etc.

**Throws:** `CtFileOperationException` - If directory info retrieval fails

#### Configuration Methods

##### getConfig()

```php
public function getConfig(?string $key = null, mixed $default = null): mixed
```

Get connection configuration.

**Parameters:**
- `$key` - Specific configuration key (optional)
- `$default` - Default value if key not found

**Returns:** `mixed` - Configuration value or entire config array

### ConfigurationManager

Handles configuration validation and management for the ctFile adapter.

#### Constructor

```php
public function __construct(array $config = [], ?ConfigurationValidator $validator = null)
```

**Parameters:**
- `$config` - Initial configuration array
- `$validator` - Optional custom configuration validator

#### Configuration Management

##### validate()

```php
public function validate(): bool
```

Validate the current configuration.

**Returns:** `bool` - True if configuration is valid

**Throws:** `CtFileConfigurationException` - If validation fails

##### validateKey()

```php
public function validateKey(string $key): bool
```

Validate a specific configuration key.

**Parameters:**
- `$key` - Configuration key to validate

**Returns:** `bool` - True if key is valid

**Throws:** `CtFileConfigurationException` - If validation fails

##### get()

```php
public function get(string $key, $default = null)
```

Get a configuration value by key (supports dot notation).

**Parameters:**
- `$key` - Configuration key (e.g., 'ctfile.host' or 'adapter.root_path')
- `$default` - Default value if key not found

**Returns:** `mixed` - Configuration value

##### set()

```php
public function set(string $key, $value): void
```

Set a configuration value (supports dot notation).

**Parameters:**
- `$key` - Configuration key
- `$value` - Value to set

##### merge()

```php
public function merge(array $config): void
```

Merge additional configuration with existing configuration.

**Parameters:**
- `$config` - Configuration array to merge

##### toArray()

```php
public function toArray(): array
```

Get all configuration as array.

**Returns:** `array` - Complete configuration array

##### getDefaultConfig()

```php
public static function getDefaultConfig(): array
```

Get default configuration structure.

**Returns:** `array` - Default configuration array

##### getValidator()

```php
public function getValidator(): ConfigurationValidator
```

Get the configuration validator instance.

**Returns:** `ConfigurationValidator` - The validator instance

### ErrorHandler

Centralized error handling and exception management for ctFile operations.

#### Constructor

```php
public function __construct(?LoggerInterface $logger = null)
```

**Parameters:**
- `$logger` - Optional PSR-3 logger instance for error logging

#### Error Handling

##### handleCtFileError()

```php
public function handleCtFileError(\Throwable $error, string $operation, string $path = ''): void
```

Handle ctFile errors and convert them to appropriate Flysystem exceptions.

**Parameters:**
- `$error` - The original ctFile error
- `$operation` - The operation being performed
- `$path` - The file or directory path involved

**Throws:** `\Throwable` - The converted Flysystem exception

##### createFlysystemException()

```php
public function createFlysystemException(
    string $type,
    string $message,
    string $path = '',
    ?\Throwable $previous = null
): \Throwable
```

Create a Flysystem exception of the specified type.

**Parameters:**
- `$type` - Exception type (read, write, delete, etc.)
- `$message` - Exception message
- `$path` - File or directory path
- `$previous` - Previous exception

**Returns:** `\Throwable` - The created Flysystem exception

##### logError()

```php
public function logError(\Throwable $error, array $context = []): void
```

Log an error with appropriate context information.

**Parameters:**
- `$error` - The error to log
- `$context` - Additional context information

## Utility Classes

### PathNormalizer

Utility class for path normalization, validation, and manipulation.

#### Path Operations

##### normalize()

```php
public static function normalize(string $path): string
```

Normalize a file path by removing redundant separators and resolving relative references.

**Parameters:**
- `$path` - The path to normalize

**Returns:** `string` - The normalized path

##### validate()

```php
public static function validate(string $path): bool
```

Validate a path to ensure it's safe and doesn't contain malicious patterns.

**Parameters:**
- `$path` - The path to validate

**Returns:** `bool` - True if path is valid, false otherwise

##### isAbsolute()

```php
public static function isAbsolute(string $path): bool
```

Check if a path is absolute.

**Parameters:**
- `$path` - The path to check

**Returns:** `bool` - True if path is absolute, false otherwise

##### join()

```php
public static function join(string ...$parts): string
```

Join multiple path parts into a single normalized path.

**Parameters:**
- `$parts` - Path parts to join

**Returns:** `string` - The joined and normalized path

##### dirname()

```php
public static function dirname(string $path): string
```

Get the directory name from a path.

**Parameters:**
- `$path` - The path

**Returns:** `string` - The directory name

##### basename()

```php
public static function basename(string $path): string
```

Get the base name from a path.

**Parameters:**
- `$path` - The path

**Returns:** `string` - The base name

### MetadataMapper

Utility class for converting ctFile metadata to Flysystem FileAttributes objects.

#### Metadata Conversion

##### toFileAttributes()

```php
public static function toFileAttributes(array $ctFileMetadata): FileAttributes
```

Convert ctFile metadata to Flysystem FileAttributes object.

**Parameters:**
- `$ctFileMetadata` - Raw metadata from ctFile

**Returns:** `FileAttributes` - Flysystem file attributes object

##### toDirectoryAttributes()

```php
public static function toDirectoryAttributes(array $ctFileMetadata): DirectoryAttributes
```

Convert ctFile metadata to Flysystem DirectoryAttributes object.

**Parameters:**
- `$ctFileMetadata` - Raw metadata from ctFile

**Returns:** `DirectoryAttributes` - Flysystem directory attributes object

##### extractMimeType()

```php
public static function extractMimeType(array $metadata): string
```

Extract MIME type from ctFile metadata.

**Parameters:**
- `$metadata` - ctFile metadata array

**Returns:** `string` - MIME type string

##### extractVisibility()

```php
public static function extractVisibility(array $metadata): string
```

Extract visibility from ctFile metadata.

**Parameters:**
- `$metadata` - ctFile metadata array

**Returns:** `string` - Flysystem visibility constant

##### extractTimestamp()

```php
public static function extractTimestamp(array $metadata): ?int
```

Extract timestamp from ctFile metadata.

**Parameters:**
- `$metadata` - ctFile metadata array

**Returns:** `int|null` - Unix timestamp or null if not available

##### createMinimalFileAttributes()

```php
public static function createMinimalFileAttributes(string $path, ?int $size = null): FileAttributes
```

Create FileAttributes with minimal information.

**Parameters:**
- `$path` - File path
- `$size` - File size in bytes (optional)

**Returns:** `FileAttributes` - Minimal file attributes object

##### createMinimalDirectoryAttributes()

```php
public static function createMinimalDirectoryAttributes(string $path): DirectoryAttributes
```

Create DirectoryAttributes with minimal information.

**Parameters:**
- `$path` - Directory path

**Returns:** `DirectoryAttributes` - Minimal directory attributes object

##### mergeFileAttributes()

```php
public static function mergeFileAttributes(FileAttributes $attributes, array $additionalMetadata): FileAttributes
```

Merge additional metadata into existing FileAttributes.

**Parameters:**
- `$attributes` - Existing attributes
- `$additionalMetadata` - Additional metadata to merge

**Returns:** `FileAttributes` - New FileAttributes with merged data

##### mergeDirectoryAttributes()

```php
public static function mergeDirectoryAttributes(DirectoryAttributes $attributes, array $additionalMetadata): DirectoryAttributes
```

Merge additional metadata into existing DirectoryAttributes.

**Parameters:**
- `$attributes` - Existing attributes
- `$additionalMetadata` - Additional metadata to merge

**Returns:** `DirectoryAttributes` - New DirectoryAttributes with merged data

## Advanced Features

### CacheManager

Manages caching for metadata and directory listings with TTL support.

#### Constructor

```php
public function __construct(?CacheInterface $cache = null, array $config = [])
```

**Parameters:**
- `$cache` - PSR-16 cache implementation
- `$config` - Cache configuration options

#### Cache Operations

##### isEnabled()

```php
public function isEnabled(): bool
```

Check if caching is enabled and available.

**Returns:** `bool` - True if caching is enabled

##### get()

```php
public function get(string $key, mixed $default = null): mixed
```

Get cached value by key.

**Parameters:**
- `$key` - Cache key
- `$default` - Default value if not found

**Returns:** `mixed` - Cached value or default

##### set()

```php
public function set(string $key, mixed $value, ?int $ttl = null): bool
```

Set cached value with TTL.

**Parameters:**
- `$key` - Cache key
- `$value` - Value to cache
- `$ttl` - Time to live in seconds (optional)

**Returns:** `bool` - True if successful

##### delete()

```php
public function delete(string $key): bool
```

Delete cached value by key.

**Parameters:**
- `$key` - Cache key to delete

**Returns:** `bool` - True if successful

##### clear()

```php
public function clear(): bool
```

Clear all cached values with the configured prefix.

**Returns:** `bool` - True if successful

##### invalidatePath()

```php
public function invalidatePath(string $path): void
```

Invalidate cache for a specific path and its parent directories.

**Parameters:**
- `$path` - Path to invalidate

##### getMetadataKey()

```php
public function getMetadataKey(string $path): string
```

Get cache key for file metadata.

**Parameters:**
- `$path` - File path

**Returns:** `string` - Cache key

##### getListingKey()

```php
public function getListingKey(string $path, bool $recursive = false): string
```

Get cache key for directory listing.

**Parameters:**
- `$path` - Directory path
- `$recursive` - Whether listing is recursive

**Returns:** `string` - Cache key

### RetryHandler

Handles retry logic for failed operations with configurable attempts and delays.

#### Constructor

```php
public function __construct(
    int $maxRetries = 3,
    int $baseDelay = 1000,
    float $backoffMultiplier = 2.0,
    int $maxDelay = 30000,
    array $retryableExceptions = [],
    ?LoggerInterface $logger = null
)
```

**Parameters:**
- `$maxRetries` - Maximum number of retry attempts
- `$baseDelay` - Base delay in milliseconds
- `$backoffMultiplier` - Exponential backoff multiplier
- `$maxDelay` - Maximum delay in milliseconds
- `$retryableExceptions` - Array of exception classes that should trigger retries
- `$logger` - Optional PSR-3 logger

#### Retry Operations

##### execute()

```php
public function execute(callable $operation, array $context = []): mixed
```

Execute an operation with retry logic.

**Parameters:**
- `$operation` - The operation to execute
- `$context` - Additional context for logging

**Returns:** `mixed` - The result of the operation

**Throws:** `\Throwable` - The last exception if all retries fail

##### shouldRetry()

```php
public function shouldRetry(\Throwable $exception): bool
```

Check if an exception should trigger a retry.

**Parameters:**
- `$exception` - The exception to check

**Returns:** `bool` - True if the operation should be retried

##### calculateDelay()

```php
public function calculateDelay(int $attempt): int
```

Calculate delay for the next retry attempt using exponential backoff.

**Parameters:**
- `$attempt` - The current attempt number (0-based)

**Returns:** `int` - Delay in milliseconds

#### Configuration Getters

##### getMaxRetries()

```php
public function getMaxRetries(): int
```

Get the maximum number of retries.

**Returns:** `int` - Maximum retries

##### getBaseDelay()

```php
public function getBaseDelay(): int
```

Get the base delay in milliseconds.

**Returns:** `int` - Base delay

##### getBackoffMultiplier()

```php
public function getBackoffMultiplier(): float
```

Get the backoff multiplier.

**Returns:** `float` - Backoff multiplier

##### getMaxDelay()

```php
public function getMaxDelay(): int
```

Get the maximum delay in milliseconds.

**Returns:** `int` - Maximum delay

##### getRetryableExceptions()

```php
public function getRetryableExceptions(): array
```

Get the list of retryable exception classes.

**Returns:** `array` - Array of exception class names

## Exception Classes

### CtFileException

Base exception class for all ctFile-related errors.

#### Constructor

```php
public function __construct(
    string $message,
    string $operation = '',
    string $path = '',
    array $context = [],
    int $code = 0,
    ?\Throwable $previous = null
)
```

#### Methods

##### getOperation()

```php
public function getOperation(): string
```

Get the operation that was being performed.

**Returns:** `string` - The operation name

##### getPath()

```php
public function getPath(): string
```

Get the file or directory path involved.

**Returns:** `string` - The path

##### getContext()

```php
public function getContext(): array
```

Get additional context information.

**Returns:** `array` - The context array

##### setContext()

```php
public function setContext(array $context): self
```

Set additional context information.

**Parameters:**
- `$context` - The context array

**Returns:** `self` - The exception instance

##### addContext()

```php
public function addContext(string $key, $value): self
```

Add a single context item.

**Parameters:**
- `$key` - The context key
- `$value` - The context value

**Returns:** `self` - The exception instance

#### Static Factory Methods

##### forOperation()

```php
public static function forOperation(
    string $message,
    string $operation,
    string $path = '',
    ?\Throwable $previous = null
): static
```

Create a new exception with operation context.

##### forPath()

```php
public static function forPath(string $message, string $path, ?\Throwable $previous = null): static
```

Create a new exception with path context.

### CtFileConnectionException

Exception thrown when ctFile connection operations fail.

#### Static Factory Methods

##### connectionFailed()

```php
public static function connectionFailed(string $host, int $port, ?\Throwable $previous = null): static
```

Create an exception for connection failure.

##### authenticationFailed()

```php
public static function authenticationFailed(string $username, ?\Throwable $previous = null): static
```

Create an exception for authentication failure.

##### connectionTimeout()

```php
public static function connectionTimeout(string $host, int $port, int $timeout, ?\Throwable $previous = null): static
```

Create an exception for connection timeout.

##### connectionLost()

```php
public static function connectionLost(?\Throwable $previous = null): static
```

Create an exception for connection lost.

##### sslError()

```php
public static function sslError(string $host, int $port, ?\Throwable $previous = null): static
```

Create an exception for SSL/TLS errors.

##### networkError()

```php
public static function networkError(string $message, ?\Throwable $previous = null): static
```

Create an exception for network errors.

### CtFileAuthenticationException

Exception thrown when ctFile authentication operations fail.

#### Static Factory Methods

##### invalidCredentials()

```php
public static function invalidCredentials(string $username, ?\Throwable $previous = null): static
```

Create an exception for invalid credentials.

##### credentialsExpired()

```php
public static function credentialsExpired(string $username, ?\Throwable $previous = null): static
```

Create an exception for expired credentials.

##### accountLocked()

```php
public static function accountLocked(string $username, ?\Throwable $previous = null): static
```

Create an exception for account locked.

##### permissionDenied()

```php
public static function permissionDenied(string $operation, string $path = '', ?\Throwable $previous = null): static
```

Create an exception for insufficient permissions.

##### sessionExpired()

```php
public static function sessionExpired(?\Throwable $previous = null): static
```

Create an exception for session expired.

##### authenticationRequired()

```php
public static function authenticationRequired(string $operation, ?\Throwable $previous = null): static
```

Create an exception for authentication required.

##### twoFactorRequired()

```php
public static function twoFactorRequired(string $username, ?\Throwable $previous = null): static
```

Create an exception for two-factor authentication required.

### CtFileOperationException

Exception thrown when ctFile file or directory operations fail.

#### Static Factory Methods

##### uploadFailed()

```php
public static function uploadFailed(string $localPath, string $remotePath, ?\Throwable $previous = null): static
```

Create an exception for file upload failure.

##### downloadFailed()

```php
public static function downloadFailed(string $remotePath, string $localPath, ?\Throwable $previous = null): static
```

Create an exception for file download failure.

##### deleteFailed()

```php
public static function deleteFailed(string $path, ?\Throwable $previous = null): static
```

Create an exception for file deletion failure.

##### createDirectoryFailed()

```php
public static function createDirectoryFailed(string $path, ?\Throwable $previous = null): static
```

Create an exception for directory creation failure.

##### deleteDirectoryFailed()

```php
public static function deleteDirectoryFailed(string $path, ?\Throwable $previous = null): static
```

Create an exception for directory deletion failure.

##### listDirectoryFailed()

```php
public static function listDirectoryFailed(string $path, ?\Throwable $previous = null): static
```

Create an exception for directory listing failure.

##### moveFailed()

```php
public static function moveFailed(string $sourcePath, string $destinationPath, ?\Throwable $previous = null): static
```

Create an exception for file move/rename failure.

##### copyFailed()

```php
public static function copyFailed(string $sourcePath, string $destinationPath, ?\Throwable $previous = null): static
```

Create an exception for file copy failure.

##### fileNotFound()

```php
public static function fileNotFound(string $path, ?\Throwable $previous = null): static
```

Create an exception for file not found.

##### directoryNotFound()

```php
public static function directoryNotFound(string $path, ?\Throwable $previous = null): static
```

Create an exception for directory not found.

##### fileAlreadyExists()

```php
public static function fileAlreadyExists(string $path, ?\Throwable $previous = null): static
```

Create an exception for file already exists.

##### insufficientSpace()

```php
public static function insufficientSpace(
    string $path,
    int $requiredBytes,
    int $availableBytes,
    ?\Throwable $previous = null
): static
```

Create an exception for insufficient disk space.

### CtFileConfigurationException

Exception thrown when ctFile configuration is invalid or missing.

#### Static Factory Methods

##### missingRequiredConfig()

```php
public static function missingRequiredConfig(string $configKey, ?\Throwable $previous = null): static
```

Create an exception for missing required configuration.

##### invalidConfigValue()

```php
public static function invalidConfigValue(
    string $configKey,
    $value,
    string $expectedType,
    ?\Throwable $previous = null
): static
```

Create an exception for invalid configuration value.

##### invalidHost()

```php
public static function invalidHost(string $host, ?\Throwable $previous = null): static
```

Create an exception for invalid host configuration.

##### invalidPort()

```php
public static function invalidPort($port, ?\Throwable $previous = null): static
```

Create an exception for invalid port configuration.

##### invalidTimeout()

```php
public static function invalidTimeout($timeout, ?\Throwable $previous = null): static
```

Create an exception for invalid timeout configuration.

##### invalidCredentials()

```php
public static function invalidCredentials(string $issue, ?\Throwable $previous = null): static
```

Create an exception for invalid credentials configuration.

##### configFileNotFound()

```php
public static function configFileNotFound(string $configPath, ?\Throwable $previous = null): static
```

Create an exception for configuration file not found.

##### configParseError()

```php
public static function configParseError(string $configPath, string $parseError, ?\Throwable $previous = null): static
```

Create an exception for configuration file parsing error.

##### unsupportedConfigOption()

```php
public static function unsupportedConfigOption(
    string $configKey,
    array $supportedOptions = [],
    ?\Throwable $previous = null
): static
```

Create an exception for unsupported configuration option.

##### validationFailed()

```php
public static function validationFailed(array $validationErrors, ?\Throwable $previous = null): static
```

Create an exception for configuration validation failure.

## Configuration Reference

### Complete Configuration Structure

```php
[
    'ctfile' => [
        'host' => 'string|required',           // ctFile server hostname
        'port' => 'integer|default:21',        // ctFile server port
        'username' => 'string|required',       // Authentication username
        'password' => 'string|required',       // Authentication password
        'timeout' => 'integer|default:30',     // Connection timeout in seconds
        'ssl' => 'boolean|default:false',      // Enable SSL/TLS connection
        'passive' => 'boolean|default:true',   // Use passive mode
    ],
    'adapter' => [
        'root_path' => 'string|default:/',           // Root path for operations
        'path_separator' => 'string|default:/',     // Path separator character
        'case_sensitive' => 'boolean|default:true', // Case-sensitive path handling
        'create_directories' => 'boolean|default:true', // Auto-create parent directories
    ],
    'logging' => [
        'enabled' => 'boolean|default:false',        // Enable logging
        'level' => 'string|default:info',           // Log level (debug, info, warning, error)
        'channel' => 'string|default:filesystem-ctfile', // Log channel name
    ],
    'cache' => [
        'enabled' => 'boolean|default:false',       // Enable caching
        'ttl' => 'integer|default:300',            // Cache TTL in seconds
        'driver' => 'string|default:memory',       // Cache driver (memory, redis, etc.)
    ],
]
```

### Configuration Examples

#### Basic Configuration

```php
$config = [
    'ctfile' => [
        'host' => 'ftp.example.com',
        'username' => 'myuser',
        'password' => 'mypassword',
    ]
];
```

#### Secure Configuration with SSL

```php
$config = [
    'ctfile' => [
        'host' => 'secure-ftp.example.com',
        'port' => 990,
        'username' => 'myuser',
        'password' => 'mypassword',
        'ssl' => true,
        'timeout' => 60,
    ]
];
```

#### Configuration with Caching and Logging

```php
$config = [
    'ctfile' => [
        'host' => 'ftp.example.com',
        'username' => 'myuser',
        'password' => 'mypassword',
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 600, // 10 minutes
    ],
    'logging' => [
        'enabled' => true,
        'level' => 'warning',
    ]
];
```

#### Advanced Configuration

```php
$config = [
    'ctfile' => [
        'host' => 'ftp.example.com',
        'port' => 21,
        'username' => 'myuser',
        'password' => 'mypassword',
        'timeout' => 45,
        'ssl' => false,
        'passive' => true,
    ],
    'adapter' => [
        'root_path' => '/uploads',
        'case_sensitive' => false,
        'create_directories' => true,
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 300,
        'driver' => 'redis',
    ],
    'logging' => [
        'enabled' => true,
        'level' => 'info',
        'channel' => 'my-app-ctfile',
    ]
];
```

---

This documentation covers all public classes, methods, and configuration options available in the `yangweijie/filesystem-ctfile` package. For additional examples and tutorials, see the [Usage Examples](usage-examples.md) documentation.