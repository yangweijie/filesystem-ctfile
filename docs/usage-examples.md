# Usage Examples and Tutorials

This document provides comprehensive examples and tutorials for using the `yangweijie/filesystem-ctfile` package. From basic setup to advanced configurations, these examples will help you integrate ctFile functionality into your applications.

## Table of Contents

- [Quick Start](#quick-start)
- [Basic Usage Examples](#basic-usage-examples)
- [Advanced Configuration](#advanced-configuration)
- [Error Handling](#error-handling)
- [Performance Optimization](#performance-optimization)
- [Integration Examples](#integration-examples)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)

## Quick Start

### Installation

```bash
composer require yangweijie/filesystem-ctfile
```

### Basic Setup

```php
<?php

require_once 'vendor/autoload.php';

use YangWeijie\FilesystemCtfile\CtFileAdapter;
use YangWeijie\FilesystemCtfile\CtFileClient;
use League\Flysystem\Filesystem;

// Configure ctFile connection
$config = [
    'host' => 'your-ctfile-server.com',
    'port' => 21,
    'username' => 'your-username',
    'password' => 'your-password',
    'timeout' => 30,
    'ssl' => false,
    'passive' => true
];

// Create client and adapter
$client = new CtFileClient($config);
$adapter = new CtFileAdapter($client);
$filesystem = new Filesystem($adapter);

// Now you can use standard Flysystem operations
echo "Setup complete! Ready to use ctFile with Flysystem.\n";
```

## Basic Usage Examples

### File Operations

#### Writing Files

```php
<?php

// Write a simple text file
$filesystem->write('documents/hello.txt', 'Hello, ctFile!');

// Write with specific configuration
use League\Flysystem\Config;

$config = new Config([
    'visibility' => 'public',
    'directory_visibility' => 'public'
]);

$filesystem->write('public/announcement.txt', 'Important announcement', $config);

// Write from a stream
$stream = fopen('local-file.txt', 'r');
$filesystem->writeStream('remote/uploaded-file.txt', $stream, $config);
fclose($stream);

echo "Files written successfully!\n";
```

#### Reading Files

```php
<?php

// Read file contents as string
$contents = $filesystem->read('documents/hello.txt');
echo "File contents: " . $contents . "\n";

// Read file as stream (memory efficient for large files)
$stream = $filesystem->readStream('documents/large-file.txt');
while (!feof($stream)) {
    echo fread($stream, 8192);
}
fclose($stream);

// Check if file exists before reading
if ($filesystem->fileExists('documents/config.json')) {
    $config = json_decode($filesystem->read('documents/config.json'), true);
    print_r($config);
}
```

#### File Metadata

```php
<?php

$filePath = 'documents/report.pdf';

if ($filesystem->fileExists($filePath)) {
    // Get file size
    $size = $filesystem->fileSize($filePath);
    echo "File size: " . $size . " bytes\n";
    
    // Get last modified time
    $lastModified = $filesystem->lastModified($filePath);
    echo "Last modified: " . date('Y-m-d H:i:s', $lastModified) . "\n";
    
    // Get MIME type
    $mimeType = $filesystem->mimeType($filePath);
    echo "MIME type: " . $mimeType . "\n";
    
    // Get visibility
    $visibility = $filesystem->visibility($filePath);
    echo "Visibility: " . $visibility . "\n";
}
```

#### File Management

```php
<?php

// Copy a file
$filesystem->copy('documents/original.txt', 'backup/copy.txt');

// Move/rename a file
$filesystem->move('temp/draft.txt', 'documents/final.txt');

// Delete a file
$filesystem->delete('temp/old-file.txt');

// Set file visibility
use League\Flysystem\Visibility;

$filesystem->setVisibility('documents/private.txt', Visibility::PRIVATE);
$filesystem->setVisibility('public/shared.txt', Visibility::PUBLIC);

echo "File operations completed!\n";
```

### Directory Operations

#### Working with Directories

```php
<?php

// Create directories
$filesystem->createDirectory('projects/new-project');
$filesystem->createDirectory('uploads/2024/january');

// Check if directory exists
if (!$filesystem->directoryExists('archives')) {
    $filesystem->createDirectory('archives');
    echo "Archives directory created.\n";
}

// List directory contents
$contents = $filesystem->listContents('documents');
foreach ($contents as $item) {
    $type = $item->isFile() ? 'File' : 'Directory';
    echo "{$type}: {$item->path()}\n";
    
    if ($item->isFile()) {
        echo "  Size: " . ($item->fileSize() ?? 'unknown') . " bytes\n";
        echo "  Modified: " . ($item->lastModified() ? date('Y-m-d H:i:s', $item->lastModified()) : 'unknown') . "\n";
    }
}

// Recursive directory listing
$allContents = $filesystem->listContents('projects', true);
foreach ($allContents as $item) {
    echo ($item->isFile() ? 'F' : 'D') . ": {$item->path()}\n";
}

// Delete directory
$filesystem->deleteDirectory('temp/old-project');
```

## Advanced Configuration

### Configuration with Caching

```php
<?php

use YangWeijie\FilesystemCtfile\CacheManager;
use YangWeijie\FilesystemCtfile\Cache\MemoryCache;

// Set up caching for better performance
$cache = new MemoryCache();
$cacheConfig = [
    'enabled' => true,
    'ttl' => 600, // 10 minutes
    'key_prefix' => 'myapp_ctfile:'
];

$cacheManager = new CacheManager($cache, $cacheConfig);

// Create adapter with caching
$adapter = new CtFileAdapter($client, [], $cacheManager);
$filesystem = new Filesystem($adapter);

// Operations will now be cached
$contents = $filesystem->listContents('documents'); // Cached for 10 minutes
$fileSize = $filesystem->fileSize('large-file.txt'); // Cached result
```

### Configuration with Retry Logic

```php
<?php

use YangWeijie\FilesystemCtfile\RetryHandler;
use Psr\Log\NullLogger;

// Configure retry handler for unreliable connections
$retryHandler = new RetryHandler(
    maxRetries: 5,
    baseDelay: 1000,        // 1 second base delay
    backoffMultiplier: 2.0, // Exponential backoff
    maxDelay: 30000,        // 30 seconds max delay
    retryableExceptions: [
        \League\Flysystem\UnableToReadFile::class,
        \League\Flysystem\UnableToWriteFile::class,
    ],
    logger: new NullLogger()
);

$adapter = new CtFileAdapter($client, [], null, $retryHandler);
$filesystem = new Filesystem($adapter);

// Operations will automatically retry on failure
try {
    $filesystem->write('unreliable/test.txt', 'This will retry on failure');
    echo "File written with retry protection!\n";
} catch (\Exception $e) {
    echo "Failed after all retries: " . $e->getMessage() . "\n";
}
```

### Complete Advanced Configuration

```php
<?php

use YangWeijie\FilesystemCtfile\ConfigurationManager;
use YangWeijie\FilesystemCtfile\ErrorHandler;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create a comprehensive configuration
$config = [
    'ctfile' => [
        'host' => 'secure-ftp.example.com',
        'port' => 990,
        'username' => 'secure_user',
        'password' => 'secure_password',
        'timeout' => 60,
        'ssl' => true,
        'passive' => true,
    ],
    'adapter' => [
        'root_path' => '/app-data',
        'case_sensitive' => false,
        'create_directories' => true,
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 300,
        'driver' => 'memory',
    ],
    'logging' => [
        'enabled' => true,
        'level' => 'info',
        'channel' => 'ctfile-app',
    ]
];

// Set up logging
$logger = new Logger('ctfile');
$logger->pushHandler(new StreamHandler('logs/ctfile.log', Logger::INFO));

// Create configuration manager
$configManager = new ConfigurationManager($config);
$configManager->validate(); // Throws exception if invalid

// Create error handler with logging
$errorHandler = new ErrorHandler($logger);

// Create components
$client = new CtFileClient($configManager->get('ctfile'));
$cacheManager = new CacheManager(new MemoryCache(), $configManager->get('cache'));
$retryHandler = new RetryHandler(3, 1000, 2.0, 30000, [], $logger);

// Create adapter with all features
$adapter = new CtFileAdapter(
    $client,
    $configManager->get('adapter'),
    $cacheManager,
    $retryHandler
);

$filesystem = new Filesystem($adapter);

echo "Advanced configuration complete!\n";
```

## Error Handling

### Basic Error Handling

```php
<?php

use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\FilesystemException;

try {
    $contents = $filesystem->read('nonexistent-file.txt');
} catch (UnableToReadFile $e) {
    echo "Could not read file: " . $e->getMessage() . "\n";
    echo "Location: " . $e->location() . "\n";
} catch (FilesystemException $e) {
    echo "Filesystem error: " . $e->getMessage() . "\n";
}

try {
    $filesystem->write('/readonly/file.txt', 'content');
} catch (UnableToWriteFile $e) {
    echo "Could not write file: " . $e->getMessage() . "\n";
    // Handle write failure (maybe try alternative location)
    $filesystem->write('temp/fallback.txt', 'content');
}
```

### Advanced Error Handling with ctFile-Specific Exceptions

```php
<?php

use YangWeijie\FilesystemCtfile\Exceptions\CtFileConnectionException;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileAuthenticationException;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileOperationException;

try {
    $client = new CtFileClient($config);
    $client->connect();
    
    $adapter = new CtFileAdapter($client);
    $filesystem = new Filesystem($adapter);
    
    $filesystem->write('test.txt', 'Hello World');
    
} catch (CtFileConnectionException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
    echo "Host: " . $e->getContext()['host'] ?? 'unknown' . "\n";
    echo "Port: " . $e->getContext()['port'] ?? 'unknown' . "\n";
    
    // Maybe try backup server
    $backupConfig = $config;
    $backupConfig['host'] = 'backup-server.example.com';
    // ... retry with backup
    
} catch (CtFileAuthenticationException $e) {
    echo "Authentication failed: " . $e->getMessage() . "\n";
    echo "Username: " . $e->getContext()['username'] ?? 'unknown' . "\n";
    
    // Maybe prompt for new credentials
    // ... handle authentication failure
    
} catch (CtFileOperationException $e) {
    echo "Operation failed: " . $e->getMessage() . "\n";
    echo "Operation: " . $e->getOperation() . "\n";
    echo "Path: " . $e->getPath() . "\n";
    
    // Handle specific operation failures
    if ($e->getOperation() === 'upload') {
        echo "Upload failed, trying alternative method...\n";
        // ... implement fallback upload strategy
    }
}
```

### Graceful Degradation

```php
<?php

class RobustFileManager
{
    private Filesystem $filesystem;
    private array $fallbackPaths;
    
    public function __construct(Filesystem $filesystem, array $fallbackPaths = [])
    {
        $this->filesystem = $filesystem;
        $this->fallbackPaths = $fallbackPaths ?: ['temp/', 'backup/', 'local/'];
    }
    
    public function safeWrite(string $path, string $contents): bool
    {
        // Try primary path
        try {
            $this->filesystem->write($path, $contents);
            return true;
        } catch (FilesystemException $e) {
            echo "Primary write failed: " . $e->getMessage() . "\n";
        }
        
        // Try fallback paths
        foreach ($this->fallbackPaths as $fallbackPrefix) {
            $fallbackPath = $fallbackPrefix . basename($path);
            try {
                $this->filesystem->write($fallbackPath, $contents);
                echo "Wrote to fallback location: {$fallbackPath}\n";
                return true;
            } catch (FilesystemException $e) {
                echo "Fallback write failed for {$fallbackPath}: " . $e->getMessage() . "\n";
            }
        }
        
        // All attempts failed
        echo "All write attempts failed for: {$path}\n";
        return false;
    }
    
    public function safeRead(string $path): ?string
    {
        $attempts = array_merge([$path], array_map(fn($prefix) => $prefix . basename($path), $this->fallbackPaths));
        
        foreach ($attempts as $attemptPath) {
            try {
                return $this->filesystem->read($attemptPath);
            } catch (FilesystemException $e) {
                echo "Read attempt failed for {$attemptPath}: " . $e->getMessage() . "\n";
            }
        }
        
        return null;
    }
}

// Usage
$robustManager = new RobustFileManager($filesystem, ['backup/', 'temp/']);
$success = $robustManager->safeWrite('important/data.txt', 'Critical data');
$data = $robustManager->safeRead('important/data.txt');
```

## Performance Optimization

### Batch Operations

```php
<?php

class BatchFileOperations
{
    private Filesystem $filesystem;
    
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }
    
    public function batchUpload(array $files): array
    {
        $results = [];
        $startTime = microtime(true);
        
        foreach ($files as $localPath => $remotePath) {
            try {
                $stream = fopen($localPath, 'r');
                $this->filesystem->writeStream($remotePath, $stream);
                fclose($stream);
                
                $results[$remotePath] = ['status' => 'success', 'size' => filesize($localPath)];
            } catch (FilesystemException $e) {
                $results[$remotePath] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }
        
        $duration = microtime(true) - $startTime;
        $results['_summary'] = [
            'total_files' => count($files),
            'successful' => count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'success')),
            'duration' => round($duration, 2) . 's'
        ];
        
        return $results;
    }
    
    public function batchDelete(array $paths): array
    {
        $results = [];
        
        foreach ($paths as $path) {
            try {
                $this->filesystem->delete($path);
                $results[$path] = 'deleted';
            } catch (FilesystemException $e) {
                $results[$path] = 'error: ' . $e->getMessage();
            }
        }
        
        return $results;
    }
}

// Usage
$batchOps = new BatchFileOperations($filesystem);

$uploadFiles = [
    'local/file1.txt' => 'remote/file1.txt',
    'local/file2.txt' => 'remote/file2.txt',
    'local/file3.txt' => 'remote/file3.txt',
];

$uploadResults = $batchOps->batchUpload($uploadFiles);
print_r($uploadResults);
```

### Memory-Efficient Large File Handling

```php
<?php

class LargeFileHandler
{
    private Filesystem $filesystem;
    private int $chunkSize;
    
    public function __construct(Filesystem $filesystem, int $chunkSize = 8192)
    {
        $this->filesystem = $filesystem;
        $this->chunkSize = $chunkSize;
    }
    
    public function uploadLargeFile(string $localPath, string $remotePath): bool
    {
        if (!file_exists($localPath)) {
            throw new \InvalidArgumentException("Local file does not exist: {$localPath}");
        }
        
        $fileSize = filesize($localPath);
        echo "Uploading large file ({$fileSize} bytes): {$localPath} -> {$remotePath}\n";
        
        $localStream = fopen($localPath, 'rb');
        if (!$localStream) {
            throw new \RuntimeException("Cannot open local file: {$localPath}");
        }
        
        try {
            $this->filesystem->writeStream($remotePath, $localStream);
            echo "Upload completed successfully!\n";
            return true;
        } catch (FilesystemException $e) {
            echo "Upload failed: " . $e->getMessage() . "\n";
            return false;
        } finally {
            fclose($localStream);
        }
    }
    
    public function downloadLargeFile(string $remotePath, string $localPath): bool
    {
        echo "Downloading large file: {$remotePath} -> {$localPath}\n";
        
        try {
            $remoteStream = $this->filesystem->readStream($remotePath);
            $localStream = fopen($localPath, 'wb');
            
            if (!$localStream) {
                throw new \RuntimeException("Cannot create local file: {$localPath}");
            }
            
            $bytesWritten = 0;
            while (!feof($remoteStream)) {
                $chunk = fread($remoteStream, $this->chunkSize);
                $written = fwrite($localStream, $chunk);
                $bytesWritten += $written;
                
                // Progress indicator
                if ($bytesWritten % (1024 * 1024) === 0) { // Every MB
                    echo "Downloaded: " . round($bytesWritten / 1024 / 1024, 1) . " MB\n";
                }
            }
            
            fclose($remoteStream);
            fclose($localStream);
            
            echo "Download completed! Total: {$bytesWritten} bytes\n";
            return true;
            
        } catch (FilesystemException $e) {
            echo "Download failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

// Usage
$largeFileHandler = new LargeFileHandler($filesystem, 64 * 1024); // 64KB chunks

$largeFileHandler->uploadLargeFile('local/huge-database.sql', 'backups/huge-database.sql');
$largeFileHandler->downloadLargeFile('backups/huge-database.sql', 'restored/huge-database.sql');
```

## Integration Examples

### Laravel Integration

```php
<?php

// config/filesystems.php
return [
    'disks' => [
        'ctfile' => [
            'driver' => 'ctfile',
            'host' => env('CTFILE_HOST'),
            'port' => env('CTFILE_PORT', 21),
            'username' => env('CTFILE_USERNAME'),
            'password' => env('CTFILE_PASSWORD'),
            'ssl' => env('CTFILE_SSL', false),
            'timeout' => env('CTFILE_TIMEOUT', 30),
            'root' => env('CTFILE_ROOT', '/'),
        ],
    ],
];

// app/Providers/AppServiceProvider.php
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use YangWeijie\FilesystemCtfile\CtFileAdapter;
use YangWeijie\FilesystemCtfile\CtFileClient;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Storage::extend('ctfile', function ($app, $config) {
            $client = new CtFileClient([
                'host' => $config['host'],
                'port' => $config['port'],
                'username' => $config['username'],
                'password' => $config['password'],
                'ssl' => $config['ssl'],
                'timeout' => $config['timeout'],
            ]);
            
            $adapter = new CtFileAdapter($client, [
                'root_path' => $config['root'],
            ]);
            
            return new Filesystem($adapter);
        });
    }
}

// Usage in Laravel controllers
class FileController extends Controller
{
    public function upload(Request $request)
    {
        $file = $request->file('document');
        $path = 'uploads/' . $file->getClientOriginalName();
        
        Storage::disk('ctfile')->putFileAs('uploads', $file, $file->getClientOriginalName());
        
        return response()->json(['message' => 'File uploaded successfully', 'path' => $path]);
    }
    
    public function download(string $filename)
    {
        $path = 'uploads/' . $filename;
        
        if (!Storage::disk('ctfile')->exists($path)) {
            abort(404);
        }
        
        $contents = Storage::disk('ctfile')->get($path);
        $mimeType = Storage::disk('ctfile')->mimeType($path);
        
        return response($contents)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
```

### Symfony Integration

```php
<?php

// config/services.yaml
services:
    ctfile.client:
        class: YangWeijie\FilesystemCtfile\CtFileClient
        arguments:
            - host: '%env(CTFILE_HOST)%'
              port: '%env(int:CTFILE_PORT)%'
              username: '%env(CTFILE_USERNAME)%'
              password: '%env(CTFILE_PASSWORD)%'
              ssl: '%env(bool:CTFILE_SSL)%'
    
    ctfile.adapter:
        class: YangWeijie\FilesystemCtfile\CtFileAdapter
        arguments:
            - '@ctfile.client'
    
    ctfile.filesystem:
        class: League\Flysystem\Filesystem
        arguments:
            - '@ctfile.adapter'

// src/Service/FileService.php
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileService
{
    private Filesystem $filesystem;
    
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }
    
    public function uploadFile(UploadedFile $file, string $directory = 'uploads'): string
    {
        $filename = uniqid() . '.' . $file->getClientOriginalExtension();
        $path = $directory . '/' . $filename;
        
        $stream = fopen($file->getPathname(), 'r');
        $this->filesystem->writeStream($path, $stream);
        fclose($stream);
        
        return $path;
    }
    
    public function getFileContents(string $path): string
    {
        return $this->filesystem->read($path);
    }
    
    public function deleteFile(string $path): bool
    {
        try {
            $this->filesystem->delete($path);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

### Standalone Application Example

```php
<?php

class DocumentManager
{
    private Filesystem $filesystem;
    private string $documentsPath;
    
    public function __construct(Filesystem $filesystem, string $documentsPath = 'documents')
    {
        $this->filesystem = $filesystem;
        $this->documentsPath = rtrim($documentsPath, '/');
    }
    
    public function createDocument(string $name, string $content, array $metadata = []): string
    {
        $path = $this->documentsPath . '/' . $name;
        
        // Create document with metadata
        $documentData = [
            'content' => $content,
            'metadata' => array_merge([
                'created_at' => date('c'),
                'version' => '1.0',
            ], $metadata)
        ];
        
        $this->filesystem->write($path, json_encode($documentData, JSON_PRETTY_PRINT));
        
        return $path;
    }
    
    public function getDocument(string $name): ?array
    {
        $path = $this->documentsPath . '/' . $name;
        
        if (!$this->filesystem->fileExists($path)) {
            return null;
        }
        
        $data = json_decode($this->filesystem->read($path), true);
        return $data;
    }
    
    public function updateDocument(string $name, string $content, array $metadata = []): bool
    {
        $document = $this->getDocument($name);
        if (!$document) {
            return false;
        }
        
        $document['content'] = $content;
        $document['metadata'] = array_merge($document['metadata'], $metadata);
        $document['metadata']['updated_at'] = date('c');
        
        $path = $this->documentsPath . '/' . $name;
        $this->filesystem->write($path, json_encode($document, JSON_PRETTY_PRINT));
        
        return true;
    }
    
    public function listDocuments(): array
    {
        $contents = $this->filesystem->listContents($this->documentsPath);
        $documents = [];
        
        foreach ($contents as $item) {
            if ($item->isFile()) {
                $documents[] = [
                    'name' => basename($item->path()),
                    'size' => $item->fileSize(),
                    'modified' => $item->lastModified() ? date('c', $item->lastModified()) : null,
                ];
            }
        }
        
        return $documents;
    }
    
    public function deleteDocument(string $name): bool
    {
        $path = $this->documentsPath . '/' . $name;
        
        try {
            $this->filesystem->delete($path);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

// Usage
$documentManager = new DocumentManager($filesystem);

// Create a document
$path = $documentManager->createDocument('report.json', 'Monthly sales report', [
    'author' => 'John Doe',
    'department' => 'Sales'
]);

// Update the document
$documentManager->updateDocument('report.json', 'Updated monthly sales report', [
    'reviewer' => 'Jane Smith'
]);

// List all documents
$documents = $documentManager->listDocuments();
foreach ($documents as $doc) {
    echo "Document: {$doc['name']} ({$doc['size']} bytes)\n";
}

// Get document content
$document = $documentManager->getDocument('report.json');
if ($document) {
    echo "Content: " . $document['content'] . "\n";
    echo "Author: " . $document['metadata']['author'] . "\n";
}
```

## Best Practices

### 1. Configuration Management

```php
<?php

// Use environment variables for sensitive data
$config = [
    'ctfile' => [
        'host' => $_ENV['CTFILE_HOST'] ?? throw new \RuntimeException('CTFILE_HOST not set'),
        'port' => (int)($_ENV['CTFILE_PORT'] ?? 21),
        'username' => $_ENV['CTFILE_USERNAME'] ?? throw new \RuntimeException('CTFILE_USERNAME not set'),
        'password' => $_ENV['CTFILE_PASSWORD'] ?? throw new \RuntimeException('CTFILE_PASSWORD not set'),
        'ssl' => filter_var($_ENV['CTFILE_SSL'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'timeout' => (int)($_ENV['CTFILE_TIMEOUT'] ?? 30),
    ]
];

// Validate configuration before use
$configManager = new ConfigurationManager($config);
try {
    $configManager->validate();
} catch (CtFileConfigurationException $e) {
    throw new \RuntimeException('Invalid ctFile configuration: ' . $e->getMessage());
}
```

### 2. Connection Management

```php
<?php

class CtFileConnectionManager
{
    private ?CtFileClient $client = null;
    private array $config;
    private int $maxRetries = 3;
    
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    public function getClient(): CtFileClient
    {
        if ($this->client === null || !$this->client->isConnected()) {
            $this->client = $this->createClient();
        }
        
        return $this->client;
    }
    
    private function createClient(): CtFileClient
    {
        $attempts = 0;
        
        while ($attempts < $this->maxRetries) {
            try {
                $client = new CtFileClient($this->config);
                $client->connect();
                return $client;
            } catch (CtFileConnectionException $e) {
                $attempts++;
                if ($attempts >= $this->maxRetries) {
                    throw $e;
                }
                sleep(pow(2, $attempts)); // Exponential backoff
            }
        }
        
        throw new \RuntimeException('Failed to create ctFile client after all retries');
    }
    
    public function disconnect(): void
    {
        if ($this->client) {
            $this->client->disconnect();
            $this->client = null;
        }
    }
    
    public function __destruct()
    {
        $this->disconnect();
    }
}
```

### 3. Error Logging and Monitoring

```php
<?php

use Psr\Log\LoggerInterface;

class MonitoredFilesystem
{
    private Filesystem $filesystem;
    private LoggerInterface $logger;
    private array $metrics = [];
    
    public function __construct(Filesystem $filesystem, LoggerInterface $logger)
    {
        $this->filesystem = $filesystem;
        $this->logger = $logger;
    }
    
    public function write(string $path, string $contents): void
    {
        $startTime = microtime(true);
        
        try {
            $this->filesystem->write($path, $contents);
            $duration = microtime(true) - $startTime;
            
            $this->logger->info('File write successful', [
                'path' => $path,
                'size' => strlen($contents),
                'duration' => $duration
            ]);
            
            $this->recordMetric('write_success', $duration);
            
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            
            $this->logger->error('File write failed', [
                'path' => $path,
                'error' => $e->getMessage(),
                'duration' => $duration
            ]);
            
            $this->recordMetric('write_failure', $duration);
            throw $e;
        }
    }
    
    public function read(string $path): string
    {
        $startTime = microtime(true);
        
        try {
            $contents = $this->filesystem->read($path);
            $duration = microtime(true) - $startTime;
            
            $this->logger->info('File read successful', [
                'path' => $path,
                'size' => strlen($contents),
                'duration' => $duration
            ]);
            
            $this->recordMetric('read_success', $duration);
            return $contents;
            
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            
            $this->logger->error('File read failed', [
                'path' => $path,
                'error' => $e->getMessage(),
                'duration' => $duration
            ]);
            
            $this->recordMetric('read_failure', $duration);
            throw $e;
        }
    }
    
    private function recordMetric(string $operation, float $duration): void
    {
        if (!isset($this->metrics[$operation])) {
            $this->metrics[$operation] = ['count' => 0, 'total_duration' => 0];
        }
        
        $this->metrics[$operation]['count']++;
        $this->metrics[$operation]['total_duration'] += $duration;
    }
    
    public function getMetrics(): array
    {
        $result = [];
        
        foreach ($this->metrics as $operation => $data) {
            $result[$operation] = [
                'count' => $data['count'],
                'average_duration' => $data['total_duration'] / $data['count'],
                'total_duration' => $data['total_duration']
            ];
        }
        
        return $result;
    }
}
```

### 4. Path Validation and Security

```php
<?php

use YangWeijie\FilesystemCtfile\Utilities\PathNormalizer;

class SecureFileOperations
{
    private Filesystem $filesystem;
    private array $allowedExtensions;
    private array $blockedPaths;
    private int $maxFileSize;
    
    public function __construct(
        Filesystem $filesystem,
        array $allowedExtensions = [],
        array $blockedPaths = [],
        int $maxFileSize = 10 * 1024 * 1024 // 10MB
    ) {
        $this->filesystem = $filesystem;
        $this->allowedExtensions = array_map('strtolower', $allowedExtensions);
        $this->blockedPaths = $blockedPaths;
        $this->maxFileSize = $maxFileSize;
    }
    
    public function safeWrite(string $path, string $contents): void
    {
        $this->validatePath($path);
        $this->validateContent($contents);
        
        $normalizedPath = PathNormalizer::normalize($path);
        $this->filesystem->write($normalizedPath, $contents);
    }
    
    public function safeRead(string $path): string
    {
        $this->validatePath($path);
        
        $normalizedPath = PathNormalizer::normalize($path);
        return $this->filesystem->read($normalizedPath);
    }
    
    private function validatePath(string $path): void
    {
        // Normalize and validate path
        if (!PathNormalizer::validate($path)) {
            throw new \InvalidArgumentException("Invalid path: {$path}");
        }
        
        $normalizedPath = PathNormalizer::normalize($path);
        
        // Check for blocked paths
        foreach ($this->blockedPaths as $blockedPath) {
            if (str_starts_with($normalizedPath, $blockedPath)) {
                throw new \InvalidArgumentException("Access denied to path: {$path}");
            }
        }
        
        // Check file extension if restrictions are set
        if (!empty($this->allowedExtensions)) {
            $extension = strtolower(pathinfo($normalizedPath, PATHINFO_EXTENSION));
            if (!in_array($extension, $this->allowedExtensions, true)) {
                throw new \InvalidArgumentException("File extension not allowed: {$extension}");
            }
        }
    }
    
    private function validateContent(string $contents): void
    {
        if (strlen($contents) > $this->maxFileSize) {
            throw new \InvalidArgumentException("File size exceeds maximum allowed size");
        }
        
        // Check for malicious content patterns
        $maliciousPatterns = [
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
        ];
        
        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $contents)) {
                throw new \InvalidArgumentException("Potentially malicious content detected");
            }
        }
    }
}

// Usage
$secureOps = new SecureFileOperations(
    $filesystem,
    ['txt', 'json', 'csv', 'pdf'], // Allowed extensions
    ['/system/', '/config/', '/.env'], // Blocked paths
    5 * 1024 * 1024 // 5MB max file size
);

try {
    $secureOps->safeWrite('uploads/document.txt', 'Safe content');
    $content = $secureOps->safeRead('uploads/document.txt');
} catch (\InvalidArgumentException $e) {
    echo "Security validation failed: " . $e->getMessage() . "\n";
}
```

## Troubleshooting

### Common Issues and Solutions

#### 1. Connection Issues

**Problem**: Connection timeouts or failures

```php
<?php

// Diagnostic function
function diagnoseConnection(array $config): void
{
    echo "Diagnosing ctFile connection...\n";
    
    // Test basic connectivity
    $host = $config['host'];
    $port = $config['port'];
    
    $socket = @fsockopen($host, $port, $errno, $errstr, 10);
    if (!$socket) {
        echo "❌ Cannot connect to {$host}:{$port} - {$errstr} ({$errno})\n";
        echo "Solutions:\n";
        echo "- Check if the host and port are correct\n";
        echo "- Verify firewall settings\n";
        echo "- Check if the ctFile server is running\n";
        return;
    }
    
    echo "✅ Basic connectivity to {$host}:{$port} successful\n";
    fclose($socket);
    
    // Test ctFile client connection
    try {
        $client = new CtFileClient($config);
        $client->connect();
        echo "✅ ctFile client connection successful\n";
        
        // Test basic operation
        $client->listFiles('/');
        echo "✅ Basic ctFile operations working\n";
        
        $client->disconnect();
    } catch (CtFileConnectionException $e) {
        echo "❌ ctFile connection failed: " . $e->getMessage() . "\n";
        echo "Solutions:\n";
        echo "- Verify username and password\n";
        echo "- Check SSL/TLS settings\n";
        echo "- Try passive/active mode toggle\n";
    } catch (CtFileAuthenticationException $e) {
        echo "❌ Authentication failed: " . $e->getMessage() . "\n";
        echo "Solutions:\n";
        echo "- Verify credentials\n";
        echo "- Check account status\n";
        echo "- Verify permissions\n";
    }
}

// Usage
diagnoseConnection($config);
```

#### 2. Performance Issues

**Problem**: Slow file operations

```php
<?php

class PerformanceTuner
{
    public static function optimizeForLargeFiles(CtFileAdapter $adapter): void
    {
        // Enable caching for metadata
        $cache = new MemoryCache();
        $cacheManager = new CacheManager($cache, [
            'enabled' => true,
            'ttl' => 600, // 10 minutes
        ]);
        $adapter->setCacheManager($cacheManager);
        
        // Configure retry with shorter delays for large files
        $retryHandler = new RetryHandler(
            maxRetries: 2,
            baseDelay: 500,
            backoffMultiplier: 1.5
        );
        $adapter->setRetryHandler($retryHandler);
        
        echo "Adapter optimized for large file operations\n";
    }
    
    public static function optimizeForManySmallFiles(CtFileAdapter $adapter): void
    {
        // Enable aggressive caching
        $cache = new MemoryCache();
        $cacheManager = new CacheManager($cache, [
            'enabled' => true,
            'ttl' => 300, // 5 minutes
        ]);
        $adapter->setCacheManager($cacheManager);
        
        // More retries for small files
        $retryHandler = new RetryHandler(
            maxRetries: 5,
            baseDelay: 200,
            backoffMultiplier: 1.2
        );
        $adapter->setRetryHandler($retryHandler);
        
        echo "Adapter optimized for many small file operations\n";
    }
    
    public static function benchmarkOperations(Filesystem $filesystem): array
    {
        $results = [];
        
        // Benchmark write operation
        $testContent = str_repeat('test data ', 1000);
        $startTime = microtime(true);
        $filesystem->write('benchmark/write-test.txt', $testContent);
        $results['write'] = microtime(true) - $startTime;
        
        // Benchmark read operation
        $startTime = microtime(true);
        $filesystem->read('benchmark/write-test.txt');
        $results['read'] = microtime(true) - $startTime;
        
        // Benchmark list operation
        $startTime = microtime(true);
        iterator_to_array($filesystem->listContents('benchmark'));
        $results['list'] = microtime(true) - $startTime;
        
        // Cleanup
        $filesystem->delete('benchmark/write-test.txt');
        
        return $results;
    }
}

// Usage
$results = PerformanceTuner::benchmarkOperations($filesystem);
foreach ($results as $operation => $duration) {
    echo "{$operation}: " . round($duration * 1000, 2) . "ms\n";
}

if ($results['write'] > 1.0) {
    echo "Write operations are slow, consider optimizing for large files\n";
    PerformanceTuner::optimizeForLargeFiles($adapter);
}
```

#### 3. Memory Issues

**Problem**: High memory usage with large files

```php
<?php

class MemoryEfficientOperations
{
    private Filesystem $filesystem;
    
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }
    
    public function processLargeFile(string $remotePath, callable $processor): void
    {
        echo "Processing large file with minimal memory usage...\n";
        $initialMemory = memory_get_usage(true);
        
        $stream = $this->filesystem->readStream($remotePath);
        $chunkSize = 8192; // 8KB chunks
        $processedBytes = 0;
        
        while (!feof($stream)) {
            $chunk = fread($stream, $chunkSize);
            $processor($chunk);
            $processedBytes += strlen($chunk);
            
            // Memory usage monitoring
            $currentMemory = memory_get_usage(true);
            if ($processedBytes % (1024 * 1024) === 0) { // Every MB
                echo "Processed: " . round($processedBytes / 1024 / 1024, 1) . "MB, ";
                echo "Memory: " . round(($currentMemory - $initialMemory) / 1024 / 1024, 1) . "MB\n";
            }
        }
        
        fclose($stream);
        
        $finalMemory = memory_get_usage(true);
        echo "Processing complete. Memory used: " . 
             round(($finalMemory - $initialMemory) / 1024 / 1024, 1) . "MB\n";
    }
    
    public function copyLargeFile(string $sourcePath, string $destPath): void
    {
        echo "Copying large file with streaming...\n";
        
        $sourceStream = $this->filesystem->readStream($sourcePath);
        $this->filesystem->writeStream($destPath, $sourceStream);
        
        echo "Large file copy completed\n";
    }
}

// Usage
$memoryOps = new MemoryEfficientOperations($filesystem);

// Process a large log file line by line
$memoryOps->processLargeFile('logs/huge-log.txt', function($chunk) {
    // Process each chunk (e.g., search for patterns, count lines, etc.)
    $lineCount = substr_count($chunk, "\n");
    static $totalLines = 0;
    $totalLines += $lineCount;
    
    if ($lineCount > 0) {
        echo "Lines in chunk: {$lineCount}, Total: {$totalLines}\n";
    }
});
```

#### 4. Configuration Validation

**Problem**: Configuration errors

```php
<?php

class ConfigurationTroubleshooter
{
    public static function validateAndFix(array $config): array
    {
        $issues = [];
        $fixes = [];
        
        // Check required fields
        $required = ['host', 'username', 'password'];
        foreach ($required as $field) {
            if (empty($config['ctfile'][$field] ?? '')) {
                $issues[] = "Missing required field: ctfile.{$field}";
            }
        }
        
        // Validate port
        $port = $config['ctfile']['port'] ?? 21;
        if (!is_int($port) || $port < 1 || $port > 65535) {
            $issues[] = "Invalid port: {$port}";
            $fixes['ctfile']['port'] = 21;
        }
        
        // Validate timeout
        $timeout = $config['ctfile']['timeout'] ?? 30;
        if (!is_int($timeout) || $timeout < 1) {
            $issues[] = "Invalid timeout: {$timeout}";
            $fixes['ctfile']['timeout'] = 30;
        }
        
        // Validate SSL setting
        if (isset($config['ctfile']['ssl']) && !is_bool($config['ctfile']['ssl'])) {
            $issues[] = "SSL setting must be boolean";
            $fixes['ctfile']['ssl'] = false;
        }
        
        // Apply fixes
        $fixedConfig = array_merge_recursive($config, $fixes);
        
        if (!empty($issues)) {
            echo "Configuration issues found:\n";
            foreach ($issues as $issue) {
                echo "- {$issue}\n";
            }
            
            if (!empty($fixes)) {
                echo "\nApplied automatic fixes:\n";
                foreach ($fixes as $section => $sectionFixes) {
                    foreach ($sectionFixes as $key => $value) {
                        echo "- {$section}.{$key} = " . var_export($value, true) . "\n";
                    }
                }
            }
        } else {
            echo "Configuration validation passed ✅\n";
        }
        
        return $fixedConfig;
    }
    
    public static function testConfiguration(array $config): bool
    {
        try {
            $configManager = new ConfigurationManager($config);
            $configManager->validate();
            
            $client = new CtFileClient($configManager->get('ctfile'));
            $client->connect();
            $client->disconnect();
            
            echo "Configuration test passed ✅\n";
            return true;
            
        } catch (\Exception $e) {
            echo "Configuration test failed ❌: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

// Usage
$config = ConfigurationTroubleshooter::validateAndFix($config);
ConfigurationTroubleshooter::testConfiguration($config);
```

### Debug Mode

```php
<?php

class DebugFilesystem
{
    private Filesystem $filesystem;
    private bool $debugEnabled;
    
    public function __construct(Filesystem $filesystem, bool $debugEnabled = true)
    {
        $this->filesystem = $filesystem;
        $this->debugEnabled = $debugEnabled;
    }
    
    public function write(string $path, string $contents): void
    {
        if ($this->debugEnabled) {
            echo "[DEBUG] Writing to: {$path} (" . strlen($contents) . " bytes)\n";
        }
        
        $startTime = microtime(true);
        $this->filesystem->write($path, $contents);
        $duration = microtime(true) - $startTime;
        
        if ($this->debugEnabled) {
            echo "[DEBUG] Write completed in " . round($duration * 1000, 2) . "ms\n";
        }
    }
    
    public function read(string $path): string
    {
        if ($this->debugEnabled) {
            echo "[DEBUG] Reading from: {$path}\n";
        }
        
        $startTime = microtime(true);
        $contents = $this->filesystem->read($path);
        $duration = microtime(true) - $startTime;
        
        if ($this->debugEnabled) {
            echo "[DEBUG] Read completed in " . round($duration * 1000, 2) . "ms (" . strlen($contents) . " bytes)\n";
        }
        
        return $contents;
    }
    
    public function listContents(string $path): iterable
    {
        if ($this->debugEnabled) {
            echo "[DEBUG] Listing contents of: {$path}\n";
        }
        
        $startTime = microtime(true);
        $contents = $this->filesystem->listContents($path);
        $items = iterator_to_array($contents);
        $duration = microtime(true) - $startTime;
        
        if ($this->debugEnabled) {
            echo "[DEBUG] Listed " . count($items) . " items in " . round($duration * 1000, 2) . "ms\n";
        }
        
        return $items;
    }
}

// Usage
$debugFs = new DebugFilesystem($filesystem, true);
$debugFs->write('debug/test.txt', 'Debug content');
$content = $debugFs->read('debug/test.txt');
$items = $debugFs->listContents('debug');
```

---

This comprehensive guide covers the most common use cases, advanced configurations, and troubleshooting scenarios for the `yangweijie/filesystem-ctfile` package. For additional information, refer to the [API Documentation](api-documentation.md).