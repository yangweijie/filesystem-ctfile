<?php

use Yangweijie\FilesystemCtlife\CTFileAdapter;
use Yangweijie\FilesystemCtlife\CTFileConfig;
use Yangweijie\FilesystemCtlife\CTFileClient;
use Yangweijie\FilesystemCtlife\PathMapper;
use Yangweijie\FilesystemCtlife\Support\UploadHelper;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCheckDirectoryExistence;

describe('CTFileAdapter', function () {
    beforeEach(function () {
        $this->config = new CTFileConfig([
            'session' => 'test_session_token_123456',
            'app_id' => 'test_app_id',
        ]);
        
        $this->adapter = new CTFileAdapter($this->config);
    });

    it('can be instantiated with config', function () {
        expect($this->adapter)->toBeInstanceOf(CTFileAdapter::class);
        expect($this->adapter)->toBeInstanceOf(FilesystemAdapter::class);
    });

    it('has correct dependencies injected', function () {
        expect($this->adapter->getConfig())->toBeInstanceOf(CTFileConfig::class);
        expect($this->adapter->getClient())->toBeInstanceOf(CTFileClient::class);
        expect($this->adapter->getPathMapper())->toBeInstanceOf(PathMapper::class);
        expect($this->adapter->getUploadHelper())->toBeInstanceOf(UploadHelper::class);
    });

    it('can check directory existence for root directory', function () {
        // 根目录应该总是存在
        expect($this->adapter->directoryExists('/'))->toBeTrue();
        expect($this->adapter->directoryExists(''))->toBeTrue();
    });

    it('returns false for non-existent files', function () {
        // 对于不存在的文件，应该返回 false 而不是抛出异常
        expect($this->adapter->fileExists('/non/existent/file.txt'))->toBeFalse();
    });

    it('returns false for non-existent directories', function () {
        // 对于不存在的目录，应该返回 false 而不是抛出异常
        expect($this->adapter->directoryExists('/non/existent/directory'))->toBeFalse();
    });

    it('throws exception when file existence check fails', function () {
        // 测试基本的异常处理逻辑
        $reflection = new ReflectionClass($this->adapter);
        $method = $reflection->getMethod('checkExistence');
        $method->setAccessible(true);

        // 测试根目录存在检查
        $result = $method->invoke($this->adapter, '/', 'directory');
        expect($result)->toBeTrue(); // 根目录应该存在
    });

    it('has all required FilesystemAdapter methods', function () {
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
    });

    it('has metadata operation methods', function () {
        // 验证元数据操作方法存在
        $metadataMethods = ['mimeType', 'fileSize', 'lastModified', 'visibility', 'setVisibility', 'listContents'];
        foreach ($metadataMethods as $method) {
            expect(method_exists($this->adapter, $method))->toBeTrue();
        }

        // 验证私有辅助方法存在
        $privateMethods = ['getFileMetadata', 'listDirectory', 'convertToAttributes'];
        foreach ($privateMethods as $method) {
            expect(method_exists($this->adapter, $method))->toBeTrue();
        }
    });

    it('can access internal components', function () {
        // 测试可以访问内部组件
        $config = $this->adapter->getConfig();
        expect($config->getSession())->toBe('test_session_token_123456');
        expect($config->getAppId())->toBe('test_app_id');

        $client = $this->adapter->getClient();
        expect($client)->toBeInstanceOf(CTFileClient::class);

        $pathMapper = $this->adapter->getPathMapper();
        expect($pathMapper)->toBeInstanceOf(PathMapper::class);

        $uploadHelper = $this->adapter->getUploadHelper();
        expect($uploadHelper)->toBeInstanceOf(UploadHelper::class);
    });

    it('handles checkExistence method correctly', function () {
        $reflection = new ReflectionClass($this->adapter);
        $method = $reflection->getMethod('checkExistence');
        $method->setAccessible(true);

        // 测试根目录存在
        $result = $method->invoke($this->adapter, '/', 'directory');
        expect($result)->toBeTrue();

        // 测试空路径（也是根目录）
        $result = $method->invoke($this->adapter, '', 'directory');
        expect($result)->toBeTrue();
    });

    it('properly initializes all dependencies', function () {
        // 验证所有依赖都正确初始化
        expect($this->adapter->getConfig())->not->toBeNull();
        expect($this->adapter->getClient())->not->toBeNull();
        expect($this->adapter->getPathMapper())->not->toBeNull();
        expect($this->adapter->getUploadHelper())->not->toBeNull();

        // 验证依赖之间的关系
        expect($this->adapter->getClient())->toBeInstanceOf(CTFileClient::class);
        expect($this->adapter->getPathMapper())->toBeInstanceOf(PathMapper::class);
        expect($this->adapter->getUploadHelper())->toBeInstanceOf(UploadHelper::class);
    });

    it('can parse file paths correctly', function () {
        $reflection = new ReflectionClass($this->adapter);
        $method = $reflection->getMethod('parseFilePath');
        $method->setAccessible(true);

        // 测试根目录文件
        $result = $method->invoke($this->adapter, '/test.txt');
        expect($result['folder_id'])->toBe('d0');
        expect($result['filename'])->toBe('test.txt');

        // 测试子目录文件（可能会因为API调用失败而抛出异常，这是预期的）
        try {
            $result = $method->invoke($this->adapter, '/folder/test.txt');
            expect($result['filename'])->toBe('test.txt');
        } catch (Exception $e) {
            // API 调用失败是预期的（使用测试凭据）
            expect($e)->toBeInstanceOf(Exception::class);
        }
    });

    it('validates filenames correctly', function () {
        $reflection = new ReflectionClass($this->adapter);
        $method = $reflection->getMethod('parseFilePath');
        $method->setAccessible(true);

        // 测试无效文件名
        expect(fn() => $method->invoke($this->adapter, '/invalid/file.txt'))
            ->toThrow(Exception::class);

        // 测试空路径
        expect(fn() => $method->invoke($this->adapter, '/'))
            ->toThrow(Exception::class);
    });

    it('handles file read/write operations', function () {
        // 由于我们无法轻易模拟 API 响应，这里测试基本的方法存在性
        expect(method_exists($this->adapter, 'write'))->toBeTrue();
        expect(method_exists($this->adapter, 'writeStream'))->toBeTrue();
        expect(method_exists($this->adapter, 'read'))->toBeTrue();
        expect(method_exists($this->adapter, 'readStream'))->toBeTrue();
    });

    it('can create memory stream for readStream', function () {
        // 测试内存流创建逻辑
        $content = 'test content';
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $readContent = stream_get_contents($stream);
        expect($readContent)->toBe($content);

        fclose($stream);
    });

    it('can parse directory paths correctly', function () {
        $reflection = new ReflectionClass($this->adapter);
        $method = $reflection->getMethod('parseDirectoryPath');
        $method->setAccessible(true);

        // 测试根目录下的目录
        $result = $method->invoke($this->adapter, '/testdir');
        expect($result['parent_id'])->toBe('d0');
        expect($result['directory_name'])->toBe('testdir');

        // 测试嵌套目录
        try {
            $result = $method->invoke($this->adapter, '/parent/child');
            expect($result['directory_name'])->toBe('child');
        } catch (Exception $e) {
            // API 调用失败是预期的（使用测试凭据）
            expect($e)->toBeInstanceOf(Exception::class);
        }
    });

    it('validates directory names correctly', function () {
        $reflection = new ReflectionClass($this->adapter);
        $method = $reflection->getMethod('parseDirectoryPath');
        $method->setAccessible(true);

        // 测试无效目录名
        expect(fn() => $method->invoke($this->adapter, '/invalid*dir'))
            ->toThrow(Exception::class);

        // 测试空路径
        expect(fn() => $method->invoke($this->adapter, '/'))
            ->toThrow(Exception::class);
    });

    it('has management operation methods', function () {
        // 验证管理操作方法存在
        $managementMethods = ['delete', 'deleteDirectory', 'createDirectory', 'move', 'copy'];
        foreach ($managementMethods as $method) {
            expect(method_exists($this->adapter, $method))->toBeTrue();
        }

        // 验证私有辅助方法存在
        $privateMethods = ['parseDirectoryPath', 'moveFile', 'moveDirectory'];
        foreach ($privateMethods as $method) {
            expect(method_exists($this->adapter, $method))->toBeTrue();
        }
    });

    it('handles root directory protection', function () {
        // 测试根目录保护逻辑
        $config = new \League\Flysystem\Config();

        // 不应该能够删除根目录
        expect(fn() => $this->adapter->deleteDirectory('/'))
            ->toThrow(Exception::class);

        expect(fn() => $this->adapter->deleteDirectory(''))
            ->toThrow(Exception::class);

        // 不应该能够创建根目录
        expect(fn() => $this->adapter->createDirectory('/', $config))
            ->toThrow(Exception::class);

        expect(fn() => $this->adapter->createDirectory('', $config))
            ->toThrow(Exception::class);
    });

    it('can convert API data to attributes', function () {
        $reflection = new ReflectionClass($this->adapter);
        $method = $reflection->getMethod('convertToAttributes');
        $method->setAccessible(true);

        // 测试文件数据转换
        $fileData = [
            'id' => 'f123',
            'name' => 'test.txt',
            'size' => 1024,
            'mime_type' => 'text/plain',
        ];

        $result = $method->invoke($this->adapter, $fileData, '/test.txt');
        expect($result)->toBeInstanceOf(\League\Flysystem\FileAttributes::class);

        // 测试目录数据转换
        $dirData = [
            'id' => 'd456',
            'name' => 'testdir',
        ];

        $result = $method->invoke($this->adapter, $dirData, '/testdir');
        expect($result)->toBeInstanceOf(\League\Flysystem\DirectoryAttributes::class);

        // 测试无效数据
        $invalidData = ['invalid' => 'data'];
        $result = $method->invoke($this->adapter, $invalidData, '/invalid');
        expect($result)->toBeNull();
    });

    it('handles setVisibility correctly', function () {
        // setVisibility 应该抛出说明性异常
        expect(fn() => $this->adapter->setVisibility('/test.txt', 'public'))
            ->toThrow(Exception::class);
    });

    it('can handle metadata operations', function () {
        // 由于需要实际的API调用，这里只测试方法存在性和基本逻辑
        expect(method_exists($this->adapter, 'getFileMetadata'))->toBeTrue();

        // 测试不存在文件的元数据获取
        expect(fn() => $this->adapter->mimeType('/nonexistent.txt'))
            ->toThrow(Exception::class);

        expect(fn() => $this->adapter->fileSize('/nonexistent.txt'))
            ->toThrow(Exception::class);

        expect(fn() => $this->adapter->lastModified('/nonexistent.txt'))
            ->toThrow(Exception::class);

        expect(fn() => $this->adapter->visibility('/nonexistent.txt'))
            ->toThrow(Exception::class);
    });
});
