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

use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use Mockery;
use YangWeijie\FilesystemCtfile\CtFileAdapter;
use YangWeijie\FilesystemCtfile\CtFileClient;
use YangWeijie\FilesystemCtfile\Tests\TestCase;

/**
 * Tests for compatibility with different Flysystem versions.
 *
 * These tests ensure that the CtFileAdapter maintains compatibility
 * with the Flysystem v3.x interface and its evolution.
 */
class FlysystemVersionCompatibilityTest extends TestCase
{
    private CtFileClient $mockClient;

    private CtFileAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock(CtFileClient::class);
        $this->adapter = new CtFileAdapter($this->mockClient);
    }

    public function test_adapter_implements_flysystem_adapter_interface(): void
    {
        expect($this->adapter)->toBeInstanceOf(FilesystemAdapter::class);
    }

    public function test_adapter_has_all_required_interface_methods(): void
    {
        $requiredMethods = [
            'fileExists',
            'directoryExists',
            'write',
            'writeStream',
            'read',
            'readStream',
            'delete',
            'deleteDirectory',
            'createDirectory',
            'setVisibility',
            'visibility',
            'mimeType',
            'lastModified',
            'fileSize',
            'listContents',
            'move',
            'copy',
        ];

        foreach ($requiredMethods as $method) {
            expect(method_exists($this->adapter, $method))->toBeTrue();
        }
    }

    public function test_adapter_method_signatures_match_interface(): void
    {
        $reflection = new \ReflectionClass($this->adapter);

        // Test fileExists signature
        $method = $reflection->getMethod('fileExists');
        expect($method->getParameters())->toHaveCount(1);
        expect($method->getParameters()[0]->getType()?->getName())->toBe('string');
        expect($method->getReturnType()?->getName())->toBe('bool');

        // Test write signature
        $method = $reflection->getMethod('write');
        expect($method->getParameters())->toHaveCount(3);
        expect($method->getParameters()[0]->getType()?->getName())->toBe('string');
        expect($method->getParameters()[1]->getType()?->getName())->toBe('string');
        expect($method->getParameters()[2]->getType()?->getName())->toBe(Config::class);
        expect($method->getReturnType()?->getName())->toBe('void');

        // Test listContents signature
        $method = $reflection->getMethod('listContents');
        expect($method->getParameters())->toHaveCount(2);
        expect($method->getParameters()[0]->getType()?->getName())->toBe('string');
        expect($method->getParameters()[1]->getType()?->getName())->toBe('bool');
        expect($method->getReturnType()?->getName())->toBe('iterable');
    }

    public function test_adapter_works_with_flysystem_config_object(): void
    {
        $config = new Config([
            'visibility' => 'public',
            'directory_visibility' => 'public',
            'custom_option' => 'value',
        ]);

        // These methods should accept Config objects without errors
        expect(fn () => $this->adapter->write('test.txt', 'content', $config))
            ->toThrow(\BadMethodCallException::class); // Expected since not implemented yet

        expect(fn () => $this->adapter->createDirectory('test', $config))
            ->toThrow(\BadMethodCallException::class); // Expected since not implemented yet
    }

    public function test_adapter_integrates_with_path_prefixer(): void
    {
        $config = ['root_path' => '/uploads', 'path_separator' => '/'];
        $adapter = new CtFileAdapter($this->mockClient, $config);

        $prefixer = $adapter->getPrefixer();
        expect($prefixer)->toBeInstanceOf(PathPrefixer::class);

        // Test that prefixer is configured correctly
        $reflection = new \ReflectionClass($prefixer);
        $prefixProperty = $reflection->getProperty('prefix');
        $prefixProperty->setAccessible(true);
        expect($prefixProperty->getValue($prefixer))->toBe('/uploads/');
    }

    public function test_adapter_handles_flysystem_exceptions_correctly(): void
    {
        // Test that adapter throws appropriate Flysystem exceptions
        $this->mockClient
            ->shouldReceive('fileExists')
            ->andThrow(new \RuntimeException('Connection failed'));

        expect(fn () => $this->adapter->fileExists('test.txt'))
            ->toThrow(\League\Flysystem\UnableToCheckFileExistence::class);
    }

    public function test_adapter_config_integration(): void
    {
        $config = [
            'root_path' => '/test',
            'path_separator' => '/',
            'case_sensitive' => false,
            'create_directories' => true,
        ];

        $adapter = new CtFileAdapter($this->mockClient, $config);

        expect($adapter->getConfig('root_path'))->toBe('/test');
        expect($adapter->getConfig('case_sensitive'))->toBeFalse();
        expect($adapter->getConfig('create_directories'))->toBeTrue();
    }

    public function test_adapter_with_minimal_configuration(): void
    {
        $adapter = new CtFileAdapter($this->mockClient);

        // Should work with default configuration
        expect($adapter->getConfig('root_path'))->toBe('');
        expect($adapter->getConfig('path_separator'))->toBe('/');
        expect($adapter->getConfig('case_sensitive'))->toBeTrue();
        expect($adapter->getConfig('create_directories'))->toBeTrue();
    }

    public function test_adapter_maintains_flysystem_v3_compatibility(): void
    {
        // Test that adapter works with Flysystem v3.x features
        $filesystem = new Filesystem($this->adapter);

        // Test that filesystem can be created
        expect($filesystem)->toBeInstanceOf(Filesystem::class);

        // Test that adapter is accessible
        // Note: Flysystem v3 doesn't expose getAdapter() method publicly

        // Test that basic operations are available (even if not implemented)
        expect(method_exists($filesystem, 'fileExists'))->toBeTrue();
        expect(method_exists($filesystem, 'write'))->toBeTrue();
        expect(method_exists($filesystem, 'read'))->toBeTrue();
        expect(method_exists($filesystem, 'delete'))->toBeTrue();
        expect(method_exists($filesystem, 'listContents'))->toBeTrue();
    }

    public function test_adapter_boolean_return_types(): void
    {
        // Test methods that should return boolean values
        $this->mockClient
            ->shouldReceive('fileExists')
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('directoryExists')
            ->andReturn(false);

        $result1 = $this->adapter->fileExists('test.txt');
        $result2 = $this->adapter->directoryExists('test');

        expect($result1)->toBeTrue();
        expect($result2)->toBeFalse();
    }

    public function test_adapter_void_return_types(): void
    {
        // Test methods that should return void (when implemented)
        // For now, they throw BadMethodCallException

        $config = new Config();

        expect(fn () => $this->adapter->write('test.txt', 'content', $config))
            ->toThrow(\BadMethodCallException::class);

        expect(fn () => $this->adapter->delete('test.txt'))
            ->toThrow(\BadMethodCallException::class);

        expect(fn () => $this->adapter->createDirectory('test', $config))
            ->toThrow(\BadMethodCallException::class);
    }

    public function test_adapter_iterable_return_type(): void
    {
        // Test listContents returns iterable (when implemented)
        expect(fn () => $this->adapter->listContents('/', false))
            ->toThrow(\BadMethodCallException::class);
    }

    public function test_adapter_file_attributes_return_type(): void
    {
        // Test methods that return FileAttributes (these are implemented)
        $this->mockClient->shouldReceive('getFileInfo')->andReturn([
            'size' => 100,
            'modified' => time(),
            'mime_type' => 'text/plain',
            'permissions' => '644',
        ]);

        $visibility = $this->adapter->visibility('test.txt');
        expect($visibility)->toBeInstanceOf(\League\Flysystem\FileAttributes::class);

        $mimeType = $this->adapter->mimeType('test.txt');
        expect($mimeType)->toBeInstanceOf(\League\Flysystem\FileAttributes::class);

        $lastModified = $this->adapter->lastModified('test.txt');
        expect($lastModified)->toBeInstanceOf(\League\Flysystem\FileAttributes::class);

        $fileSize = $this->adapter->fileSize('test.txt');
        expect($fileSize)->toBeInstanceOf(\League\Flysystem\FileAttributes::class);
    }

    public function test_adapter_stream_handling_compatibility(): void
    {
        // Test that adapter can handle stream resources
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'test content');
        rewind($stream);

        $config = new Config();

        // writeStream is not implemented
        expect(fn () => $this->adapter->writeStream('test.txt', $stream, $config))
            ->toThrow(\BadMethodCallException::class);

        // readStream is implemented
        $this->mockClient->shouldReceive('fileExists')->with('test.txt')->andReturn(true);
        $this->mockClient->shouldReceive('readFile')->with('test.txt')->andReturn('test content');

        $resultStream = $this->adapter->readStream('test.txt');
        expect($resultStream)->toBeResource();

        fclose($stream);
    }
}
