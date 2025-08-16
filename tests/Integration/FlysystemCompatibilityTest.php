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
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckFileExistence;
use Mockery;
use YangWeijie\FilesystemCtfile\CtFileAdapter;
use YangWeijie\FilesystemCtfile\CtFileClient;
use YangWeijie\FilesystemCtfile\Tests\TestCase;

/**
 * Integration tests for Flysystem compatibility.
 *
 * These tests verify that the CtFileAdapter works correctly when used
 * through the Flysystem Filesystem interface, ensuring full compatibility
 * with the Flysystem ecosystem.
 */
class FlysystemCompatibilityTest extends TestCase
{
    private Filesystem $filesystem;

    private CtFileClient $mockClient;

    private CtFileAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock(CtFileClient::class);
        $this->adapter = new CtFileAdapter($this->mockClient);
        $this->filesystem = new Filesystem($this->adapter);
    }

    public function test_filesystem_can_be_instantiated_with_ctfile_adapter(): void
    {
        expect($this->filesystem)->toBeInstanceOf(Filesystem::class);
        // Note: Flysystem v3 doesn't expose getAdapter() method publicly
        // We can verify the adapter works by testing operations
        expect(method_exists($this->filesystem, 'fileExists'))->toBeTrue();
    }

    public function test_filesystem_file_exists_delegates_to_adapter(): void
    {
        $this->mockClient
            ->shouldReceive('fileExists')
            ->with('test.txt')
            ->once()
            ->andReturn(true);

        $result = $this->filesystem->fileExists('test.txt');

        expect($result)->toBeTrue();
    }

    public function test_filesystem_file_exists_handles_exceptions(): void
    {
        $this->mockClient
            ->shouldReceive('fileExists')
            ->with('test.txt')
            ->once()
            ->andThrow(new \RuntimeException('Connection failed'));

        expect(fn () => $this->filesystem->fileExists('test.txt'))
            ->toThrow(UnableToCheckFileExistence::class);
    }

    public function test_filesystem_directory_exists_delegates_to_adapter(): void
    {
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->with('uploads')
            ->once()
            ->andReturn(true);

        $result = $this->filesystem->directoryExists('uploads');

        expect($result)->toBeTrue();
    }

    public function test_filesystem_directory_exists_handles_exceptions(): void
    {
        $this->mockClient
            ->shouldReceive('directoryExists')
            ->with('uploads')
            ->once()
            ->andThrow(new \RuntimeException('Connection failed'));

        expect(fn () => $this->filesystem->directoryExists('uploads'))
            ->toThrow(UnableToCheckDirectoryExistence::class);
    }

    public function test_filesystem_write_throws_not_implemented_exception(): void
    {
        expect(fn () => $this->filesystem->write('test.txt', 'content'))
            ->toThrow(\BadMethodCallException::class, 'Method not yet implemented');
    }

    public function test_filesystem_write_stream_throws_not_implemented_exception(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'content');
        rewind($stream);

        expect(fn () => $this->filesystem->writeStream('test.txt', $stream))
            ->toThrow(\BadMethodCallException::class, 'Method not yet implemented');

        fclose($stream);
    }

    public function test_filesystem_read_delegates_to_adapter(): void
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

        $result = $this->filesystem->read('test.txt');

        expect($result)->toBe($expectedContent);
    }

    public function test_filesystem_read_stream_delegates_to_adapter(): void
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

        $result = $this->filesystem->readStream('test.txt');

        expect($result)->toBeResource();
        expect(get_resource_type($result))->toBe('stream');

        $streamContent = stream_get_contents($result);
        expect($streamContent)->toBe($expectedContent);

        fclose($result);
    }

    public function test_filesystem_delete_throws_not_implemented_exception(): void
    {
        expect(fn () => $this->filesystem->delete('test.txt'))
            ->toThrow(\BadMethodCallException::class, 'Method not yet implemented');
    }

    public function test_filesystem_delete_directory_throws_not_implemented_exception(): void
    {
        expect(fn () => $this->filesystem->deleteDirectory('uploads'))
            ->toThrow(\BadMethodCallException::class, 'Method not yet implemented');
    }

    public function test_filesystem_create_directory_throws_not_implemented_exception(): void
    {
        expect(fn () => $this->filesystem->createDirectory('uploads'))
            ->toThrow(\BadMethodCallException::class, 'Method not yet implemented');
    }

    public function test_filesystem_set_visibility_delegates_to_adapter(): void
    {
        $this->mockClient->shouldReceive('fileExists')->with('test.txt')->andReturn(true);
        $this->mockClient->shouldReceive('getFileInfo')->with('test.txt')->andReturn(['permissions' => '644']);

        // Test that setVisibility works without throwing exceptions
        $this->filesystem->setVisibility('test.txt', 'public');
        expect(true)->toBeTrue(); // If we get here, no exception was thrown
    }

    public function test_filesystem_visibility_delegates_to_adapter(): void
    {
        $this->mockClient->shouldReceive('getFileInfo')->with('test.txt')->andReturn([
            'permissions' => '644',
            'size' => 100,
            'modified' => time(),
        ]);

        $visibility = $this->filesystem->visibility('test.txt');
        expect($visibility)->toBeString();
    }

    public function test_filesystem_mime_type_delegates_to_adapter(): void
    {
        $this->mockClient->shouldReceive('getFileInfo')->with('test.txt')->andReturn([
            'mime_type' => 'text/plain',
            'size' => 100,
            'modified' => time(),
        ]);

        $mimeType = $this->filesystem->mimeType('test.txt');
        expect($mimeType)->toBeString();
    }

    public function test_filesystem_last_modified_delegates_to_adapter(): void
    {
        $this->mockClient->shouldReceive('getFileInfo')->with('test.txt')->andReturn([
            'modified' => time(),
            'size' => 100,
        ]);

        $lastModified = $this->filesystem->lastModified('test.txt');
        expect($lastModified)->toBeInt();
    }

    public function test_filesystem_file_size_delegates_to_adapter(): void
    {
        $this->mockClient->shouldReceive('getFileInfo')->with('test.txt')->andReturn([
            'size' => 100,
            'modified' => time(),
        ]);

        $fileSize = $this->filesystem->fileSize('test.txt');
        expect($fileSize)->toBeInt();
    }

    public function test_filesystem_list_contents_throws_not_implemented_exception(): void
    {
        expect(fn () => $this->filesystem->listContents('/'))
            ->toThrow(\BadMethodCallException::class, 'Method not yet implemented');
    }

    public function test_filesystem_move_throws_not_implemented_exception(): void
    {
        expect(fn () => $this->filesystem->move('old.txt', 'new.txt'))
            ->toThrow(\BadMethodCallException::class, 'Method not yet implemented');
    }

    public function test_filesystem_copy_throws_not_implemented_exception(): void
    {
        expect(fn () => $this->filesystem->copy('source.txt', 'destination.txt'))
            ->toThrow(\BadMethodCallException::class, 'Method not yet implemented');
    }

    public function test_filesystem_has_method_delegates_to_file_exists(): void
    {
        $this->mockClient
            ->shouldReceive('fileExists')
            ->with('test.txt')
            ->once()
            ->andReturn(true);

        $result = $this->filesystem->has('test.txt');

        expect($result)->toBeTrue();
    }

    public function test_filesystem_with_config_options(): void
    {
        $config = [
            'root_path' => '/uploads',
            'path_separator' => '/',
            'case_sensitive' => false,
        ];

        $adapter = new CtFileAdapter($this->mockClient, $config);
        $filesystem = new Filesystem($adapter);

        // Note: Flysystem v3 doesn't expose getAdapter() method publicly
        expect($adapter->getConfig('root_path'))->toBe('/uploads');
        expect($adapter->getConfig('case_sensitive'))->toBeFalse();
    }

    public function test_filesystem_path_prefixing_with_root_path(): void
    {
        $config = ['root_path' => '/uploads'];
        $adapter = new CtFileAdapter($this->mockClient, $config);
        $filesystem = new Filesystem($adapter);

        $this->mockClient
            ->shouldReceive('fileExists')
            ->with('/uploads/test.txt')
            ->once()
            ->andReturn(true);

        $result = $filesystem->fileExists('test.txt');

        expect($result)->toBeTrue();
    }

    public function test_filesystem_handles_empty_paths(): void
    {
        $this->mockClient
            ->shouldReceive('fileExists')
            ->with('')
            ->once()
            ->andReturn(false);

        $result = $this->filesystem->fileExists('');

        expect($result)->toBeFalse();
    }

    public function test_filesystem_handles_nested_paths(): void
    {
        $this->mockClient
            ->shouldReceive('fileExists')
            ->with('folder/subfolder/test.txt')
            ->once()
            ->andReturn(true);

        $result = $this->filesystem->fileExists('folder/subfolder/test.txt');

        expect($result)->toBeTrue();
    }

    public function test_filesystem_adapter_configuration_is_accessible(): void
    {
        $config = [
            'root_path' => '/test',
            'custom_option' => 'value',
        ];

        $adapter = new CtFileAdapter($this->mockClient, $config);
        $filesystem = new Filesystem($adapter);

        expect($adapter->getConfig('root_path'))->toBe('/test');
        expect($adapter->getConfig('custom_option'))->toBe('value');
        expect($adapter->getConfig('nonexistent', 'default'))->toBe('default');
    }

    public function test_filesystem_adapter_client_is_accessible(): void
    {
        expect($this->adapter->getClient())->toBe($this->mockClient);
    }

    public function test_filesystem_adapter_prefixer_is_configured(): void
    {
        $prefixer = $this->adapter->getPrefixer();

        expect($prefixer)->toBeInstanceOf(\League\Flysystem\PathPrefixer::class);
    }
}
