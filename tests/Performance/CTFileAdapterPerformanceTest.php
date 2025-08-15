<?php

use Yangweijie\FilesystemCtlife\CTFileAdapter;
use Yangweijie\FilesystemCtlife\CTFileConfig;
use League\Flysystem\Filesystem;

describe('CTFileAdapter Performance Tests', function () {
    beforeEach(function () {
        // 使用环境变量或跳过测试
        $session = $_ENV['CTFILE_SESSION'] ?? null;
        $appId = $_ENV['CTFILE_APP_ID'] ?? null;
        
        if (!$session || !$appId) {
            $this->markTestSkipped('CTFile credentials not provided. Set CTFILE_SESSION and CTFILE_APP_ID environment variables to run performance tests.');
        }
        
        $this->config = new CTFileConfig([
            'session' => $session,
            'app_id' => $appId,
            'timeout' => 60,
            'retry_attempts' => 1, // 减少重试以获得更准确的性能数据
        ]);
        
        $this->adapter = new CTFileAdapter($this->config);
        $this->filesystem = new Filesystem($this->adapter);
    });

    it('measures file upload performance', function () {
        $testFile = '/perf-upload-' . time() . '.txt';
        $testContent = str_repeat('Performance test content. ', 1000); // ~25KB
        
        $startTime = microtime(true);
        $this->filesystem->write($testFile, $testContent);
        $uploadTime = microtime(true) - $startTime;
        
        // 验证上传成功
        expect($this->filesystem->fileExists($testFile))->toBeTrue();
        
        // 性能断言（上传应在10秒内完成）
        expect($uploadTime)->toBeLessThan(10.0);
        
        echo "Upload time for " . strlen($testContent) . " bytes: " . round($uploadTime, 3) . "s" . PHP_EOL;
        
        // 清理
        $this->filesystem->delete($testFile);
    });

    it('measures file download performance', function () {
        $testFile = '/perf-download-' . time() . '.txt';
        $testContent = str_repeat('Download performance test. ', 1000); // ~25KB
        
        // 先上传文件
        $this->filesystem->write($testFile, $testContent);
        
        // 测量下载性能
        $startTime = microtime(true);
        $downloadedContent = $this->filesystem->read($testFile);
        $downloadTime = microtime(true) - $startTime;
        
        // 验证下载内容正确
        expect($downloadedContent)->toBe($testContent);
        
        // 性能断言（下载应在5秒内完成）
        expect($downloadTime)->toBeLessThan(5.0);
        
        echo "Download time for " . strlen($testContent) . " bytes: " . round($downloadTime, 3) . "s" . PHP_EOL;
        
        // 清理
        $this->filesystem->delete($testFile);
    });

    it('measures directory listing performance', function () {
        $startTime = microtime(true);
        $contents = iterator_to_array($this->filesystem->listContents('/', false));
        $listTime = microtime(true) - $startTime;
        
        // 性能断言（列表操作应在3秒内完成）
        expect($listTime)->toBeLessThan(3.0);
        
        echo "Directory listing time for " . count($contents) . " items: " . round($listTime, 3) . "s" . PHP_EOL;
    });

    it('measures batch file operations performance', function () {
        $fileCount = 5;
        $testFiles = [];
        $testContent = 'Batch test content - ' . time();
        
        // 批量上传
        $startTime = microtime(true);
        for ($i = 0; $i < $fileCount; $i++) {
            $testFile = "/perf-batch-{$i}-" . time() . ".txt";
            $testFiles[] = $testFile;
            $this->filesystem->write($testFile, $testContent . " - File {$i}");
        }
        $batchUploadTime = microtime(true) - $startTime;
        
        // 批量读取
        $startTime = microtime(true);
        foreach ($testFiles as $testFile) {
            $this->filesystem->read($testFile);
        }
        $batchReadTime = microtime(true) - $startTime;
        
        // 批量删除
        $startTime = microtime(true);
        foreach ($testFiles as $testFile) {
            $this->filesystem->delete($testFile);
        }
        $batchDeleteTime = microtime(true) - $startTime;
        
        // 性能断言
        expect($batchUploadTime)->toBeLessThan(30.0); // 30秒内完成5个文件上传
        expect($batchReadTime)->toBeLessThan(15.0);   // 15秒内完成5个文件读取
        expect($batchDeleteTime)->toBeLessThan(15.0); // 15秒内完成5个文件删除
        
        echo "Batch upload time for {$fileCount} files: " . round($batchUploadTime, 3) . "s" . PHP_EOL;
        echo "Batch read time for {$fileCount} files: " . round($batchReadTime, 3) . "s" . PHP_EOL;
        echo "Batch delete time for {$fileCount} files: " . round($batchDeleteTime, 3) . "s" . PHP_EOL;
    });

    it('measures large file handling performance', function () {
        $testFile = '/perf-large-' . time() . '.txt';
        $largeContent = str_repeat('Large file performance test content. ', 50000); // ~1.5MB
        
        // 大文件上传
        $startTime = microtime(true);
        $this->filesystem->write($testFile, $largeContent);
        $largeUploadTime = microtime(true) - $startTime;
        
        // 大文件下载
        $startTime = microtime(true);
        $downloadedContent = $this->filesystem->read($testFile);
        $largeDownloadTime = microtime(true) - $startTime;
        
        // 验证内容正确
        expect(strlen($downloadedContent))->toBe(strlen($largeContent));
        
        // 性能断言（大文件操作应在合理时间内完成）
        expect($largeUploadTime)->toBeLessThan(60.0);   // 1分钟内上传
        expect($largeDownloadTime)->toBeLessThan(30.0); // 30秒内下载
        
        echo "Large file upload time for " . round(strlen($largeContent) / 1024 / 1024, 2) . "MB: " . round($largeUploadTime, 3) . "s" . PHP_EOL;
        echo "Large file download time for " . round(strlen($largeContent) / 1024 / 1024, 2) . "MB: " . round($largeDownloadTime, 3) . "s" . PHP_EOL;
        
        // 清理
        $this->filesystem->delete($testFile);
    });

    it('measures path mapping cache performance', function () {
        $testDir = '/perf-cache-' . time();
        $this->filesystem->createDirectory($testDir);
        
        // 第一次访问（缓存未命中）
        $startTime = microtime(true);
        $exists1 = $this->filesystem->directoryExists($testDir);
        $firstAccessTime = microtime(true) - $startTime;
        
        // 第二次访问（缓存命中）
        $startTime = microtime(true);
        $exists2 = $this->filesystem->directoryExists($testDir);
        $secondAccessTime = microtime(true) - $startTime;
        
        expect($exists1)->toBeTrue();
        expect($exists2)->toBeTrue();
        
        // 缓存命中应该更快
        expect($secondAccessTime)->toBeLessThan($firstAccessTime);
        
        echo "First access time (cache miss): " . round($firstAccessTime, 4) . "s" . PHP_EOL;
        echo "Second access time (cache hit): " . round($secondAccessTime, 4) . "s" . PHP_EOL;
        echo "Cache speedup: " . round($firstAccessTime / $secondAccessTime, 2) . "x" . PHP_EOL;
        
        // 清理
        $this->filesystem->deleteDirectory($testDir);
    });

    it('measures memory usage during operations', function () {
        $initialMemory = memory_get_usage(true);
        
        $testFile = '/perf-memory-' . time() . '.txt';
        $testContent = str_repeat('Memory test content. ', 10000); // ~200KB
        
        // 执行一系列操作
        $this->filesystem->write($testFile, $testContent);
        $this->filesystem->read($testFile);
        $this->filesystem->fileSize($testFile);
        $this->filesystem->mimeType($testFile);
        $this->filesystem->delete($testFile);
        
        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;
        
        // 内存增长应该在合理范围内（小于10MB）
        expect($memoryIncrease)->toBeLessThan(10 * 1024 * 1024);
        
        echo "Memory increase during operations: " . round($memoryIncrease / 1024 / 1024, 2) . "MB" . PHP_EOL;
    });
});
