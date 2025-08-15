<?php

use Yangweijie\FilesystemCtlife\Support\UploadHelper;
use Yangweijie\FilesystemCtlife\Support\FileInfo;
use Yangweijie\FilesystemCtlife\CTFileClient;
use Yangweijie\FilesystemCtlife\CTFileConfig;

describe('UploadHelper', function () {
    beforeEach(function () {
        $this->config = new CTFileConfig([
            'session' => 'test_session_token_123456',
            'app_id' => 'test_app_id',
        ]);
        
        $this->client = new CTFileClient($this->config);
        $this->helper = new UploadHelper($this->client);
    });

    it('can be instantiated', function () {
        expect($this->helper)->toBeInstanceOf(UploadHelper::class);
    });

    it('can prepare upload data correctly', function () {
        $reflection = new ReflectionClass($this->helper);
        $method = $reflection->getMethod('prepareUploadData');
        $method->setAccessible(true);
        
        $content = 'Hello, World!';
        $result = $method->invoke($this->helper, $content);
        
        expect($result)->toHaveKey('content');
        expect($result)->toHaveKey('size');
        expect($result)->toHaveKey('checksum');
        expect($result['content'])->toBe($content);
        expect($result['size'])->toBe(strlen($content));
        expect($result['checksum'])->toBe(md5($content));
    });

    it('can prepare upload data from resource', function () {
        $reflection = new ReflectionClass($this->helper);
        $method = $reflection->getMethod('prepareUploadData');
        $method->setAccessible(true);
        
        $content = 'Hello, World!';
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, $content);
        
        $result = $method->invoke($this->helper, $resource);
        
        expect($result)->toHaveKey('content');
        expect($result)->toHaveKey('size');
        expect($result)->toHaveKey('checksum');
        expect($result['size'])->toBe(strlen($content));
        expect($result['checksum'])->toBe(md5($content));
        
        fclose($resource);
    });

    it('throws exception for invalid content type', function () {
        $reflection = new ReflectionClass($this->helper);
        $method = $reflection->getMethod('prepareUploadData');
        $method->setAccessible(true);
        
        expect(fn() => $method->invoke($this->helper, 123))
            ->toThrow(InvalidArgumentException::class, 'Content must be a string or resource');
    });

    it('can validate upload parameters', function () {
        // 有效参数
        expect($this->helper->validateUploadParams('test.txt', 1024))->toBeTrue();
        
        // 无效文件名
        expect($this->helper->validateUploadParams('test/file.txt', 1024))->toBeFalse();
        
        // 文件过大
        $config = ['max_file_size' => 1024];
        expect($this->helper->validateUploadParams('test.txt', 2048, $config))->toBeFalse();
        
        // 不允许的扩展名
        $config = ['allowed_extensions' => ['txt', 'pdf']];
        expect($this->helper->validateUploadParams('test.jpg', 1024, $config))->toBeFalse();
        expect($this->helper->validateUploadParams('test.txt', 1024, $config))->toBeTrue();
    });

    it('can generate unique filenames', function () {
        $original = 'test.txt';
        $unique1 = $this->helper->generateUniqueFilename($original);
        $unique2 = $this->helper->generateUniqueFilename($original);
        
        expect($unique1)->not->toBe($unique2);
        expect($unique1)->toContain('test_');
        expect($unique1)->toEndWith('.txt');
        
        // 测试带前缀
        $withPrefix = $this->helper->generateUniqueFilename($original, 'upload_');
        expect($withPrefix)->toStartWith('upload_test_');
    });

    it('can generate unique filename without extension', function () {
        $original = 'testfile';
        $unique = $this->helper->generateUniqueFilename($original);
        
        expect($unique)->toContain('testfile_');
        expect($unique)->not->toContain('.');
    });

    it('can create progress callback', function () {
        $progressData = [];
        $callback = function ($progress, $uploaded, $total) use (&$progressData) {
            $progressData = [$progress, $uploaded, $total];
        };
        
        $curlCallback = $this->helper->getProgressCallback($callback);
        
        expect($curlCallback)->toBeCallable();
        
        // 模拟进度回调
        $curlCallback(0, 0, 1000, 500);
        
        expect($progressData[0])->toBe(50.0); // 50% 进度
        expect($progressData[1])->toBe(500);  // 已上传
        expect($progressData[2])->toBe(1000); // 总大小
    });

    it('handles zero upload total in progress callback', function () {
        $progressData = [];
        $callback = function ($progress, $uploaded, $total) use (&$progressData) {
            $progressData = [$progress, $uploaded, $total];
        };
        
        $curlCallback = $this->helper->getProgressCallback($callback);
        
        // 模拟零总大小的情况
        $curlCallback(0, 0, 0, 0);
        
        // 回调不应该被调用
        expect($progressData)->toBe([]);
    });

    it('can clean up temporary files', function () {
        // 创建临时文件
        $tempFile1 = tempnam(sys_get_temp_dir(), 'test1_');
        $tempFile2 = tempnam(sys_get_temp_dir(), 'test2_');
        
        file_put_contents($tempFile1, 'test content 1');
        file_put_contents($tempFile2, 'test content 2');
        
        expect(file_exists($tempFile1))->toBeTrue();
        expect(file_exists($tempFile2))->toBeTrue();
        
        // 清理文件
        $this->helper->cleanupTempFiles([$tempFile1, $tempFile2]);
        
        expect(file_exists($tempFile1))->toBeFalse();
        expect(file_exists($tempFile2))->toBeFalse();
    });

    it('handles non-existent files in cleanup gracefully', function () {
        $nonExistentFile = '/path/to/non/existent/file.txt';
        
        // 应该不抛出异常
        $this->helper->cleanupTempFiles([$nonExistentFile]);
        
        expect(true)->toBeTrue(); // 如果到达这里说明没有抛出异常
    });

    it('can handle file existence check', function () {
        // 由于我们无法轻易模拟 API 响应，这里测试基本逻辑
        // 在实际环境中，这需要使用 HTTP 客户端模拟库
        
        // 测试异常情况下的处理
        $result = $this->helper->fileExists('d0', 'nonexistent.txt');
        expect($result)->toBeFalse(); // 应该返回 false 而不是抛出异常
    });

    it('can download from URL', function () {
        $reflection = new ReflectionClass($this->helper);
        $method = $reflection->getMethod('downloadFromUrl');
        $method->setAccessible(true);
        
        // 测试无效URL的错误处理
        expect(fn() => $method->invoke($this->helper, 'invalid-url'))
            ->toThrow(Exception::class);
    });
});
