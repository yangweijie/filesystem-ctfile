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

use League\Flysystem\Filesystem;
use Mockery;
use YangWeijie\FilesystemCtfile\CtFileAdapter;
use YangWeijie\FilesystemCtfile\CtFileClient;
use YangWeijie\FilesystemCtfile\Tests\Fixtures\MockCtFileServer;
use YangWeijie\FilesystemCtfile\Tests\TestCase;

/**
 * Integration tests using the mock ctFile server with real components.
 *
 * These tests verify the complete integration between CtFileClient,
 * CtFileAdapter, and Flysystem using the mock server to simulate
 * various ctFile server behaviors.
 */
class CtFileIntegrationTest extends TestCase
{
    private MockCtFileServer $mockServer;

    private CtFileClient $client;

    private CtFileAdapter $adapter;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockServer = new MockCtFileServer();

        // Create a mock client that delegates to our mock server
        $this->client = Mockery::mock(CtFileClient::class);
        $this->setupClientMockDelegation();

        $this->adapter = new CtFileAdapter($this->client);
        $this->filesystem = new Filesystem($this->adapter);
    }

    public function test_integration_file_existence_check(): void
    {
        // Setup mock server state
        $this->mockServer->connect('localhost', 21, 'test', 'password');
        $this->mockServer->addFile('existing.txt', 'test content');

        // Test through filesystem interface
        expect($this->filesystem->fileExists('existing.txt'))->toBeTrue();
        expect($this->filesystem->fileExists('missing.txt'))->toBeFalse();

        // Verify mock server was called
        expect($this->mockServer->getOperationCount('fileExists'))->toBe(2);
    }

    public function test_integration_directory_existence_check(): void
    {
        // Setup mock server state
        $this->mockServer->connect('localhost', 21, 'test', 'password');
        $this->mockServer->addDirectory('uploads');

        // Test through filesystem interface
        expect($this->filesystem->directoryExists('uploads'))->toBeTrue();
        expect($this->filesystem->directoryExists('missing'))->toBeFalse();

        // Verify mock server was called
        expect($this->mockServer->getOperationCount('directoryExists'))->toBe(2);
    }

    public function test_integration_with_path_prefixing(): void
    {
        $config = ['root_path' => '/app/storage'];
        $adapter = new CtFileAdapter($this->client, $config);
        $filesystem = new Filesystem($adapter);

        // Setup mock server state
        $this->mockServer->connect('localhost', 21, 'test', 'password');
        $this->mockServer->addFile('/app/storage/test.txt', 'content');

        // Test that paths are properly prefixed
        expect($filesystem->fileExists('test.txt'))->toBeTrue();
    }

    public function test_integration_error_handling(): void
    {
        // Setup mock server to simulate errors
        $this->mockServer->connect('localhost', 21, 'test', 'password');
        $this->mockServer->simulateError('fileExists', true, 'Connection timeout');

        // Test that errors are properly propagated
        expect(fn () => $this->filesystem->fileExists('test.txt'))
            ->toThrow(\League\Flysystem\UnableToCheckFileExistence::class);
    }

    public function test_integration_multiple_operations(): void
    {
        // Setup mock server state
        $this->mockServer->connect('localhost', 21, 'test', 'password');
        $this->mockServer->addFile('file1.txt', 'content1');
        $this->mockServer->addFile('file2.txt', 'content2');
        $this->mockServer->addDirectory('uploads');

        // Perform multiple operations
        $results = [
            'file1_exists' => $this->filesystem->fileExists('file1.txt'),
            'file2_exists' => $this->filesystem->fileExists('file2.txt'),
            'file3_exists' => $this->filesystem->fileExists('file3.txt'),
            'uploads_exists' => $this->filesystem->directoryExists('uploads'),
            'missing_dir_exists' => $this->filesystem->directoryExists('missing'),
        ];

        // Verify results
        expect($results['file1_exists'])->toBeTrue();
        expect($results['file2_exists'])->toBeTrue();
        expect($results['file3_exists'])->toBeFalse();
        expect($results['uploads_exists'])->toBeTrue();
        expect($results['missing_dir_exists'])->toBeFalse();

        // Verify operation counts
        expect($this->mockServer->getOperationCount('fileExists'))->toBe(3);
        expect($this->mockServer->getOperationCount('directoryExists'))->toBe(2);
    }

    public function test_integration_with_nested_paths(): void
    {
        // Setup mock server state
        $this->mockServer->connect('localhost', 21, 'test', 'password');
        $this->mockServer->addFile('folder/subfolder/deep.txt', 'deep content');
        $this->mockServer->addDirectory('folder/subfolder');

        // Test nested path operations
        expect($this->filesystem->fileExists('folder/subfolder/deep.txt'))->toBeTrue();
        expect($this->filesystem->directoryExists('folder/subfolder'))->toBeTrue();
        expect($this->filesystem->directoryExists('folder'))->toBeTrue();
    }

    public function test_integration_error_recovery(): void
    {
        // Setup mock server
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // First operation fails
        $this->mockServer->simulateError('fileExists', true, 'Temporary error');
        expect(fn () => $this->filesystem->fileExists('test.txt'))
            ->toThrow(\League\Flysystem\UnableToCheckFileExistence::class);

        // Second operation succeeds (error simulation disabled)
        $this->mockServer->simulateError('fileExists', false);
        $this->mockServer->addFile('test.txt', 'content');
        expect($this->filesystem->fileExists('test.txt'))->toBeTrue();
    }

    public function test_integration_concurrent_operations_simulation(): void
    {
        // Setup mock server state
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Add multiple files
        for ($i = 1; $i <= 10; $i++) {
            $this->mockServer->addFile("file{$i}.txt", "content{$i}");
        }

        // Simulate concurrent file existence checks
        $results = [];
        for ($i = 1; $i <= 10; $i++) {
            $results["file{$i}"] = $this->filesystem->fileExists("file{$i}.txt");
        }

        // Verify all operations succeeded
        foreach ($results as $result) {
            expect($result)->toBeTrue();
        }

        // Verify operation count
        expect($this->mockServer->getOperationCount('fileExists'))->toBe(10);
    }

    public function test_integration_with_response_delays(): void
    {
        // Setup mock server with delays
        $this->mockServer->connect('localhost', 21, 'test', 'password');
        $this->mockServer->setResponseDelay('fileExists', 1000); // 1ms delay
        $this->mockServer->addFile('slow.txt', 'content');

        // Measure operation time
        $startTime = microtime(true);
        $result = $this->filesystem->fileExists('slow.txt');
        $endTime = microtime(true);

        expect($result)->toBeTrue();

        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
        expect($duration)->toBeGreaterThan(0.5); // Should be at least 0.5ms
    }

    public function test_integration_state_consistency(): void
    {
        // Setup mock server state
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Initial state - no files
        expect($this->filesystem->fileExists('test.txt'))->toBeFalse();

        // Add file through mock server
        $this->mockServer->addFile('test.txt', 'content');

        // File should now exist
        expect($this->filesystem->fileExists('test.txt'))->toBeTrue();

        // Remove file through mock server
        $this->mockServer->deleteFile('test.txt');

        // File should no longer exist
        expect($this->filesystem->fileExists('test.txt'))->toBeFalse();
    }

    public function test_integration_adapter_configuration_with_mock(): void
    {
        $config = [
            'root_path' => '/test/root',
            'path_separator' => '/',
            'case_sensitive' => false,
        ];

        $adapter = new CtFileAdapter($this->client, $config);
        $filesystem = new Filesystem($adapter);

        // Setup mock server
        $this->mockServer->connect('localhost', 21, 'test', 'password');
        $this->mockServer->addFile('/test/root/config-test.txt', 'content');

        // Test with configuration
        expect($filesystem->fileExists('config-test.txt'))->toBeTrue();
        expect($adapter->getConfig('root_path'))->toBe('/test/root');
        expect($adapter->getConfig('case_sensitive'))->toBeFalse();
    }

    public function test_integration_unimplemented_operations_with_mock(): void
    {
        // Setup mock server
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Test that implemented operations work with mock server
        $this->mockServer->addFile('test.txt', 'test content');
        $content = $this->filesystem->read('test.txt');
        expect($content)->toBe('test content');

        // Test write operation
        $this->filesystem->write('new-file.txt', 'new content');
        expect($this->mockServer->fileExists('new-file.txt'))->toBeTrue();

        // Test delete operation
        $this->filesystem->delete('test.txt');
        expect($this->mockServer->fileExists('test.txt'))->toBeFalse();

        // Test directory operations
        $this->filesystem->createDirectory('test-dir');
        expect($this->mockServer->directoryExists('test-dir'))->toBeTrue();

        $this->filesystem->deleteDirectory('test-dir');
        expect($this->mockServer->directoryExists('test-dir'))->toBeFalse();
    }

    /**
     * Setup client mock to delegate to mock server.
     */
    private function setupClientMockDelegation(): void
    {
        $this->client
            ->shouldReceive('fileExists')
            ->andReturnUsing(function ($path) {
                return $this->mockServer->fileExists($path);
            });

        $this->client
            ->shouldReceive('directoryExists')
            ->andReturnUsing(function ($path) {
                return $this->mockServer->directoryExists($path);
            });

        $this->client
            ->shouldReceive('readFile')
            ->andReturnUsing(function ($path) {
                return $this->mockServer->readFile($path);
            });

        // Add other method delegations as needed when more methods are implemented
    }
}
