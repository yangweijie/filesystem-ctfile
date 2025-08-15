# Usage Guide

## Quick Start

### Basic Setup

```php
use League\Flysystem\Filesystem;
use Yangweijie\FilesystemCtlife\CTFileAdapter;
use Yangweijie\FilesystemCtlife\CTFileConfig;

// Configure the adapter
$config = new CTFileConfig([
    'session' => 'your-ctfile-session-token',
    'app_id' => 'your-ctfile-app-id',
]);

// Create filesystem instance
$adapter = new CTFileAdapter($config);
$filesystem = new Filesystem($adapter);
```

## File Operations

### Writing Files

#### Write String Content
```php
// Write a simple text file
$filesystem->write('documents/readme.txt', 'Hello, CTFile!');

// Write with specific configuration
use League\Flysystem\Config;

$filesystem->write('data/config.json', json_encode($data), new Config([
    'visibility' => 'private',
]));
```

#### Write from Stream
```php
// Upload from local file
$stream = fopen('/path/to/local/file.pdf', 'r');
$filesystem->writeStream('uploads/document.pdf', $stream);
fclose($stream);

// Upload from memory
$content = "Large content data...";
$stream = fopen('php://memory', 'r+');
fwrite($stream, $content);
rewind($stream);
$filesystem->writeStream('temp/data.txt', $stream);
fclose($stream);
```

### Reading Files

#### Read as String
```php
// Read entire file content
$content = $filesystem->read('documents/readme.txt');
echo $content;

// Check if file exists before reading
if ($filesystem->fileExists('documents/readme.txt')) {
    $content = $filesystem->read('documents/readme.txt');
}
```

#### Read as Stream
```php
// Read large files efficiently
$stream = $filesystem->readStream('uploads/large-file.zip');

// Save to local file
$localFile = fopen('/tmp/downloaded.zip', 'w');
stream_copy_to_stream($stream, $localFile);
fclose($stream);
fclose($localFile);

// Process stream data
$stream = $filesystem->readStream('data/log.txt');
while (!feof($stream)) {
    $line = fgets($stream);
    // Process each line
    echo $line;
}
fclose($stream);
```

### File Management

#### Copy Files
```php
// Copy within CTFile
$filesystem->copy('source/document.pdf', 'backup/document.pdf');

// Copy with error handling
try {
    $filesystem->copy('source.txt', 'destination.txt');
    echo "File copied successfully!";
} catch (UnableToCopyFile $e) {
    echo "Copy failed: " . $e->getMessage();
}
```

#### Move Files
```php
// Move/rename files
$filesystem->move('old-name.txt', 'new-name.txt');
$filesystem->move('temp/file.txt', 'permanent/file.txt');
```

#### Delete Files
```php
// Delete single file
$filesystem->delete('unwanted-file.txt');

// Delete multiple files
$filesToDelete = ['temp1.txt', 'temp2.txt', 'temp3.txt'];
foreach ($filesToDelete as $file) {
    if ($filesystem->fileExists($file)) {
        $filesystem->delete($file);
    }
}
```

## Directory Operations

### Creating Directories
```php
// Create single directory
$filesystem->createDirectory('new-folder');

// Create nested directories
$filesystem->createDirectory('projects/2024/january');

// Create with specific configuration
$filesystem->createDirectory('private-docs', new Config([
    'visibility' => 'private',
]));
```

### Listing Directory Contents
```php
// List files in directory (non-recursive)
$contents = $filesystem->listContents('documents', false);

foreach ($contents as $item) {
    if ($item->isFile()) {
        echo "File: " . $item->path() . " (" . $item->fileSize() . " bytes)\n";
    } else {
        echo "Directory: " . $item->path() . "\n";
    }
}

// List all files recursively
$allFiles = $filesystem->listContents('/', true);
foreach ($allFiles as $item) {
    if ($item->isFile()) {
        echo $item->path() . "\n";
    }
}
```

### Directory Management
```php
// Check if directory exists
if ($filesystem->directoryExists('uploads')) {
    echo "Uploads directory exists!";
}

// Delete empty directory
$filesystem->deleteDirectory('empty-folder');

// Delete directory with contents (be careful!)
$contents = $filesystem->listContents('folder-to-delete', true);
foreach ($contents as $item) {
    if ($item->isFile()) {
        $filesystem->delete($item->path());
    }
}
$filesystem->deleteDirectory('folder-to-delete');
```

## File Metadata

### Getting File Information
```php
// Get file size
$size = $filesystem->fileSize('document.pdf');
echo "File size: " . number_format($size) . " bytes";

// Get MIME type
$mimeType = $filesystem->mimeType('image.jpg');
echo "MIME type: " . $mimeType;

// Get last modified time
$timestamp = $filesystem->lastModified('data.json');
echo "Last modified: " . date('Y-m-d H:i:s', $timestamp);

// Get visibility
$visibility = $filesystem->visibility('secret.txt');
echo "Visibility: " . $visibility; // 'public' or 'private'
```

### Setting File Visibility
```php
// Make file public
$filesystem->setVisibility('document.pdf', 'public');

// Make file private
$filesystem->setVisibility('secret.txt', 'private');
```

## Advanced Usage

### Batch Operations
```php
// Batch upload multiple files
$files = [
    'file1.txt' => 'Content 1',
    'file2.txt' => 'Content 2',
    'file3.txt' => 'Content 3',
];

foreach ($files as $path => $content) {
    try {
        $filesystem->write($path, $content);
        echo "✓ Uploaded: $path\n";
    } catch (Exception $e) {
        echo "✗ Failed: $path - " . $e->getMessage() . "\n";
    }
}
```

### Working with Large Files
```php
// Upload large file with progress tracking
function uploadLargeFile($filesystem, $localPath, $remotePath) {
    $fileSize = filesize($localPath);
    $stream = fopen($localPath, 'r');
    
    echo "Uploading $localPath ($fileSize bytes)...\n";
    
    try {
        $filesystem->writeStream($remotePath, $stream);
        echo "✓ Upload completed!\n";
    } catch (Exception $e) {
        echo "✗ Upload failed: " . $e->getMessage() . "\n";
    } finally {
        fclose($stream);
    }
}

uploadLargeFile($filesystem, '/path/to/large-file.zip', 'uploads/large-file.zip');
```

### Synchronizing Directories
```php
function syncDirectory($filesystem, $localDir, $remoteDir) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($localDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $relativePath = str_replace($localDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $remotePath = $remoteDir . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
            
            // Create remote directory if needed
            $remoteSubDir = dirname($remotePath);
            if ($remoteSubDir !== $remoteDir && !$filesystem->directoryExists($remoteSubDir)) {
                $filesystem->createDirectory($remoteSubDir);
            }
            
            // Upload file
            $stream = fopen($file->getPathname(), 'r');
            $filesystem->writeStream($remotePath, $stream);
            fclose($stream);
            
            echo "Synced: $relativePath\n";
        }
    }
}

syncDirectory($filesystem, '/local/documents', 'remote/documents');
```

## Error Handling

### Specific Exception Handling
```php
use Yangweijie\FilesystemCtlife\Exceptions\AuthenticationException;
use Yangweijie\FilesystemCtlife\Exceptions\NetworkException;
use Yangweijie\FilesystemCtlife\Exceptions\RateLimitException;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnableToReadFile;

try {
    $filesystem->write('test.txt', 'content');
} catch (AuthenticationException $e) {
    echo "Authentication failed: " . $e->getMessage();
    // Handle re-authentication
} catch (NetworkException $e) {
    echo "Network error: " . $e->getMessage();
    // Retry or handle network issues
} catch (RateLimitException $e) {
    echo "Rate limited. Retry after: " . $e->getRetryAfter() . " seconds";
    // Wait and retry
} catch (UnableToWriteFile $e) {
    echo "Write failed: " . $e->getMessage();
    // Handle write errors
}
```

### Retry Logic
```php
function writeWithRetry($filesystem, $path, $content, $maxRetries = 3) {
    $attempt = 0;
    
    while ($attempt < $maxRetries) {
        try {
            $filesystem->write($path, $content);
            return true;
        } catch (RateLimitException $e) {
            $retryAfter = $e->getRetryAfter();
            echo "Rate limited. Waiting {$retryAfter} seconds...\n";
            sleep($retryAfter);
            $attempt++;
        } catch (NetworkException $e) {
            echo "Network error on attempt " . ($attempt + 1) . "\n";
            $attempt++;
            if ($attempt < $maxRetries) {
                sleep(pow(2, $attempt)); // Exponential backoff
            }
        } catch (Exception $e) {
            echo "Unrecoverable error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    echo "Failed after $maxRetries attempts\n";
    return false;
}

writeWithRetry($filesystem, 'important.txt', 'Important content');
```

## Performance Tips

### 1. Use Streams for Large Files
```php
// Good: Use streams for large files
$stream = fopen('large-file.zip', 'r');
$filesystem->writeStream('uploads/large-file.zip', $stream);
fclose($stream);

// Avoid: Loading large files into memory
$content = file_get_contents('large-file.zip'); // Memory intensive
$filesystem->write('uploads/large-file.zip', $content);
```

### 2. Batch Operations
```php
// Good: Batch similar operations
$files = ['file1.txt', 'file2.txt', 'file3.txt'];
foreach ($files as $file) {
    $filesystem->delete($file);
}

// Avoid: Individual operations with delays
foreach ($files as $file) {
    $filesystem->delete($file);
    sleep(1); // Unnecessary delay
}
```

### 3. Cache Directory Listings
```php
// Cache directory contents for repeated access
static $directoryCache = [];

function getCachedListing($filesystem, $directory) {
    global $directoryCache;
    
    if (!isset($directoryCache[$directory])) {
        $directoryCache[$directory] = $filesystem->listContents($directory, false);
    }
    
    return $directoryCache[$directory];
}
```

## Integration Examples

### Laravel Integration
```php
// In a Laravel service provider
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Yangweijie\FilesystemCtlife\CTFileAdapter;
use Yangweijie\FilesystemCtlife\CTFileConfig;

class CTFileServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('ctfile', function ($app) {
            $config = new CTFileConfig([
                'session' => config('filesystems.disks.ctfile.session'),
                'app_id' => config('filesystems.disks.ctfile.app_id'),
            ]);
            
            $adapter = new CTFileAdapter($config);
            return new Filesystem($adapter);
        });
    }
}

// Usage in Laravel
$ctfile = app('ctfile');
$ctfile->write('uploads/file.txt', 'content');
```

### Symfony Integration
```php
// In services.yaml
services:
    ctfile.config:
        class: Yangweijie\FilesystemCtlife\CTFileConfig
        arguments:
            - session: '%env(CTFILE_SESSION)%'
              app_id: '%env(CTFILE_APP_ID)%'

    ctfile.adapter:
        class: Yangweijie\FilesystemCtlife\CTFileAdapter
        arguments: ['@ctfile.config']

    ctfile.filesystem:
        class: League\Flysystem\Filesystem
        arguments: ['@ctfile.adapter']
```

## Next Steps

- Check the [API Reference](api.md) for detailed method documentation
- Explore the [examples directory](../examples/) for more code samples
- Read about [error handling](../README.md#error-handling) for robust applications
