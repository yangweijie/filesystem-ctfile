# API Reference

## CTFileConfig

Configuration class for the CTFile adapter.

### Constructor

```php
public function __construct(array $config)
```

Creates a new configuration instance.

**Parameters:**
- `$config` (array): Configuration array

**Throws:**
- `InvalidArgumentException`: If required parameters are missing or invalid

### Methods

#### getSession()
```php
public function getSession(): string
```
Returns the CTFile session token.

#### getAppId()
```php
public function getAppId(): string
```
Returns the CTFile application ID.

#### getApiBaseUrl()
```php
public function getApiBaseUrl(): string
```
Returns the API base URL.

#### getUploadBaseUrl()
```php
public function getUploadBaseUrl(): string
```
Returns the upload base URL.

#### getTimeout()
```php
public function getTimeout(): int
```
Returns the request timeout in seconds.

#### getRetryAttempts()
```php
public function getRetryAttempts(): int
```
Returns the number of retry attempts.

#### getCacheTtl()
```php
public function getCacheTtl(): int
```
Returns the cache TTL in seconds.

#### getStorageType()
```php
public function getStorageType(): string
```
Returns the storage type ('public' or 'private').

#### isPublicStorage()
```php
public function isPublicStorage(): bool
```
Returns true if storage type is public.

#### toArray()
```php
public function toArray(): array
```
Returns all configuration as an array.

## CTFileAdapter

Main adapter class implementing Flysystem's FilesystemAdapter interface.

### Constructor

```php
public function __construct(CTFileConfig $config)
```

Creates a new adapter instance.

**Parameters:**
- `$config` (CTFileConfig): Configuration instance

### File Operations

#### fileExists()
```php
public function fileExists(string $path): bool
```
Check if a file exists.

**Parameters:**
- `$path` (string): File path

**Returns:** `bool` - True if file exists

#### write()
```php
public function write(string $path, string $contents, Config $config): void
```
Write a file.

**Parameters:**
- `$path` (string): File path
- `$contents` (string): File contents
- `$config` (Config): Write configuration

**Throws:**
- `UnableToWriteFile`: If write operation fails

#### writeStream()
```php
public function writeStream(string $path, $contents, Config $config): void
```
Write a file using a stream.

**Parameters:**
- `$path` (string): File path
- `$contents` (resource): Stream resource
- `$config` (Config): Write configuration

**Throws:**
- `UnableToWriteFile`: If write operation fails

#### read()
```php
public function read(string $path): string
```
Read a file.

**Parameters:**
- `$path` (string): File path

**Returns:** `string` - File contents

**Throws:**
- `UnableToReadFile`: If read operation fails

#### readStream()
```php
public function readStream(string $path)
```
Read a file as a stream.

**Parameters:**
- `$path` (string): File path

**Returns:** `resource` - Stream resource

**Throws:**
- `UnableToReadFile`: If read operation fails

#### delete()
```php
public function delete(string $path): void
```
Delete a file.

**Parameters:**
- `$path` (string): File path

**Throws:**
- `UnableToDeleteFile`: If delete operation fails

#### copy()
```php
public function copy(string $source, string $destination, Config $config): void
```
Copy a file.

**Parameters:**
- `$source` (string): Source file path
- `$destination` (string): Destination file path
- `$config` (Config): Copy configuration

**Throws:**
- `UnableToCopyFile`: If copy operation fails

#### move()
```php
public function move(string $source, string $destination, Config $config): void
```
Move a file.

**Parameters:**
- `$source` (string): Source file path
- `$destination` (string): Destination file path
- `$config` (Config): Move configuration

**Throws:**
- `UnableToMoveFile`: If move operation fails

### Directory Operations

#### directoryExists()
```php
public function directoryExists(string $path): bool
```
Check if a directory exists.

**Parameters:**
- `$path` (string): Directory path

**Returns:** `bool` - True if directory exists

#### createDirectory()
```php
public function createDirectory(string $path, Config $config): void
```
Create a directory.

**Parameters:**
- `$path` (string): Directory path
- `$config` (Config): Create configuration

**Throws:**
- `UnableToCreateDirectory`: If create operation fails

#### deleteDirectory()
```php
public function deleteDirectory(string $path): void
```
Delete a directory.

**Parameters:**
- `$path` (string): Directory path

**Throws:**
- `UnableToDeleteDirectory`: If delete operation fails

#### listContents()
```php
public function listContents(string $path, bool $deep): iterable
```
List directory contents.

**Parameters:**
- `$path` (string): Directory path
- `$deep` (bool): Whether to list recursively

**Returns:** `iterable` - Iterator of StorageAttributes

**Throws:**
- `UnableToListContents`: If list operation fails

### Metadata Operations

#### fileSize()
```php
public function fileSize(string $path): FileAttributes
```
Get file size.

**Parameters:**
- `$path` (string): File path

**Returns:** `FileAttributes` - File attributes with size

**Throws:**
- `UnableToRetrieveMetadata`: If operation fails

#### mimeType()
```php
public function mimeType(string $path): FileAttributes
```
Get file MIME type.

**Parameters:**
- `$path` (string): File path

**Returns:** `FileAttributes` - File attributes with MIME type

**Throws:**
- `UnableToRetrieveMetadata`: If operation fails

#### lastModified()
```php
public function lastModified(string $path): FileAttributes
```
Get file last modified time.

**Parameters:**
- `$path` (string): File path

**Returns:** `FileAttributes` - File attributes with timestamp

**Throws:**
- `UnableToRetrieveMetadata`: If operation fails

#### visibility()
```php
public function visibility(string $path): FileAttributes
```
Get file visibility.

**Parameters:**
- `$path` (string): File path

**Returns:** `FileAttributes` - File attributes with visibility

**Throws:**
- `UnableToRetrieveMetadata`: If operation fails

#### setVisibility()
```php
public function setVisibility(string $path, string $visibility): void
```
Set file visibility.

**Parameters:**
- `$path` (string): File path
- `$visibility` (string): Visibility ('public' or 'private')

**Throws:**
- `UnableToSetVisibility`: If operation fails

## CTFileClient

HTTP client for CTFile API communication.

### Constructor

```php
public function __construct(CTFileConfig $config)
```

### Methods

#### getFileList()
```php
public function getFileList(string $folderId, int $page = 1, int $pageSize = 100): array
```
Get list of files in a folder.

#### getFileInfo()
```php
public function getFileInfo(string $fileId): array
```
Get information about a specific file.

#### createFolder()
```php
public function createFolder(string $name, string $parentId = 'd0'): array
```
Create a new folder.

#### deleteFile()
```php
public function deleteFile(string $fileId): array
```
Delete a file.

#### deleteFolder()
```php
public function deleteFolder(string $folderId): array
```
Delete a folder.

#### getUploadUrl()
```php
public function getUploadUrl(): array
```
Get upload URL for file upload.

#### getDownloadUrl()
```php
public function getDownloadUrl(string $fileId): array
```
Get download URL for a file.

## PathMapper

Utility class for mapping between Flysystem paths and CTFile IDs.

### Methods

#### getFileId()
```php
public function getFileId(string $path): string
```
Get CTFile ID for a file path.

#### getDirectoryId()
```php
public function getDirectoryId(string $path): string
```
Get CTFile ID for a directory path.

#### cachePath()
```php
public function cachePath(string $path, string $id): void
```
Cache a path-to-ID mapping.

#### invalidateCache()
```php
public function invalidateCache(string $path): void
```
Invalidate cache for a path.

## Exception Classes

### CTFileException

Base exception class for all CTFile-related errors.

```php
class CTFileException extends Exception
{
    public function getErrorCode(): int
    public function getErrorDetails(): array
}
```

### AuthenticationException

Exception for authentication-related errors.

```php
class AuthenticationException extends CTFileException
{
    public static function invalidSession(string $session): self
    public static function sessionExpired(): self
    public static function insufficientPermissions(string $operation): self
}
```

### NetworkException

Exception for network-related errors.

```php
class NetworkException extends CTFileException
{
    public static function connectionTimeout(string $url, int $timeout): self
    public static function connectionFailed(string $url, string $reason): self
    public static function dnsResolutionFailed(string $hostname): self
}
```

### RateLimitException

Exception for rate limiting errors.

```php
class RateLimitException extends CTFileException
{
    public function getRetryAfter(): int
    
    public static function requestRateLimit(int $retryAfter): self
    public static function uploadRateLimit(int $retryAfter): self
    public static function storageQuotaExceeded(int $used, int $total): self
}
```

## Support Classes

### FileInfo

Utility class for file information processing.

#### Static Methods

```php
public static function fromApiResponse(array $data, string $path): FileAttributes
public static function fromDirectoryResponse(array $data, string $path): DirectoryAttributes
public static function calculateChecksum(string $content): string
public static function determineMimeType(string $path, string $content = ''): string
```

### UploadHelper

Utility class for upload operations.

#### Methods

```php
public function upload(string $path, $content, Config $config): array
public function prepareUploadData($content): array
public function validateUploadParameters(array $params): void
```
