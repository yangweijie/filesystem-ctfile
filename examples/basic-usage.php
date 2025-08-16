<?php

declare(strict_types=1);

/**
 * Basic Usage Example
 * 
 * This example demonstrates basic usage of the yangweijie/filesystem-ctfile package.
 * 
 * Before running this example:
 * 1. Install the package: composer require yangweijie/filesystem-ctfile
 * 2. Update the configuration below with your actual ctFile server details
 * 3. Run: php examples/basic-usage.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use League\Flysystem\Filesystem;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnableToReadFile;
use YangWeijie\FilesystemCtfile\CtFileAdapter;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileException;

// Configuration - Update these values with your actual ctFile server details
$config = [
    'ctfile' => [
        'host' => 'your-ctfile-host.com',      // Replace with your ctFile server host
        'port' => 21,                          // Replace with your ctFile server port
        'username' => 'your-username',         // Replace with your username
        'password' => 'your-password',         // Replace with your password
        'timeout' => 30,
        'ssl' => false,
        'passive' => true,
    ],
    'adapter' => [
        'root_path' => '/example',             // Optional: Set a root path
        'create_directories' => true,          // Auto-create directories
    ],
    'logging' => [
        'enabled' => true,                     // Enable logging for this example
        'level' => 'info',
    ],
];

echo "=== yangweijie/filesystem-ctfile Basic Usage Example ===" . PHP_EOL . PHP_EOL;

try {
    // Create the adapter and filesystem
    echo "Creating CtFileAdapter..." . PHP_EOL;
    $adapter = new CtFileAdapter($config);
    $filesystem = new Filesystem($adapter);
    echo "✓ Adapter created successfully" . PHP_EOL . PHP_EOL;

    // Example 1: Write a file
    echo "Example 1: Writing a file..." . PHP_EOL;
    $filename = 'example.txt';
    $content = 'Hello, World! This is a test file created at ' . date('Y-m-d H:i:s');
    
    $filesystem->write($filename, $content);
    echo "✓ File '{$filename}' written successfully" . PHP_EOL . PHP_EOL;

    // Example 2: Check if file exists
    echo "Example 2: Checking if file exists..." . PHP_EOL;
    if ($filesystem->fileExists($filename)) {
        echo "✓ File '{$filename}' exists" . PHP_EOL;
    } else {
        echo "✗ File '{$filename}' does not exist" . PHP_EOL;
    }
    echo PHP_EOL;

    // Example 3: Read the file
    echo "Example 3: Reading the file..." . PHP_EOL;
    $readContent = $filesystem->read($filename);
    echo "✓ File content: " . $readContent . PHP_EOL . PHP_EOL;

    // Example 4: Get file metadata
    echo "Example 4: Getting file metadata..." . PHP_EOL;
    $fileSize = $filesystem->fileSize($filename);
    echo "✓ File size: {$fileSize} bytes" . PHP_EOL;
    
    try {
        $mimeType = $filesystem->mimeType($filename);
        echo "✓ MIME type: {$mimeType}" . PHP_EOL;
    } catch (Exception $e) {
        echo "ℹ MIME type detection not available: " . $e->getMessage() . PHP_EOL;
    }
    
    try {
        $lastModified = $filesystem->lastModified($filename);
        echo "✓ Last modified: " . date('Y-m-d H:i:s', $lastModified) . PHP_EOL;
    } catch (Exception $e) {
        echo "ℹ Last modified time not available: " . $e->getMessage() . PHP_EOL;
    }
    echo PHP_EOL;

    // Example 5: Create a directory
    echo "Example 5: Creating a directory..." . PHP_EOL;
    $dirName = 'test-directory';
    $filesystem->createDirectory($dirName);
    echo "✓ Directory '{$dirName}' created successfully" . PHP_EOL . PHP_EOL;

    // Example 6: Write a file in the directory
    echo "Example 6: Writing a file in the directory..." . PHP_EOL;
    $dirFile = $dirName . '/nested-file.txt';
    $filesystem->write($dirFile, 'This is a file in a subdirectory');
    echo "✓ File '{$dirFile}' written successfully" . PHP_EOL . PHP_EOL;

    // Example 7: List directory contents
    echo "Example 7: Listing directory contents..." . PHP_EOL;
    $listing = $filesystem->listContents('/', false); // List root directory, non-recursive
    echo "Contents of root directory:" . PHP_EOL;
    foreach ($listing as $item) {
        $type = $item->isFile() ? 'FILE' : 'DIR';
        echo "  {$type}: {$item->path()}" . PHP_EOL;
    }
    echo PHP_EOL;

    // Example 8: Copy a file
    echo "Example 8: Copying a file..." . PHP_EOL;
    $copyName = 'example-copy.txt';
    $filesystem->copy($filename, $copyName);
    echo "✓ File copied from '{$filename}' to '{$copyName}'" . PHP_EOL . PHP_EOL;

    // Example 9: Move/rename a file
    echo "Example 9: Moving/renaming a file..." . PHP_EOL;
    $newName = 'example-renamed.txt';
    $filesystem->move($copyName, $newName);
    echo "✓ File moved from '{$copyName}' to '{$newName}'" . PHP_EOL . PHP_EOL;

    // Example 10: Clean up - delete files and directory
    echo "Example 10: Cleaning up..." . PHP_EOL;
    $filesystem->delete($filename);
    echo "✓ Deleted '{$filename}'" . PHP_EOL;
    
    $filesystem->delete($newName);
    echo "✓ Deleted '{$newName}'" . PHP_EOL;
    
    $filesystem->delete($dirFile);
    echo "✓ Deleted '{$dirFile}'" . PHP_EOL;
    
    $filesystem->deleteDirectory($dirName);
    echo "✓ Deleted directory '{$dirName}'" . PHP_EOL . PHP_EOL;

    echo "=== All examples completed successfully! ===" . PHP_EOL;

} catch (UnableToWriteFile $e) {
    echo "✗ Failed to write file: " . $e->getMessage() . PHP_EOL;
    echo "Check your ctFile server configuration and permissions." . PHP_EOL;
} catch (UnableToReadFile $e) {
    echo "✗ Failed to read file: " . $e->getMessage() . PHP_EOL;
    echo "Check if the file exists and you have read permissions." . PHP_EOL;
} catch (CtFileException $e) {
    echo "✗ ctFile error: " . $e->getMessage() . PHP_EOL;
    echo "Check your ctFile server connection and credentials." . PHP_EOL;
} catch (Exception $e) {
    echo "✗ Unexpected error: " . $e->getMessage() . PHP_EOL;
    echo "Stack trace:" . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}

echo PHP_EOL . "For more examples, see the docs/ directory." . PHP_EOL;