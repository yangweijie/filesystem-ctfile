<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Tests\Unit;

use League\Flysystem\Config;
use League\Flysystem\UnableToWriteFile;
use Mockery;
use PHPUnit\Framework\TestCase;
use YangWeijie\FilesystemCtfile\CacheManager;
use YangWeijie\FilesystemCtfile\CtFileAdapter;
use YangWeijie\FilesystemCtfile\CtFileClient;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileOperationException;
use YangWeijie\FilesystemCtfile\RetryHandler;

/**
 * Unit tests for CtFileAdapter write operations.
 */
class CtFileAdapterWriteTest extends TestCase
{
    private CtFileClient $mockClient;
    private CacheManager $mockCacheManager;
    private RetryHandler $mockRetryHandler;
    private CtFileAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock(CtFileClient::class);
        $this->mockCacheManager = Mockery::mock(CacheManager::class);
        $this->mockRetryHandler = Mockery::mock(RetryHandler::class);

        $this->adapter = new CtFileAdapter(
            $this->mockClient,
            ['create_directories' => true],
            $this->mockCacheManager,
            $this->mockRetryHandler
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testWriteFileSuccessfully(): void
    {
        $path = 'test/file.txt';
        $contents = 'Hello, World!';
        $config = new Config();

        // Mock retry handler to execute operation directly
        $this->mockRetryHandler
            ->shouldReceive('execute')
            ->times(3) // Once for directory check, once for directory creation, once for write
            ->andReturnUsing(fn (callable $operation) => $operation());

        // Mock directory existence check (parent directory doesn't exist)
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->with('test')
            ->once()
            ->andReturn(false);

        // Mock directory creation
        $this->mockClient
            ->shouldReceive('createDirectory')
            ->with('test', true)
            ->once()
            ->andReturn(true);

        // Mock file write
        $this->mockClient
            ->shouldReceive('writeFile')
            ->with('test/file.txt', $contents)
            ->once()
            ->andReturn(true);

        // Mock cache invalidation
        $this->mockCacheManager
            ->shouldReceive('isEnabled')
            ->once()
            ->andReturn(true);

        $this->mockCacheManager
            ->shouldReceive('invalidatePath')
            ->with($path)
            ->once();

        $this->adapter->write($path, $contents, $config);
        
        // Assert that the operation completed without throwing exceptions
        $this->assertTrue(true);
    }

    public function testWriteFileWithExistingDirectory(): void
    {
        $path = 'existing/file.txt';
        $contents = 'Test content';
        $config = new Config();

        // Mock retry handler to execute operation directly
        $this->mockRetryHandler
            ->shouldReceive('execute')
            ->times(2) // Once for directory check, once for write
            ->andReturnUsing(fn (callable $operation) => $operation());

        // Mock directory existence check (parent directory exists)
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->with('existing')
            ->once()
            ->andReturn(true);

        // Directory creation should not be called
        $this->mockClient
            ->shouldNotReceive('createDirectory');

        // Mock file write
        $this->mockClient
            ->shouldReceive('writeFile')
            ->with('existing/file.txt', $contents)
            ->once()
            ->andReturn(true);

        // Mock cache invalidation
        $this->mockCacheManager
            ->shouldReceive('isEnabled')
            ->once()
            ->andReturn(true);

        $this->mockCacheManager
            ->shouldReceive('invalidatePath')
            ->with($path)
            ->once();

        $this->adapter->write($path, $contents, $config);
        
        // Assert that the operation completed without throwing exceptions
        $this->assertTrue(true);
    }

    public function testWriteFileWithoutDirectoryCreation(): void
    {
        $adapter = new CtFileAdapter(
            $this->mockClient,
            ['create_directories' => false],
            $this->mockCacheManager,
            $this->mockRetryHandler
        );

        $path = 'test/file.txt';
        $contents = 'Hello, World!';
        $config = new Config();

        // Mock retry handler to execute operation directly
        $this->mockRetryHandler
            ->shouldReceive('execute')
            ->once() // Only for write operation
            ->andReturnUsing(fn (callable $operation) => $operation());

        // Directory operations should not be called
        $this->mockClient
            ->shouldNotReceive('directoryExists');
        $this->mockClient
            ->shouldNotReceive('createDirectory');

        // Mock file write
        $this->mockClient
            ->shouldReceive('writeFile')
            ->with('test/file.txt', $contents)
            ->once()
            ->andReturn(true);

        // Mock cache invalidation
        $this->mockCacheManager
            ->shouldReceive('isEnabled')
            ->once()
            ->andReturn(true);

        $this->mockCacheManager
            ->shouldReceive('invalidatePath')
            ->with($path)
            ->once();

        $adapter->write($path, $contents, $config);
        
        // Assert that the operation completed without throwing exceptions
        $this->assertTrue(true);
    }

    public function testWriteFileFailsWhenClientReturnsFalse(): void
    {
        $path = 'test/file.txt';
        $contents = 'Hello, World!';
        $config = new Config();

        // Mock retry handler to execute operation directly
        $this->mockRetryHandler
            ->shouldReceive('execute')
            ->times(2)
            ->andReturnUsing(fn (callable $operation) => $operation());

        // Mock directory operations
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->with('test')
            ->once()
            ->andReturn(true);

        // Mock file write failure
        $this->mockClient
            ->shouldReceive('writeFile')
            ->with('test/file.txt', $contents)
            ->once()
            ->andReturn(false);

        $this->expectException(UnableToWriteFile::class);
        $this->expectExceptionMessage('Unable to write file at location: test/file.txt. Write operation failed');

        $this->adapter->write($path, $contents, $config);
    }

    public function testWriteFileThrowsExceptionOnClientError(): void
    {
        $path = 'test/file.txt';
        $contents = 'Hello, World!';
        $config = new Config();

        // Mock retry handler to execute operation directly
        $this->mockRetryHandler
            ->shouldReceive('execute')
            ->times(2)
            ->andReturnUsing(fn (callable $operation) => $operation());

        // Mock directory operations
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->with('test')
            ->once()
            ->andReturn(true);

        // Mock file write exception
        $this->mockClient
            ->shouldReceive('writeFile')
            ->with('test/file.txt', $contents)
            ->once()
            ->andThrow(new CtFileOperationException('Connection failed', 'write_file', $path));

        $this->expectException(UnableToWriteFile::class);
        $this->expectExceptionMessage('Unable to write file at location: test/file.txt. Connection failed');

        $this->adapter->write($path, $contents, $config);
    }

    public function testWriteFileFailsWhenDirectoryCreationFails(): void
    {
        $path = 'test/file.txt';
        $contents = 'Hello, World!';
        $config = new Config();

        // Mock retry handler to execute operation directly
        $this->mockRetryHandler
            ->shouldReceive('execute')
            ->times(2)
            ->andReturnUsing(fn (callable $operation) => $operation());

        // Mock directory existence check (parent directory doesn't exist)
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->with('test')
            ->once()
            ->andReturn(false);

        // Mock directory creation failure
        $this->mockClient
            ->shouldReceive('createDirectory')
            ->with('test', true)
            ->once()
            ->andThrow(new CtFileOperationException('Failed to create directory', 'create_directory', 'test'));

        $this->expectException(UnableToWriteFile::class);
        $this->expectExceptionMessage('Unable to write file at location: test/file.txt. Failed to create directory');

        $this->adapter->write($path, $contents, $config);
    }

    public function testWriteStreamSuccessfully(): void
    {
        $path = 'test/stream.txt';
        $contents = 'Stream content';
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $config = new Config();

        // Mock retry handler to execute operation directly
        $this->mockRetryHandler
            ->shouldReceive('execute')
            ->times(2)
            ->andReturnUsing(fn (callable $operation) => $operation());

        // Mock directory operations
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->with('test')
            ->once()
            ->andReturn(true);

        // Mock file write
        $this->mockClient
            ->shouldReceive('writeFile')
            ->with('test/stream.txt', $contents)
            ->once()
            ->andReturn(true);

        // Mock cache invalidation
        $this->mockCacheManager
            ->shouldReceive('isEnabled')
            ->once()
            ->andReturn(true);

        $this->mockCacheManager
            ->shouldReceive('invalidatePath')
            ->with($path)
            ->once();

        $this->adapter->writeStream($path, $stream, $config);

        fclose($stream);
        
        // Assert that the operation completed without throwing exceptions
        $this->assertTrue(true);
    }

    public function testWriteStreamWithInvalidResource(): void
    {
        $path = 'test/stream.txt';
        $invalidResource = 'not a resource';
        $config = new Config();

        $this->expectException(UnableToWriteFile::class);
        $this->expectExceptionMessage('Unable to write file at location: test/stream.txt. Contents must be a resource');

        $this->adapter->writeStream($path, $invalidResource, $config);
    }

    public function testWriteStreamWithDirectoryCreation(): void
    {
        $path = 'new/directory/stream.txt';
        $contents = 'Stream content with directory creation';
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $config = new Config();

        // Mock retry handler to execute operation directly
        $this->mockRetryHandler
            ->shouldReceive('execute')
            ->times(3) // Directory check, directory creation, write
            ->andReturnUsing(fn (callable $operation) => $operation());

        // Mock directory operations
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->with('new/directory')
            ->once()
            ->andReturn(false);

        $this->mockClient
            ->shouldReceive('createDirectory')
            ->with('new/directory', true)
            ->once()
            ->andReturn(true);

        // Mock file write
        $this->mockClient
            ->shouldReceive('writeFile')
            ->with('new/directory/stream.txt', $contents)
            ->once()
            ->andReturn(true);

        // Mock cache invalidation
        $this->mockCacheManager
            ->shouldReceive('isEnabled')
            ->once()
            ->andReturn(true);

        $this->mockCacheManager
            ->shouldReceive('invalidatePath')
            ->with($path)
            ->once();

        $this->adapter->writeStream($path, $stream, $config);

        fclose($stream);
        
        // Assert that the operation completed without throwing exceptions
        $this->assertTrue(true);
    }

    public function testWriteStreamFailsWhenClientReturnsFalse(): void
    {
        $path = 'test/stream.txt';
        $contents = 'Stream content';
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $config = new Config();

        // Mock retry handler to execute operation directly
        $this->mockRetryHandler
            ->shouldReceive('execute')
            ->times(2)
            ->andReturnUsing(fn (callable $operation) => $operation());

        // Mock directory operations
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->with('test')
            ->once()
            ->andReturn(true);

        // Mock file write failure
        $this->mockClient
            ->shouldReceive('writeFile')
            ->with('test/stream.txt', $contents)
            ->once()
            ->andReturn(false);

        $this->expectException(UnableToWriteFile::class);
        $this->expectExceptionMessage('Unable to write file at location: test/stream.txt. Write stream operation failed');

        $this->adapter->writeStream($path, $stream, $config);

        fclose($stream);
    }

    public function testWriteStreamThrowsExceptionOnClientError(): void
    {
        $path = 'test/stream.txt';
        $contents = 'Stream content';
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $config = new Config();

        // Mock retry handler to execute operation directly
        $this->mockRetryHandler
            ->shouldReceive('execute')
            ->times(2)
            ->andReturnUsing(fn (callable $operation) => $operation());

        // Mock directory operations
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->with('test')
            ->once()
            ->andReturn(true);

        // Mock file write exception
        $this->mockClient
            ->shouldReceive('writeFile')
            ->with('test/stream.txt', $contents)
            ->once()
            ->andThrow(new CtFileOperationException('Network error', 'write_file', $path));

        $this->expectException(UnableToWriteFile::class);
        $this->expectExceptionMessage('Unable to write file at location: test/stream.txt. Network error');

        $this->adapter->writeStream($path, $stream, $config);

        fclose($stream);
    }

    public function testWriteWithRootPath(): void
    {
        $adapter = new CtFileAdapter(
            $this->mockClient,
            ['root_path' => '/uploads', 'create_directories' => true],
            $this->mockCacheManager,
            $this->mockRetryHandler
        );

        $path = 'file.txt';
        $contents = 'Root path test';
        $config = new Config();

        // Mock retry handler to execute operation directly
        $this->mockRetryHandler
            ->shouldReceive('execute')
            ->times(2)
            ->andReturnUsing(fn (callable $operation) => $operation());

        // Mock directory operations with prefixed path
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->with('/uploads')
            ->once()
            ->andReturn(true);

        // Mock file write with prefixed path
        $this->mockClient
            ->shouldReceive('writeFile')
            ->with('/uploads/file.txt', $contents)
            ->once()
            ->andReturn(true);

        // Mock cache invalidation
        $this->mockCacheManager
            ->shouldReceive('isEnabled')
            ->once()
            ->andReturn(true);

        $this->mockCacheManager
            ->shouldReceive('invalidatePath')
            ->with($path)
            ->once();

        $adapter->write($path, $contents, $config);
        
        // Assert that the operation completed without throwing exceptions
        $this->assertTrue(true);
    }

    public function testWriteWithoutCacheManager(): void
    {
        $adapter = new CtFileAdapter($this->mockClient, ['create_directories' => true]);

        $path = 'test/file.txt';
        $contents = 'No cache test';
        $config = new Config();

        // Mock directory operations
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->with('test')
            ->once()
            ->andReturn(true);

        // Mock file write
        $this->mockClient
            ->shouldReceive('writeFile')
            ->with('test/file.txt', $contents)
            ->once()
            ->andReturn(true);

        // No cache operations should be called
        $this->mockCacheManager
            ->shouldNotReceive('isEnabled');
        $this->mockCacheManager
            ->shouldNotReceive('invalidatePath');

        $adapter->write($path, $contents, $config);
        
        // Assert that the operation completed without throwing exceptions
        $this->assertTrue(true);
    }

    public function testWriteWithoutRetryHandler(): void
    {
        $adapter = new CtFileAdapter(
            $this->mockClient,
            ['create_directories' => true],
            $this->mockCacheManager
        );

        $path = 'test/file.txt';
        $contents = 'No retry test';
        $config = new Config();

        // Mock directory operations (called directly without retry handler)
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->with('test')
            ->once()
            ->andReturn(true);

        // Mock file write (called directly without retry handler)
        $this->mockClient
            ->shouldReceive('writeFile')
            ->with('test/file.txt', $contents)
            ->once()
            ->andReturn(true);

        // Mock cache invalidation
        $this->mockCacheManager
            ->shouldReceive('isEnabled')
            ->once()
            ->andReturn(true);

        $this->mockCacheManager
            ->shouldReceive('invalidatePath')
            ->with($path)
            ->once();

        $adapter->write($path, $contents, $config);
        
        // Assert that the operation completed without throwing exceptions
        $this->assertTrue(true);
    }

    public function testWriteEmptyContent(): void
    {
        $path = 'test/empty.txt';
        $contents = '';
        $config = new Config();

        // Mock retry handler to execute operation directly
        $this->mockRetryHandler
            ->shouldReceive('execute')
            ->times(2)
            ->andReturnUsing(fn (callable $operation) => $operation());

        // Mock directory operations
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->with('test')
            ->once()
            ->andReturn(true);

        // Mock file write with empty content
        $this->mockClient
            ->shouldReceive('writeFile')
            ->with('test/empty.txt', '')
            ->once()
            ->andReturn(true);

        // Mock cache invalidation
        $this->mockCacheManager
            ->shouldReceive('isEnabled')
            ->once()
            ->andReturn(true);

        $this->mockCacheManager
            ->shouldReceive('invalidatePath')
            ->with($path)
            ->once();

        $this->adapter->write($path, $contents, $config);
        
        // Assert that the operation completed without throwing exceptions
        $this->assertTrue(true);
    }

    public function testWriteLargeContent(): void
    {
        $path = 'test/large.txt';
        $contents = str_repeat('Large content test. ', 10000); // ~200KB
        $config = new Config();

        // Mock retry handler to execute operation directly
        $this->mockRetryHandler
            ->shouldReceive('execute')
            ->times(2)
            ->andReturnUsing(fn (callable $operation) => $operation());

        // Mock directory operations
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->with('test')
            ->once()
            ->andReturn(true);

        // Mock file write with large content
        $this->mockClient
            ->shouldReceive('writeFile')
            ->with('test/large.txt', $contents)
            ->once()
            ->andReturn(true);

        // Mock cache invalidation
        $this->mockCacheManager
            ->shouldReceive('isEnabled')
            ->once()
            ->andReturn(true);

        $this->mockCacheManager
            ->shouldReceive('invalidatePath')
            ->with($path)
            ->once();

        $this->adapter->write($path, $contents, $config);
        
        // Assert that the operation completed without throwing exceptions
        $this->assertTrue(true);
    }
}