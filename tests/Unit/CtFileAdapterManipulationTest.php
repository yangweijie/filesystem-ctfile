<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Tests\Unit;

use League\Flysystem\Config;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use Mockery;
use PHPUnit\Framework\TestCase;
use YangWeijie\FilesystemCtfile\CtFileAdapter;
use YangWeijie\FilesystemCtfile\CtFileClient;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileOperationException;

/**
 * Unit tests for CtFileAdapter file and directory manipulation methods.
 */
class CtFileAdapterManipulationTest extends TestCase
{
    private CtFileClient $mockClient;

    private CtFileAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock(CtFileClient::class);
        $this->adapter = new CtFileAdapter($this->mockClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // Delete file tests

    public function test_delete_file_successfully(): void
    {
        $path = 'test/file.txt';

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with($path)
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('deleteFile')
            ->once()
            ->with($path)
            ->andReturn(true);

        $this->adapter->delete($path);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function test_delete_nonexistent_file_succeeds(): void
    {
        $path = 'test/nonexistent.txt';

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with($path)
            ->andReturn(false);

        // Should not call deleteFile for non-existent files
        $this->mockClient
            ->shouldNotReceive('deleteFile');

        $this->adapter->delete($path);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function test_delete_file_fails_when_operation_fails(): void
    {
        $path = 'test/file.txt';

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with($path)
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('deleteFile')
            ->once()
            ->with($path)
            ->andReturn(false);

        $this->expectException(UnableToDeleteFile::class);
        $this->expectExceptionMessage('Delete operation failed');

        $this->adapter->delete($path);
    }

    public function test_delete_file_fails_when_client_throws_exception(): void
    {
        $path = 'test/file.txt';

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with($path)
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('deleteFile')
            ->once()
            ->with($path)
            ->andThrow(new CtFileOperationException('Client error', 'delete_file', $path));

        $this->expectException(UnableToDeleteFile::class);
        $this->expectExceptionMessage('Client error');

        $this->adapter->delete($path);
    }

    // Delete directory tests

    public function test_delete_directory_successfully(): void
    {
        $path = 'test/directory';

        $this->mockClient
            ->shouldReceive('directoryExists')
            ->once()
            ->with($path)
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('removeDirectory')
            ->once()
            ->with($path, true)
            ->andReturn(true);

        $this->adapter->deleteDirectory($path);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function test_delete_nonexistent_directory_succeeds(): void
    {
        $path = 'test/nonexistent';

        $this->mockClient
            ->shouldReceive('directoryExists')
            ->once()
            ->with($path)
            ->andReturn(false);

        // Should not call removeDirectory for non-existent directories
        $this->mockClient
            ->shouldNotReceive('removeDirectory');

        $this->adapter->deleteDirectory($path);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function test_delete_directory_fails_when_operation_fails(): void
    {
        $path = 'test/directory';

        $this->mockClient
            ->shouldReceive('directoryExists')
            ->once()
            ->with($path)
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('removeDirectory')
            ->once()
            ->with($path, true)
            ->andReturn(false);

        $this->expectException(UnableToDeleteDirectory::class);
        $this->expectExceptionMessage('Directory deletion failed');

        $this->adapter->deleteDirectory($path);
    }

    public function test_delete_directory_fails_when_client_throws_exception(): void
    {
        $path = 'test/directory';

        $this->mockClient
            ->shouldReceive('directoryExists')
            ->once()
            ->with($path)
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('removeDirectory')
            ->once()
            ->with($path, true)
            ->andThrow(new CtFileOperationException('Client error', 'remove_directory', $path));

        $this->expectException(UnableToDeleteDirectory::class);
        $this->expectExceptionMessage('Client error');

        $this->adapter->deleteDirectory($path);
    }

    // Create directory tests

    public function test_create_directory_successfully(): void
    {
        $path = 'test/new-directory';
        $config = new Config();

        $this->mockClient
            ->shouldReceive('directoryExists')
            ->once()
            ->with($path)
            ->andReturn(false);

        $this->mockClient
            ->shouldReceive('createDirectory')
            ->once()
            ->with($path, true)
            ->andReturn(true);

        $this->adapter->createDirectory($path, $config);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function test_create_existing_directory_succeeds(): void
    {
        $path = 'test/existing-directory';
        $config = new Config();

        $this->mockClient
            ->shouldReceive('directoryExists')
            ->once()
            ->with($path)
            ->andReturn(true);

        // Should not call createDirectory for existing directories
        $this->mockClient
            ->shouldNotReceive('createDirectory');

        $this->adapter->createDirectory($path, $config);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function test_create_directory_fails_when_operation_fails(): void
    {
        $path = 'test/new-directory';
        $config = new Config();

        $this->mockClient
            ->shouldReceive('directoryExists')
            ->once()
            ->with($path)
            ->andReturn(false);

        $this->mockClient
            ->shouldReceive('createDirectory')
            ->once()
            ->with($path, true)
            ->andReturn(false);

        $this->expectException(UnableToCreateDirectory::class);
        $this->expectExceptionMessage('Directory creation failed');

        $this->adapter->createDirectory($path, $config);
    }

    public function test_create_directory_fails_when_client_throws_exception(): void
    {
        $path = 'test/new-directory';
        $config = new Config();

        $this->mockClient
            ->shouldReceive('directoryExists')
            ->once()
            ->with($path)
            ->andReturn(false);

        $this->mockClient
            ->shouldReceive('createDirectory')
            ->once()
            ->with($path, true)
            ->andThrow(new CtFileOperationException('Client error', 'create_directory', $path));

        $this->expectException(UnableToCreateDirectory::class);
        $this->expectExceptionMessage('Client error');

        $this->adapter->createDirectory($path, $config);
    }
  
  // Move file tests

    public function test_move_file_successfully(): void
    {
        $source = 'source.txt';
        $destination = 'destination.txt';
        $config = new Config();

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with($source)
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('moveFile')
            ->once()
            ->with($source, $destination)
            ->andReturn(true);

        $this->adapter->move($source, $destination, $config);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function test_move_file_with_parent_directory_creation(): void
    {
        $source = 'test/source.txt';
        $destination = 'test/deep/nested/destination.txt';
        $config = new Config();

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with($source)
            ->andReturn(true);

        // Should check if parent directory exists
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->once()
            ->with('test/deep/nested')
            ->andReturn(false);

        // Should create parent directory
        $this->mockClient
            ->shouldReceive('createDirectory')
            ->once()
            ->with('test/deep/nested', true)
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('moveFile')
            ->once()
            ->with($source, $destination)
            ->andReturn(true);

        $this->adapter->move($source, $destination, $config);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function test_move_file_fails_when_source_does_not_exist(): void
    {
        $source = 'nonexistent.txt';
        $destination = 'destination.txt';
        $config = new Config();

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with($source)
            ->andReturn(false);

        $this->expectException(UnableToMoveFile::class);

        $this->adapter->move($source, $destination, $config);
    }

    public function test_move_file_fails_when_operation_fails(): void
    {
        $source = 'source.txt';
        $destination = 'destination.txt';
        $config = new Config();

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with($source)
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('moveFile')
            ->once()
            ->with($source, $destination)
            ->andReturn(false);

        $this->expectException(UnableToMoveFile::class);

        $this->adapter->move($source, $destination, $config);
    }

    public function test_move_file_fails_when_client_throws_exception(): void
    {
        $source = 'source.txt';
        $destination = 'destination.txt';
        $config = new Config();

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with($source)
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('moveFile')
            ->once()
            ->with($source, $destination)
            ->andThrow(new CtFileOperationException('Client error', 'move_file', $source));

        $this->expectException(UnableToMoveFile::class);

        $this->adapter->move($source, $destination, $config);
    }

    // Copy file tests

    public function test_copy_file_successfully(): void
    {
        $source = 'source.txt';
        $destination = 'destination.txt';
        $config = new Config();

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with($source)
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('copyFile')
            ->once()
            ->with($source, $destination)
            ->andReturn(true);

        $this->adapter->copy($source, $destination, $config);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function test_copy_file_with_parent_directory_creation(): void
    {
        $source = 'test/source.txt';
        $destination = 'test/deep/nested/destination.txt';
        $config = new Config();

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with($source)
            ->andReturn(true);

        // Should check if parent directory exists
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->once()
            ->with('test/deep/nested')
            ->andReturn(false);

        // Should create parent directory
        $this->mockClient
            ->shouldReceive('createDirectory')
            ->once()
            ->with('test/deep/nested', true)
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('copyFile')
            ->once()
            ->with($source, $destination)
            ->andReturn(true);

        $this->adapter->copy($source, $destination, $config);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function test_copy_file_fails_when_source_does_not_exist(): void
    {
        $source = 'nonexistent.txt';
        $destination = 'destination.txt';
        $config = new Config();

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with($source)
            ->andReturn(false);

        $this->expectException(UnableToCopyFile::class);

        $this->adapter->copy($source, $destination, $config);
    }

    public function test_copy_file_fails_when_operation_fails(): void
    {
        $source = 'source.txt';
        $destination = 'destination.txt';
        $config = new Config();

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with($source)
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('copyFile')
            ->once()
            ->with($source, $destination)
            ->andReturn(false);

        $this->expectException(UnableToCopyFile::class);

        $this->adapter->copy($source, $destination, $config);
    }

    public function test_copy_file_fails_when_client_throws_exception(): void
    {
        $source = 'source.txt';
        $destination = 'destination.txt';
        $config = new Config();

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with($source)
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('copyFile')
            ->once()
            ->with($source, $destination)
            ->andThrow(new CtFileOperationException('Client error', 'copy_file', $source));

        $this->expectException(UnableToCopyFile::class);

        $this->adapter->copy($source, $destination, $config);
    }

    // Test with custom root path configuration

    public function test_manipulation_operations_with_root_path(): void
    {
        $adapter = new CtFileAdapter($this->mockClient, ['root_path' => '/custom/root']);
        $path = 'test/file.txt';

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with('/custom/root/test/file.txt')
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('deleteFile')
            ->once()
            ->with('/custom/root/test/file.txt')
            ->andReturn(true);

        $adapter->delete($path);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    // Test with create_directories disabled

    public function test_move_file_without_creating_parent_directories(): void
    {
        $adapter = new CtFileAdapter($this->mockClient, ['create_directories' => false]);
        $source = 'test/source.txt';
        $destination = 'test/deep/nested/destination.txt';
        $config = new Config();

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with($source)
            ->andReturn(true);

        // Should not check or create parent directories when disabled
        $this->mockClient
            ->shouldNotReceive('directoryExists');
        $this->mockClient
            ->shouldNotReceive('createDirectory');

        $this->mockClient
            ->shouldReceive('moveFile')
            ->once()
            ->with($source, $destination)
            ->andReturn(true);

        $adapter->move($source, $destination, $config);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function test_copy_file_without_creating_parent_directories(): void
    {
        $adapter = new CtFileAdapter($this->mockClient, ['create_directories' => false]);
        $source = 'test/source.txt';
        $destination = 'test/deep/nested/destination.txt';
        $config = new Config();

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with($source)
            ->andReturn(true);

        // Should not check or create parent directories when disabled
        $this->mockClient
            ->shouldNotReceive('directoryExists');
        $this->mockClient
            ->shouldNotReceive('createDirectory');

        $this->mockClient
            ->shouldReceive('copyFile')
            ->once()
            ->with($source, $destination)
            ->andReturn(true);

        $adapter->copy($source, $destination, $config);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }
}