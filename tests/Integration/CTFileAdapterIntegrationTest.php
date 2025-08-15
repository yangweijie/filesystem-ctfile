<?php

use Yangweijie\FilesystemCtlife\CTFileAdapter;
use Yangweijie\FilesystemCtlife\CTFileConfig;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;

describe('CTFileAdapter Integration Tests', function () {
    beforeEach(function () {
        // 使用环境变量或跳过测试
        $session = $_ENV['CTFILE_SESSION'] ?? null;
        $appId = $_ENV['CTFILE_APP_ID'] ?? null;
        
        if (!$session || !$appId) {
            $this->markTestSkipped('CTFile credentials not provided. Set CTFILE_SESSION and CTFILE_APP_ID environment variables to run integration tests.');
        }
        
        $this->config = new CTFileConfig([
            'session' => $session,
            'app_id' => $appId,
            'api_base_url' => $_ENV['CTFILE_API_URL'] ?? 'https://webapi.ctfile.com',
            'timeout' => 30,
            'retry_attempts' => 3,
        ]);
        
        $this->adapter = new CTFileAdapter($this->config);
        $this->filesystem = new Filesystem($this->adapter);
    });

    it('can check if root directory exists', function () {
        expect($this->adapter->directoryExists('/'))->toBeTrue();
    });

    it('can list root directory contents', function () {
        $contents = iterator_to_array($this->adapter->listContents('/', false));
        expect($contents)->toBeArray();
    });

    it('can perform basic file operations', function () {
        $testFile = '/test-integration-' . time() . '.txt';
        $testContent = 'Integration test content - ' . date('Y-m-d H:i:s');
        
        // 写入文件
        $this->filesystem->write($testFile, $testContent);
        
        // 检查文件存在
        expect($this->filesystem->fileExists($testFile))->toBeTrue();
        
        // 读取文件
        $readContent = $this->filesystem->read($testFile);
        expect($readContent)->toBe($testContent);
        
        // 获取文件大小
        $size = $this->filesystem->fileSize($testFile);
        expect($size)->toBe(strlen($testContent));
        
        // 获取 MIME 类型
        $mimeType = $this->filesystem->mimeType($testFile);
        expect($mimeType)->toBe('text/plain');
        
        // 删除文件
        $this->filesystem->delete($testFile);
        
        // 确认文件已删除
        expect($this->filesystem->fileExists($testFile))->toBeFalse();
    });

    it('can perform directory operations', function () {
        $testDir = '/test-integration-dir-' . time();
        
        // 创建目录
        $this->filesystem->createDirectory($testDir);
        
        // 检查目录存在
        expect($this->filesystem->directoryExists($testDir))->toBeTrue();
        
        // 在目录中创建文件
        $testFile = $testDir . '/test-file.txt';
        $this->filesystem->write($testFile, 'Test content in directory');
        
        // 列出目录内容
        $contents = iterator_to_array($this->filesystem->listContents($testDir, false));
        expect(count($contents))->toBeGreaterThan(0);
        
        // 清理
        $this->filesystem->delete($testFile);
        $this->filesystem->deleteDirectory($testDir);
        
        // 确认目录已删除
        expect($this->filesystem->directoryExists($testDir))->toBeFalse();
    });

    it('can handle file operations with streams', function () {
        $testFile = '/test-stream-' . time() . '.txt';
        $testContent = 'Stream test content - ' . date('Y-m-d H:i:s');
        
        // 创建内存流
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $testContent);
        rewind($stream);
        
        // 写入流
        $this->filesystem->writeStream($testFile, $stream);
        fclose($stream);
        
        // 读取为流
        $readStream = $this->filesystem->readStream($testFile);
        $readContent = stream_get_contents($readStream);
        fclose($readStream);
        
        expect($readContent)->toBe($testContent);
        
        // 清理
        $this->filesystem->delete($testFile);
    });

    it('can copy and move files', function () {
        $sourceFile = '/test-source-' . time() . '.txt';
        $copyFile = '/test-copy-' . time() . '.txt';
        $moveFile = '/test-move-' . time() . '.txt';
        $testContent = 'Copy and move test content';
        
        // 创建源文件
        $this->filesystem->write($sourceFile, $testContent);
        
        // 复制文件
        $this->filesystem->copy($sourceFile, $copyFile);
        expect($this->filesystem->fileExists($copyFile))->toBeTrue();
        expect($this->filesystem->read($copyFile))->toBe($testContent);
        
        // 移动文件
        $this->filesystem->move($copyFile, $moveFile);
        expect($this->filesystem->fileExists($moveFile))->toBeTrue();
        expect($this->filesystem->fileExists($copyFile))->toBeFalse();
        
        // 清理
        $this->filesystem->delete($sourceFile);
        $this->filesystem->delete($moveFile);
    });

    it('handles errors gracefully', function () {
        // 尝试读取不存在的文件
        expect(fn() => $this->filesystem->read('/nonexistent-file.txt'))
            ->toThrow(\League\Flysystem\UnableToReadFile::class);
        
        // 尝试删除不存在的文件
        expect(fn() => $this->filesystem->delete('/nonexistent-file.txt'))
            ->toThrow(\League\Flysystem\UnableToDeleteFile::class);
        
        // 尝试创建无效名称的文件
        expect(fn() => $this->filesystem->write('/invalid*file.txt', 'content'))
            ->toThrow(\League\Flysystem\UnableToWriteFile::class);
    });

    it('can handle large files', function () {
        $testFile = '/test-large-' . time() . '.txt';
        $largeContent = str_repeat('Large file test content. ', 10000); // ~250KB
        
        // 写入大文件
        $this->filesystem->write($testFile, $largeContent);
        
        // 验证文件大小
        $size = $this->filesystem->fileSize($testFile);
        expect($size)->toBe(strlen($largeContent));
        
        // 读取大文件
        $readContent = $this->filesystem->read($testFile);
        expect(strlen($readContent))->toBe(strlen($largeContent));
        
        // 清理
        $this->filesystem->delete($testFile);
    });
});
