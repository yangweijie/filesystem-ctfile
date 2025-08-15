<?php

require_once __DIR__ . '/../vendor/autoload.php';

use League\Flysystem\Filesystem;
use Yangweijie\FilesystemCtlife\CTFileAdapter;
use Yangweijie\FilesystemCtlife\CTFileConfig;
use Yangweijie\FilesystemCtlife\Exceptions\AuthenticationException;
use Yangweijie\FilesystemCtlife\Exceptions\NetworkException;
use Yangweijie\FilesystemCtlife\Exceptions\RateLimitException;

// Advanced configuration
$config = new CTFileConfig([
    'session' => 'your-ctfile-session-token',
    'app_id' => 'your-ctfile-app-id',
    'api_base_url' => 'https://webapi.ctfile.com',
    'timeout' => 60,
    'retry_attempts' => 5,
    'cache_ttl' => 7200, // 2 hours cache
]);

$adapter = new CTFileAdapter($config);
$filesystem = new Filesystem($adapter);

echo "CTFile Flysystem Adapter - Advanced Usage Examples\n";
echo "===================================================\n\n";

// 1. Error Handling and Retry Logic
echo "1. Error Handling and Retry Logic\n";
echo "----------------------------------\n";

function performOperationWithRetry($filesystem, $operation, $maxRetries = 3) {
    $attempt = 0;
    
    while ($attempt < $maxRetries) {
        try {
            return $operation();
        } catch (RateLimitException $e) {
            $retryAfter = $e->getRetryAfter();
            echo "Rate limited. Waiting {$retryAfter} seconds before retry...\n";
            sleep($retryAfter);
            $attempt++;
        } catch (NetworkException $e) {
            echo "Network error on attempt " . ($attempt + 1) . ": " . $e->getMessage() . "\n";
            $attempt++;
            if ($attempt < $maxRetries) {
                sleep(pow(2, $attempt)); // Exponential backoff
            }
        } catch (AuthenticationException $e) {
            echo "Authentication error: " . $e->getMessage() . "\n";
            throw $e; // Don't retry authentication errors
        }
    }
    
    throw new Exception("Operation failed after {$maxRetries} attempts");
}

try {
    $result = performOperationWithRetry($filesystem, function() use ($filesystem) {
        return $filesystem->write('retry-test.txt', 'Test content with retry logic');
    });
    echo "✓ File written with retry logic\n";
} catch (Exception $e) {
    echo "❌ Failed to write file: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. Batch Operations
echo "2. Batch Operations\n";
echo "-------------------\n";

function batchUpload($filesystem, $files, $batchSize = 5) {
    $batches = array_chunk($files, $batchSize);
    $results = [];
    
    foreach ($batches as $batchIndex => $batch) {
        echo "Processing batch " . ($batchIndex + 1) . "/" . count($batches) . "\n";
        
        foreach ($batch as $file) {
            try {
                $filesystem->write($file['path'], $file['content']);
                $results[] = ['path' => $file['path'], 'status' => 'success'];
                echo "  ✓ " . $file['path'] . "\n";
            } catch (Exception $e) {
                $results[] = ['path' => $file['path'], 'status' => 'failed', 'error' => $e->getMessage()];
                echo "  ❌ " . $file['path'] . ": " . $e->getMessage() . "\n";
            }
        }
        
        // Small delay between batches to avoid rate limiting
        if ($batchIndex < count($batches) - 1) {
            sleep(1);
        }
    }
    
    return $results;
}

$filesToUpload = [];
for ($i = 1; $i <= 10; $i++) {
    $filesToUpload[] = [
        'path' => "batch/file-{$i}.txt",
        'content' => "Content for file {$i} - " . date('Y-m-d H:i:s')
    ];
}

echo "Uploading " . count($filesToUpload) . " files in batches...\n";
$batchResults = batchUpload($filesystem, $filesToUpload);

$successful = array_filter($batchResults, fn($r) => $r['status'] === 'success');
$failed = array_filter($batchResults, fn($r) => $r['status'] === 'failed');

echo "Batch upload completed: " . count($successful) . " successful, " . count($failed) . " failed\n";

echo "\n";

// 3. Large File Handling
echo "3. Large File Handling\n";
echo "----------------------\n";

function uploadLargeFile($filesystem, $path, $sizeInMB = 10) {
    echo "Creating large file ({$sizeInMB}MB)...\n";
    
    // Create a large file using streams
    $tempFile = tempnam(sys_get_temp_dir(), 'ctfile_large_');
    $handle = fopen($tempFile, 'w');
    
    $chunkSize = 1024 * 1024; // 1MB chunks
    $chunk = str_repeat('A', $chunkSize);
    
    for ($i = 0; $i < $sizeInMB; $i++) {
        fwrite($handle, $chunk);
    }
    fclose($handle);
    
    echo "Uploading large file...\n";
    $startTime = microtime(true);
    
    $stream = fopen($tempFile, 'r');
    $filesystem->writeStream($path, $stream);
    fclose($stream);
    
    $uploadTime = microtime(true) - $startTime;
    $fileSize = $filesystem->fileSize($path);
    
    echo "✓ Large file uploaded in " . round($uploadTime, 2) . " seconds\n";
    echo "✓ File size: " . round($fileSize / 1024 / 1024, 2) . " MB\n";
    
    // Cleanup
    unlink($tempFile);
    
    return $path;
}

try {
    $largeFilePath = uploadLargeFile($filesystem, 'large-file-test.bin', 5);
    echo "✓ Large file handling completed\n";
} catch (Exception $e) {
    echo "❌ Large file handling failed: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. Directory Synchronization
echo "4. Directory Synchronization\n";
echo "----------------------------\n";

function syncDirectory($filesystem, $localDir, $remoteDir) {
    if (!is_dir($localDir)) {
        throw new InvalidArgumentException("Local directory does not exist: {$localDir}");
    }
    
    echo "Syncing {$localDir} to {$remoteDir}...\n";
    
    // Create remote directory if it doesn't exist
    if (!$filesystem->directoryExists($remoteDir)) {
        $filesystem->createDirectory($remoteDir);
        echo "✓ Created remote directory: {$remoteDir}\n";
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($localDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    $syncedFiles = 0;
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $relativePath = str_replace($localDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $remotePath = $remoteDir . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
            
            // Create remote subdirectories if needed
            $remoteSubDir = dirname($remotePath);
            if ($remoteSubDir !== $remoteDir && !$filesystem->directoryExists($remoteSubDir)) {
                $filesystem->createDirectory($remoteSubDir);
                echo "✓ Created remote subdirectory: {$remoteSubDir}\n";
            }
            
            // Upload file
            $stream = fopen($file->getPathname(), 'r');
            $filesystem->writeStream($remotePath, $stream);
            fclose($stream);
            
            echo "✓ Synced: {$relativePath}\n";
            $syncedFiles++;
        }
    }
    
    echo "✓ Synchronization completed: {$syncedFiles} files synced\n";
}

// Create a temporary local directory for demo
$tempDir = sys_get_temp_dir() . '/ctfile_sync_demo';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

// Create some demo files
file_put_contents($tempDir . '/file1.txt', 'Content of file 1');
file_put_contents($tempDir . '/file2.txt', 'Content of file 2');

mkdir($tempDir . '/subdir', 0755, true);
file_put_contents($tempDir . '/subdir/file3.txt', 'Content of file 3 in subdirectory');

try {
    syncDirectory($filesystem, $tempDir, 'sync-demo');
    echo "✓ Directory synchronization completed\n";
} catch (Exception $e) {
    echo "❌ Directory synchronization failed: " . $e->getMessage() . "\n";
}

// Cleanup local temp directory
function removeDirectory($dir) {
    if (!is_dir($dir)) return;
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? removeDirectory($path) : unlink($path);
    }
    rmdir($dir);
}

removeDirectory($tempDir);

echo "\n";

// 5. Performance Monitoring
echo "5. Performance Monitoring\n";
echo "-------------------------\n";

class PerformanceMonitor {
    private $operations = [];
    
    public function time($operation, $callback) {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        try {
            $result = $callback();
            $status = 'success';
        } catch (Exception $e) {
            $result = null;
            $status = 'failed';
            $error = $e->getMessage();
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $this->operations[] = [
            'operation' => $operation,
            'duration' => $endTime - $startTime,
            'memory_used' => $endMemory - $startMemory,
            'status' => $status,
            'error' => $error ?? null,
        ];
        
        return $result;
    }
    
    public function getReport() {
        $report = "Performance Report:\n";
        $report .= str_repeat("-", 50) . "\n";
        
        foreach ($this->operations as $op) {
            $duration = round($op['duration'], 3);
            $memory = round($op['memory_used'] / 1024, 2);
            $status = $op['status'] === 'success' ? '✓' : '❌';
            
            $report .= "{$status} {$op['operation']}: {$duration}s, {$memory}KB\n";
            if ($op['error']) {
                $report .= "   Error: {$op['error']}\n";
            }
        }
        
        return $report;
    }
}

$monitor = new PerformanceMonitor();

// Monitor various operations
$monitor->time('Write small file', function() use ($filesystem) {
    return $filesystem->write('perf-small.txt', 'Small file content');
});

$monitor->time('Read small file', function() use ($filesystem) {
    return $filesystem->read('perf-small.txt');
});

$monitor->time('List directory', function() use ($filesystem) {
    return iterator_to_array($filesystem->listContents('/', false));
});

$monitor->time('Get file metadata', function() use ($filesystem) {
    return $filesystem->fileSize('perf-small.txt');
});

echo $monitor->getReport();

echo "\n✅ All advanced examples completed!\n";

// Final cleanup
echo "\nCleaning up test files...\n";
$testFiles = ['retry-test.txt', 'large-file-test.bin', 'perf-small.txt'];
foreach ($testFiles as $file) {
    try {
        if ($filesystem->fileExists($file)) {
            $filesystem->delete($file);
            echo "✓ Deleted: {$file}\n";
        }
    } catch (Exception $e) {
        echo "❌ Failed to delete {$file}: " . $e->getMessage() . "\n";
    }
}

// Clean up batch files
for ($i = 1; $i <= 10; $i++) {
    try {
        $file = "batch/file-{$i}.txt";
        if ($filesystem->fileExists($file)) {
            $filesystem->delete($file);
        }
    } catch (Exception $e) {
        // Ignore cleanup errors
    }
}

try {
    if ($filesystem->directoryExists('batch')) {
        $filesystem->deleteDirectory('batch');
        echo "✓ Deleted batch directory\n";
    }
    if ($filesystem->directoryExists('sync-demo')) {
        // Delete files in sync-demo directory first
        $contents = $filesystem->listContents('sync-demo', true);
        foreach ($contents as $item) {
            if ($item->isFile()) {
                $filesystem->delete($item->path());
            }
        }
        // Then delete subdirectories and main directory
        $filesystem->deleteDirectory('sync-demo/subdir');
        $filesystem->deleteDirectory('sync-demo');
        echo "✓ Deleted sync-demo directory\n";
    }
} catch (Exception $e) {
    echo "❌ Cleanup error: " . $e->getMessage() . "\n";
}
