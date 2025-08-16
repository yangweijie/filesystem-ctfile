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

namespace YangWeijie\FilesystemCtfile\Tests\Integration;

use YangWeijie\FilesystemCtfile\Tests\Fixtures\MockCtFileServer;
use YangWeijie\FilesystemCtfile\Tests\TestCase;

/**
 * Integration tests using the mock ctFile server.
 *
 * These tests verify that the mock server behaves correctly and can
 * simulate various ctFile server responses and error conditions.
 */
class MockCtFileServerTest extends TestCase
{
    private MockCtFileServer $mockServer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockServer = new MockCtFileServer();
    }

    public function test_mock_server_can_be_instantiated(): void
    {
        expect($this->mockServer)->toBeInstanceOf(MockCtFileServer::class);
        expect($this->mockServer->isConnected())->toBeFalse();
    }

    public function test_mock_server_connection_and_authentication(): void
    {
        // Test successful connection
        $result = $this->mockServer->connect('localhost', 21, 'test', 'password');

        expect($result)->toBeTrue();
        expect($this->mockServer->isConnected())->toBeTrue();
        expect($this->mockServer->getOperationCount('connect'))->toBe(1);
    }

    public function test_mock_server_authentication_failure(): void
    {
        expect(fn () => $this->mockServer->connect('localhost', 21, 'invalid', 'wrong'))
            ->toThrow(\RuntimeException::class, 'Authentication failed');

        expect($this->mockServer->isConnected())->toBeFalse();
    }

    public function test_mock_server_file_operations(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Test file existence - should be false initially
        expect($this->mockServer->fileExists('test.txt'))->toBeFalse();

        // Upload a file
        $result = $this->mockServer->uploadFile('/local/test.txt', 'test.txt', 'Hello World');
        expect($result)->toBeTrue();

        // Test file existence - should be true now
        expect($this->mockServer->fileExists('test.txt'))->toBeTrue();

        // Get file info
        $info = $this->mockServer->getFileInfo('test.txt');
        expect($info['content'])->toBe('Hello World');
        expect($info['size'])->toBe(11);
        expect($info['type'])->toBe('file');

        // Delete the file
        $result = $this->mockServer->deleteFile('test.txt');
        expect($result)->toBeTrue();

        // Test file existence - should be false again
        expect($this->mockServer->fileExists('test.txt'))->toBeFalse();
    }

    public function test_mock_server_directory_operations(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Test directory existence - root should exist
        expect($this->mockServer->directoryExists('/'))->toBeTrue();
        expect($this->mockServer->directoryExists('uploads'))->toBeFalse();

        // Create directory
        $result = $this->mockServer->createDirectory('uploads');
        expect($result)->toBeTrue();

        // Test directory existence - should be true now
        expect($this->mockServer->directoryExists('uploads'))->toBeTrue();

        // Create nested directory
        $result = $this->mockServer->createDirectory('uploads/images');
        expect($result)->toBeTrue();
        expect($this->mockServer->directoryExists('uploads/images'))->toBeTrue();

        // Delete directory
        $result = $this->mockServer->deleteDirectory('uploads/images');
        expect($result)->toBeTrue();
        expect($this->mockServer->directoryExists('uploads/images'))->toBeFalse();
    }

    public function test_mock_server_directory_listing(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Add some test files and directories
        $this->mockServer->addFile('file1.txt', 'Content 1');
        $this->mockServer->addFile('folder/file2.txt', 'Content 2');
        $this->mockServer->addDirectory('empty-folder');

        // List root directory (non-recursive)
        $listing = $this->mockServer->listDirectory('/', false);

        $fileNames = array_column($listing, 'path');
        expect($fileNames)->toContain('/file1.txt');
        expect($fileNames)->toContain('/folder');
        expect($fileNames)->toContain('/empty-folder');
        expect($fileNames)->not->toContain('/folder/file2.txt'); // Should not be included in non-recursive

        // List root directory (recursive)
        $listing = $this->mockServer->listDirectory('/', true);

        $fileNames = array_column($listing, 'path');
        expect($fileNames)->toContain('/file1.txt');
        expect($fileNames)->toContain('/folder');
        expect($fileNames)->toContain('/folder/file2.txt'); // Should be included in recursive
        expect($fileNames)->toContain('/empty-folder');
    }

    public function test_mock_server_error_simulation(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Configure error simulation
        $this->mockServer->simulateError('fileExists', true, 'Simulated connection timeout');

        // Test that error is thrown
        expect(fn () => $this->mockServer->fileExists('test.txt'))
            ->toThrow(\RuntimeException::class, 'Simulated connection timeout');

        // Disable error simulation
        $this->mockServer->simulateError('fileExists', false);

        // Test that operation works normally
        expect($this->mockServer->fileExists('test.txt'))->toBeFalse();
    }

    public function test_mock_server_operation_counting(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Perform various operations
        $this->mockServer->fileExists('test1.txt');
        $this->mockServer->fileExists('test2.txt');
        $this->mockServer->directoryExists('folder');
        $this->mockServer->uploadFile('/local/test.txt', 'test.txt', 'content');

        // Check operation counts
        expect($this->mockServer->getOperationCount('connect'))->toBe(1);
        expect($this->mockServer->getOperationCount('fileExists'))->toBe(2);
        expect($this->mockServer->getOperationCount('directoryExists'))->toBe(1);
        expect($this->mockServer->getOperationCount('uploadFile'))->toBe(1);

        // Check all counts
        $allCounts = $this->mockServer->getAllOperationCounts();
        expect($allCounts)->toHaveKey('connect');
        expect($allCounts)->toHaveKey('fileExists');
        expect($allCounts)->toHaveKey('directoryExists');
        expect($allCounts)->toHaveKey('uploadFile');
    }

    public function test_mock_server_response_delays(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Set response delay (1000 microseconds = 1ms)
        $this->mockServer->setResponseDelay('fileExists', 1000);

        $startTime = microtime(true);
        $this->mockServer->fileExists('test.txt');
        $endTime = microtime(true);

        $duration = ($endTime - $startTime) * 1000000; // Convert to microseconds
        expect($duration)->toBeGreaterThan(500); // Should be at least 0.5ms
    }

    public function test_mock_server_state_management(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');
        $this->mockServer->addFile('test.txt', 'content');
        $this->mockServer->addDirectory('uploads');

        // Get current state
        $state = $this->mockServer->getState();

        expect($state['connected'])->toBeTrue();
        expect($state['authenticated'])->toBeTrue();
        expect($state['files'])->toHaveKey('/test.txt');
        expect($state['directories'])->toHaveKey('/uploads');
        expect($state['operation_counts'])->toHaveKey('connect');

        // Reset server
        $this->mockServer->reset();

        // Check state after reset
        $state = $this->mockServer->getState();
        expect($state['connected'])->toBeFalse();
        expect($state['authenticated'])->toBeFalse();
        expect($state['files'])->toBeEmpty();
        expect($state['directories'])->toHaveKey('/'); // Root should still exist
        expect($state['operation_counts'])->toBeEmpty();
    }

    public function test_mock_server_requires_connection(): void
    {
        // Test that operations require connection
        expect(fn () => $this->mockServer->fileExists('test.txt'))
            ->toThrow(\RuntimeException::class, 'Not connected to server');

        expect(fn () => $this->mockServer->uploadFile('/local/test.txt', 'test.txt'))
            ->toThrow(\RuntimeException::class, 'Not connected to server');

        expect(fn () => $this->mockServer->createDirectory('uploads'))
            ->toThrow(\RuntimeException::class, 'Not connected to server');
    }

    public function test_mock_server_file_not_found_errors(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Test operations on non-existent files
        expect(fn () => $this->mockServer->getFileInfo('nonexistent.txt'))
            ->toThrow(\RuntimeException::class, 'File not found: nonexistent.txt');

        expect(fn () => $this->mockServer->deleteFile('nonexistent.txt'))
            ->toThrow(\RuntimeException::class, 'File not found: nonexistent.txt');

        expect(fn () => $this->mockServer->downloadFile('nonexistent.txt', '/local/file.txt'))
            ->toThrow(\RuntimeException::class, 'File not found: nonexistent.txt');
    }

    public function test_mock_server_directory_not_found_errors(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Test operations on non-existent directories
        expect(fn () => $this->mockServer->deleteDirectory('nonexistent'))
            ->toThrow(\RuntimeException::class, 'Directory not found: nonexistent');
    }

    public function test_mock_server_directory_not_empty_error(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Create directory with content
        $this->mockServer->addDirectory('uploads');
        $this->mockServer->addFile('uploads/test.txt', 'content');

        // Try to delete non-empty directory without recursive flag
        expect(fn () => $this->mockServer->deleteDirectory('uploads', false))
            ->toThrow(\RuntimeException::class, 'Directory not empty: uploads');

        // Should work with recursive flag
        $result = $this->mockServer->deleteDirectory('uploads', true);
        expect($result)->toBeTrue();
        expect($this->mockServer->directoryExists('uploads'))->toBeFalse();
        expect($this->mockServer->fileExists('uploads/test.txt'))->toBeFalse();
    }

    public function test_mock_server_mime_type_detection(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Test different file types
        $testFiles = [
            'document.txt' => 'text/plain',
            'page.html' => 'text/html',
            'style.css' => 'text/css',
            'script.js' => 'application/javascript',
            'data.json' => 'application/json',
            'image.jpg' => 'image/jpeg',
            'photo.png' => 'image/png',
            'archive.zip' => 'application/zip',
            'unknown.xyz' => 'application/octet-stream',
        ];

        foreach ($testFiles as $filename => $expectedMimeType) {
            $this->mockServer->addFile($filename, 'test content');
            $info = $this->mockServer->getFileInfo($filename);
            expect($info['mime_type'])->toBe($expectedMimeType);
        }
    }

    public function test_mock_server_path_normalization(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Test various path formats
        $this->mockServer->addFile('/test.txt', 'content1');
        $this->mockServer->addFile('test2.txt', 'content2');
        $this->mockServer->addFile('folder/test3.txt', 'content3');

        // All should be accessible with normalized paths
        expect($this->mockServer->fileExists('/test.txt'))->toBeTrue();
        expect($this->mockServer->fileExists('test.txt'))->toBeTrue();
        expect($this->mockServer->fileExists('/test2.txt'))->toBeTrue();
        expect($this->mockServer->fileExists('test2.txt'))->toBeTrue();
        expect($this->mockServer->fileExists('/folder/test3.txt'))->toBeTrue();
        expect($this->mockServer->fileExists('folder/test3.txt'))->toBeTrue();
    }
}
