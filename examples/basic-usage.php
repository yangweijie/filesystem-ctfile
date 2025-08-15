<?php

require_once __DIR__ . '/../vendor/autoload.php';

use League\Flysystem\Filesystem;
use Yangweijie\FilesystemCtlife\CTFileAdapter;
use Yangweijie\FilesystemCtlife\CTFileConfig;

// Configuration
$config = new CTFileConfig([
    'session' => 'your-ctfile-session-token',
    'app_id' => 'your-ctfile-app-id',
    'timeout' => 30,
    'retry_attempts' => 3,
]);

// Create adapter and filesystem
$adapter = new CTFileAdapter($config);
$filesystem = new Filesystem($adapter);

echo "CTFile Flysystem Adapter - Basic Usage Examples\n";
echo "===============================================\n\n";

try {
    // 1. File Operations
    echo "1. File Operations\n";
    echo "------------------\n";
    
    // Write a file
    $filename = 'example-' . date('Y-m-d-H-i-s') . '.txt';
    $content = "Hello, CTFile!\nThis is a test file created at " . date('Y-m-d H:i:s');
    
    echo "Writing file: {$filename}\n";
    $filesystem->write($filename, $content);
    echo "✓ File written successfully\n";
    
    // Check if file exists
    if ($filesystem->fileExists($filename)) {
        echo "✓ File exists\n";
    }
    
    // Read the file
    $readContent = $filesystem->read($filename);
    echo "✓ File content read: " . strlen($readContent) . " bytes\n";
    
    // Get file metadata
    $size = $filesystem->fileSize($filename);
    $mimeType = $filesystem->mimeType($filename);
    $lastModified = $filesystem->lastModified($filename);
    
    echo "✓ File size: {$size} bytes\n";
    echo "✓ MIME type: {$mimeType}\n";
    echo "✓ Last modified: " . date('Y-m-d H:i:s', $lastModified) . "\n";
    
    echo "\n";
    
    // 2. Directory Operations
    echo "2. Directory Operations\n";
    echo "-----------------------\n";
    
    $dirName = 'example-dir-' . date('Y-m-d-H-i-s');
    
    // Create directory
    echo "Creating directory: {$dirName}\n";
    $filesystem->createDirectory($dirName);
    echo "✓ Directory created\n";
    
    // Check if directory exists
    if ($filesystem->directoryExists($dirName)) {
        echo "✓ Directory exists\n";
    }
    
    // Create a file in the directory
    $subFile = $dirName . '/sub-file.txt';
    $filesystem->write($subFile, 'Content in subdirectory');
    echo "✓ File created in directory\n";
    
    // List directory contents
    echo "Directory contents:\n";
    $contents = $filesystem->listContents($dirName, false);
    foreach ($contents as $item) {
        $type = $item->isFile() ? 'File' : 'Directory';
        echo "  - {$type}: " . $item->path() . "\n";
    }
    
    echo "\n";
    
    // 3. Stream Operations
    echo "3. Stream Operations\n";
    echo "--------------------\n";
    
    $streamFile = 'stream-example.txt';
    $streamContent = "This content is written using streams.\n" . str_repeat("Stream data line.\n", 100);
    
    // Create a memory stream
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $streamContent);
    rewind($stream);
    
    echo "Writing stream to file: {$streamFile}\n";
    $filesystem->writeStream($streamFile, $stream);
    fclose($stream);
    echo "✓ Stream written successfully\n";
    
    // Read as stream
    $readStream = $filesystem->readStream($streamFile);
    $streamSize = 0;
    while (!feof($readStream)) {
        $chunk = fread($readStream, 1024);
        $streamSize += strlen($chunk);
    }
    fclose($readStream);
    
    echo "✓ Stream read: {$streamSize} bytes\n";
    
    echo "\n";
    
    // 4. File Copy and Move
    echo "4. File Copy and Move\n";
    echo "---------------------\n";
    
    $sourceFile = 'source-file.txt';
    $copyFile = 'copied-file.txt';
    $moveFile = 'moved-file.txt';
    
    // Create source file
    $filesystem->write($sourceFile, 'Source file content for copy/move operations');
    echo "✓ Source file created\n";
    
    // Copy file
    $filesystem->copy($sourceFile, $copyFile);
    echo "✓ File copied\n";
    
    // Move file
    $filesystem->move($copyFile, $moveFile);
    echo "✓ File moved\n";
    
    // Verify operations
    echo "Files after copy/move:\n";
    echo "  - Source exists: " . ($filesystem->fileExists($sourceFile) ? 'Yes' : 'No') . "\n";
    echo "  - Copy exists: " . ($filesystem->fileExists($copyFile) ? 'Yes' : 'No') . "\n";
    echo "  - Moved exists: " . ($filesystem->fileExists($moveFile) ? 'Yes' : 'No') . "\n";
    
    echo "\n";
    
    // 5. List Root Directory
    echo "5. Root Directory Listing\n";
    echo "-------------------------\n";
    
    echo "Root directory contents (first 10 items):\n";
    $rootContents = $filesystem->listContents('/', false);
    $count = 0;
    foreach ($rootContents as $item) {
        if ($count >= 10) break;
        $type = $item->isFile() ? 'File' : 'Directory';
        $size = $item->isFile() ? " ({$item->fileSize()} bytes)" : '';
        echo "  - {$type}: " . $item->path() . $size . "\n";
        $count++;
    }
    
    echo "\n";
    
    // Cleanup
    echo "6. Cleanup\n";
    echo "----------\n";
    
    $filesToDelete = [$filename, $subFile, $streamFile, $sourceFile, $moveFile];
    foreach ($filesToDelete as $file) {
        if ($filesystem->fileExists($file)) {
            $filesystem->delete($file);
            echo "✓ Deleted: {$file}\n";
        }
    }
    
    if ($filesystem->directoryExists($dirName)) {
        $filesystem->deleteDirectory($dirName);
        echo "✓ Deleted directory: {$dirName}\n";
    }
    
    echo "\n✅ All examples completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    if ($e->getPrevious()) {
        echo "Previous: " . $e->getPrevious()->getMessage() . "\n";
    }
}
