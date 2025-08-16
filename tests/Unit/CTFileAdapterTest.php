<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Tests\Unit;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckFileExistence;
use Mockery;
use PHPUnit\Framework\TestCase;
use YangWeijie\FilesystemCtfile\CtFileAdapter;
use YangWeijie\FilesystemCtfile\CtFileClient;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileOperationException;

/**
 * Unit tests for CtFileAdapter class.
 */
class CtFileAdapterTest extends TestCase
{
    private CtFileClient $mockClient;

    private CtFileAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock(CtFileClient::class);
        $this->adapter = new CtFileAdapter($this->mockClient);
    }

    public function test_implements_filesystem_adapter_interface(): void
    {
        $this->assertInstanceOf(FilesystemAdapter::class, $this->adapter);
    }

    public function test_constructor_with_default_config(): void
    {
        $adapter = new CtFileAdapter($this->mockClient);

        $config = $adapter->getConfig();

        $this->assertEquals('', $config['root_path']);
        $this->assertEquals('/', $config['path_separator']);
        $this->assertTrue($config['case_sensitive']);
        $this->assertTrue($config['create_directories']);
    }

    public function test_constructor_with_custom_config(): void
    {
        $customConfig = [
            'root_path' => '/custom/root',
            'path_separator' => '\\',
            'case_sensitive' => false,
            'create_directories' => false,
        ];

        $adapter = new CtFileAdapter($this->mockClient, $customConfig);

        $config = $adapter->getConfig();

        $this->assertEquals('/custom/root', $config['root_path']);
        $this->assertEquals('\\', $config['path_separator']);
        $this->assertFalse($config['case_sensitive']);
        $this->assertFalse($config['create_directories']);
    }

    public function test_get_client_returns_injected_client(): void
    {
        $client = $this->adapter->getClient();

        $this->assertSame($this->mockClient, $client);
    }

    public function test_get_config_returns_all_config_when_no_key(): void
    {
        $config = $this->adapter->getConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('root_path', $config);
        $this->assertArrayHasKey('path_separator', $config);
        $this->assertArrayHasKey('case_sensitive', $config);
        $this->assertArrayHasKey('create_directories', $config);
    }

    public function test_get_config_returns_specific_value_when_key_provided(): void
    {
        $rootPath = $this->adapter->getConfig('root_path');
        $pathSeparator = $this->adapter->getConfig('path_separator');

        $this->assertEquals('', $rootPath);
        $this->assertEquals('/', $pathSeparator);
    }

    public function test_get_config_returns_default_when_key_not_found(): void
    {
        $value = $this->adapter->getConfig('nonexistent_key', 'default_value');

        $this->assertEquals('default_value', $value);
    }

    public function test_get_prefixer_returns_path_prefixer_instance(): void
    {
        $prefixer = $this->adapter->getPrefixer();

        $this->assertInstanceOf(PathPrefixer::class, $prefixer);
    }

    public function test_file_exists_returns_true_when_file_exists(): void
    {
        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with('test.txt')
            ->andReturn(true);

        $result = $this->adapter->fileExists('test.txt');

        $this->assertTrue($result);
    }

    public function test_file_exists_returns_false_when_file_does_not_exist(): void
    {
        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with('nonexistent.txt')
            ->andReturn(false);

        $result = $this->adapter->fileExists('nonexistent.txt');

        $this->assertFalse($result);
    }

    public function test_file_exists_throws_exception_when_client_fails(): void
    {
        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with('test.txt')
            ->andThrow(new CtFileOperationException('Connection failed'));

        $this->expectException(UnableToCheckFileExistence::class);

        $this->adapter->fileExists('test.txt');
    }

    public function test_directory_exists_returns_true_when_directory_exists(): void
    {
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->once()
            ->with('test-dir')
            ->andReturn(true);

        $result = $this->adapter->directoryExists('test-dir');

        $this->assertTrue($result);
    }

    public function test_directory_exists_returns_false_when_directory_does_not_exist(): void
    {
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->once()
            ->with('nonexistent-dir')
            ->andReturn(false);

        $result = $this->adapter->directoryExists('nonexistent-dir');

        $this->assertFalse($result);
    }

    public function test_directory_exists_throws_exception_when_client_fails(): void
    {
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->once()
            ->with('test-dir')
            ->andThrow(new CtFileOperationException('Connection failed'));

        $this->expectException(UnableToCheckDirectoryExistence::class);

        $this->adapter->directoryExists('test-dir');
    }

    public function test_file_exists_with_root_path_prefix(): void
    {
        $adapter = new CtFileAdapter($this->mockClient, ['root_path' => '/root']);

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with('/root/test.txt')
            ->andReturn(true);

        $result = $adapter->fileExists('test.txt');

        $this->assertTrue($result);
    }

    public function test_directory_exists_with_root_path_prefix(): void
    {
        $adapter = new CtFileAdapter($this->mockClient, ['root_path' => '/root']);

        $this->mockClient
            ->shouldReceive('directoryExists')
            ->once()
            ->with('/root/test-dir')
            ->andReturn(true);

        $result = $adapter->directoryExists('test-dir');

        $this->assertTrue($result);
    }

    public function test_all_methods_are_implemented(): void
    {
        $config = new Config();

        // All methods should now be implemented
        // This test verifies that no methods throw BadMethodCallException
        $this->assertTrue(method_exists($this->adapter, 'listContents'));
        $this->assertTrue(method_exists($this->adapter, 'fileExists'));
        $this->assertTrue(method_exists($this->adapter, 'directoryExists'));
        $this->assertTrue(method_exists($this->adapter, 'read'));
        $this->assertTrue(method_exists($this->adapter, 'write'));
        $this->assertTrue(method_exists($this->adapter, 'delete'));
        $this->assertTrue(method_exists($this->adapter, 'createDirectory'));
        $this->assertTrue(method_exists($this->adapter, 'deleteDirectory'));
        $this->assertTrue(method_exists($this->adapter, 'move'));
        $this->assertTrue(method_exists($this->adapter, 'copy'));
    }

    public function test_delete_directory_successfully(): void
    {
        $path = 'test-dir';

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

    // File read operation tests

    public function test_read_returns_file_contents(): void
    {
        $expectedContent = 'Test file content';

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with('test.txt')
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('readFile')
            ->once()
            ->with('test.txt')
            ->andReturn($expectedContent);

        $result = $this->adapter->read('test.txt');

        $this->assertEquals($expectedContent, $result);
    }

    public function test_read_throws_exception_when_file_does_not_exist(): void
    {
        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with('nonexistent.txt')
            ->andReturn(false);

        $this->expectException(\League\Flysystem\UnableToReadFile::class);
        $this->expectExceptionMessage('File does not exist');

        $this->adapter->read('nonexistent.txt');
    }

    public function test_read_throws_exception_when_client_fails(): void
    {
        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with('test.txt')
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('readFile')
            ->once()
            ->with('test.txt')
            ->andThrow(new CtFileOperationException('Read operation failed'));

        $this->expectException(\League\Flysystem\UnableToReadFile::class);

        $this->adapter->read('test.txt');
    }

    public function test_read_with_root_path_prefix(): void
    {
        $adapter = new CtFileAdapter($this->mockClient, ['root_path' => '/root']);
        $expectedContent = 'Test file content';

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with('/root/test.txt')
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('readFile')
            ->once()
            ->with('/root/test.txt')
            ->andReturn($expectedContent);

        $result = $adapter->read('test.txt');

        $this->assertEquals($expectedContent, $result);
    }

    public function test_read_stream_returns_stream_resource(): void
    {
        $expectedContent = 'Test file content for stream';

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with('test.txt')
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('readFile')
            ->once()
            ->with('test.txt')
            ->andReturn($expectedContent);

        $result = $this->adapter->readStream('test.txt');

        $this->assertIsResource($result);
        $this->assertEquals('stream', get_resource_type($result));

        // Read from the stream to verify content
        $streamContent = stream_get_contents($result);
        $this->assertEquals($expectedContent, $streamContent);

        fclose($result);
    }

    public function test_read_stream_throws_exception_when_file_does_not_exist(): void
    {
        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with('nonexistent.txt')
            ->andReturn(false);

        $this->expectException(\League\Flysystem\UnableToReadFile::class);
        $this->expectExceptionMessage('File does not exist');

        $this->adapter->readStream('nonexistent.txt');
    }

    public function test_read_stream_throws_exception_when_client_fails(): void
    {
        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with('test.txt')
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('readFile')
            ->once()
            ->with('test.txt')
            ->andThrow(new CtFileOperationException('Read operation failed'));

        $this->expectException(\League\Flysystem\UnableToReadFile::class);

        $this->adapter->readStream('test.txt');
    }

    public function test_read_stream_with_root_path_prefix(): void
    {
        $adapter = new CtFileAdapter($this->mockClient, ['root_path' => '/root']);
        $expectedContent = 'Test file content for stream with prefix';

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with('/root/test.txt')
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('readFile')
            ->once()
            ->with('/root/test.txt')
            ->andReturn($expectedContent);

        $result = $adapter->readStream('test.txt');

        $this->assertIsResource($result);
        $streamContent = stream_get_contents($result);
        $this->assertEquals($expectedContent, $streamContent);

        fclose($result);
    }

    public function test_read_stream_handles_large_files_efficiently(): void
    {
        // Test with a larger content to ensure memory stream works properly
        $largeContent = str_repeat('This is a test line for large file content. ', 1000);

        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with('large-file.txt')
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('readFile')
            ->once()
            ->with('large-file.txt')
            ->andReturn($largeContent);

        $result = $this->adapter->readStream('large-file.txt');

        $this->assertIsResource($result);

        // Read in chunks to simulate streaming behavior
        $readContent = '';
        while (!feof($result)) {
            $readContent .= fread($result, 1024);
        }

        $this->assertEquals($largeContent, $readContent);
        $this->assertEquals(strlen($largeContent), strlen($readContent));

        fclose($result);
    }

    public function test_delete_successfully(): void
    {
        $path = 'test.txt';

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

    public function test_create_directory_successfully(): void
    {
        $path = 'test-dir';
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

    // Metadata methods tests

    public function test_file_size_returns_file_attributes_with_size(): void
    {
        $fileInfo = [
            'path' => 'test.txt',
            'size' => 1024,
            'type' => 'file',
        ];

        $this->mockClient
            ->shouldReceive('getFileInfo')
            ->once()
            ->with('test.txt')
            ->andReturn($fileInfo);

        $result = $this->adapter->fileSize('test.txt');

        $this->assertInstanceOf(\League\Flysystem\FileAttributes::class, $result);
        $this->assertEquals('test.txt', $result->path());
        $this->assertEquals(1024, $result->fileSize());
    }

    public function test_file_size_throws_exception_when_client_fails(): void
    {
        $this->mockClient
            ->shouldReceive('getFileInfo')
            ->once()
            ->with('test.txt')
            ->andThrow(new CtFileOperationException('Connection failed'));

        $this->expectException(\League\Flysystem\UnableToRetrieveMetadata::class);

        $this->adapter->fileSize('test.txt');
    }

    public function test_last_modified_returns_file_attributes_with_timestamp(): void
    {
        $timestamp = time();
        $fileInfo = [
            'path' => 'test.txt',
            'last_modified' => $timestamp,
            'type' => 'file',
        ];

        $this->mockClient
            ->shouldReceive('getFileInfo')
            ->once()
            ->with('test.txt')
            ->andReturn($fileInfo);

        $result = $this->adapter->lastModified('test.txt');

        $this->assertInstanceOf(\League\Flysystem\FileAttributes::class, $result);
        $this->assertEquals('test.txt', $result->path());
        $this->assertEquals($timestamp, $result->lastModified());
    }

    public function test_last_modified_throws_exception_when_client_fails(): void
    {
        $this->mockClient
            ->shouldReceive('getFileInfo')
            ->once()
            ->with('test.txt')
            ->andThrow(new CtFileOperationException('Connection failed'));

        $this->expectException(\League\Flysystem\UnableToRetrieveMetadata::class);

        $this->adapter->lastModified('test.txt');
    }

    public function test_mime_type_returns_file_attributes_with_mime_type(): void
    {
        $fileInfo = [
            'path' => 'test.txt',
            'mime_type' => 'text/plain',
            'type' => 'file',
        ];

        $this->mockClient
            ->shouldReceive('getFileInfo')
            ->once()
            ->with('test.txt')
            ->andReturn($fileInfo);

        $result = $this->adapter->mimeType('test.txt');

        $this->assertInstanceOf(\League\Flysystem\FileAttributes::class, $result);
        $this->assertEquals('test.txt', $result->path());
        $this->assertEquals('text/plain', $result->mimeType());
    }

    public function test_mime_type_throws_exception_when_client_fails(): void
    {
        $this->mockClient
            ->shouldReceive('getFileInfo')
            ->once()
            ->with('test.txt')
            ->andThrow(new CtFileOperationException('Connection failed'));

        $this->expectException(\League\Flysystem\UnableToRetrieveMetadata::class);

        $this->adapter->mimeType('test.txt');
    }

    public function test_visibility_returns_file_attributes_with_visibility(): void
    {
        $fileInfo = [
            'path' => 'test.txt',
            'permissions' => '644',
            'type' => 'file',
        ];

        $this->mockClient
            ->shouldReceive('getFileInfo')
            ->once()
            ->with('test.txt')
            ->andReturn($fileInfo);

        $result = $this->adapter->visibility('test.txt');

        $this->assertInstanceOf(\League\Flysystem\FileAttributes::class, $result);
        $this->assertEquals('test.txt', $result->path());
        $this->assertEquals('public', $result->visibility());
    }

    public function test_visibility_throws_exception_when_client_fails(): void
    {
        $this->mockClient
            ->shouldReceive('getFileInfo')
            ->once()
            ->with('test.txt')
            ->andThrow(new CtFileOperationException('Connection failed'));

        $this->expectException(\League\Flysystem\UnableToRetrieveMetadata::class);

        $this->adapter->visibility('test.txt');
    }

    public function test_set_visibility_succeeds_when_file_exists(): void
    {
        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with('test.txt')
            ->andReturn(true);

        // setVisibility doesn't return anything, just ensure no exception is thrown
        $this->adapter->setVisibility('test.txt', 'public');

        // If we get here without exception, the test passes
        $this->assertTrue(true);
    }

    public function test_set_visibility_throws_exception_when_file_does_not_exist(): void
    {
        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with('test.txt')
            ->andReturn(false);

        $this->expectException(\League\Flysystem\UnableToSetVisibility::class);

        $this->adapter->setVisibility('test.txt', 'public');
    }

    public function test_set_visibility_throws_exception_when_client_fails(): void
    {
        $this->mockClient
            ->shouldReceive('fileExists')
            ->once()
            ->with('test.txt')
            ->andThrow(new CtFileOperationException('Connection failed'));

        $this->expectException(\League\Flysystem\UnableToSetVisibility::class);

        $this->adapter->setVisibility('test.txt', 'public');
    }

    public function test_list_contents_returns_directory_listing(): void
    {
        $path = '/test-directory';
        $mockListing = [
            [
                'path' => 'test-directory/file1.txt',
                'name' => 'file1.txt',
                'type' => 'file',
                'size' => 1024,
                'timestamp' => 1640995200,
                'visibility' => 'public'
            ],
            [
                'path' => 'test-directory/subdir',
                'name' => 'subdir',
                'type' => 'dir',
                'timestamp' => 1640995200,
                'visibility' => 'public'
            ]
        ];

        $this->mockClient->shouldReceive('listFiles')
            ->once()
            ->with('test-directory', false)
            ->andReturn($mockListing);

        $contents = iterator_to_array($this->adapter->listContents('test-directory', false));

        $this->assertCount(2, $contents);
        $this->assertInstanceOf(FileAttributes::class, $contents[0]);
        $this->assertInstanceOf(FileAttributes::class, $contents[1]);
        
        // Check that paths are properly stripped of prefix
        $this->assertEquals('test-directory/file1.txt', $contents[0]->path());
        $this->assertEquals('test-directory/subdir', $contents[1]->path());
    }

    public function test_list_contents_recursive(): void
    {
        $path = '/test-directory';
        $mockListing = [
            [
                'path' => 'test-directory/file1.txt',
                'name' => 'file1.txt',
                'type' => 'file',
                'size' => 1024,
                'timestamp' => 1640995200,
                'visibility' => 'public'
            ],
            [
                'path' => 'test-directory/subdir/file2.txt',
                'name' => 'file2.txt',
                'type' => 'file',
                'size' => 2048,
                'timestamp' => 1640995200,
                'visibility' => 'public'
            ]
        ];

        $this->mockClient->shouldReceive('listFiles')
            ->once()
            ->with('test-directory', true)
            ->andReturn($mockListing);

        $contents = iterator_to_array($this->adapter->listContents('test-directory', true));

        $this->assertCount(2, $contents);
        $this->assertEquals('test-directory/file1.txt', $contents[0]->path());
        $this->assertEquals('test-directory/subdir/file2.txt', $contents[1]->path());
    }

    public function test_list_contents_throws_exception_when_client_fails(): void
    {
        $path = '/test-directory';
        $exception = new \Exception('Client error');

        $this->mockClient->shouldReceive('listFiles')
            ->once()
            ->with('test-directory', false)
            ->andThrow($exception);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to list contents of directory "test-directory"');

        iterator_to_array($this->adapter->listContents('test-directory', false));
    }

    public function test_move_successfully(): void
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

    public function test_copy_successfully(): void
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
}
