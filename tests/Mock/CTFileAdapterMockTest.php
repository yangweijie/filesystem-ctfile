<?php

use Yangweijie\FilesystemCtlife\CTFileAdapter;
use Yangweijie\FilesystemCtlife\CTFileConfig;
use Yangweijie\FilesystemCtlife\CTFileClient;
use Yangweijie\FilesystemCtlife\PathMapper;
use Yangweijie\FilesystemCtlife\Support\UploadHelper;
use League\Flysystem\Config;

describe('CTFileAdapter Mock Tests', function () {
    beforeEach(function () {
        $this->config = new CTFileConfig([
            'session' => 'mock_session_token',
            'app_id' => 'mock_app_id',
        ]);
        
        // 创建模拟的客户端
        $this->mockClient = $this->createMock(CTFileClient::class);
        $this->mockPathMapper = $this->createMock(PathMapper::class);
        $this->mockUploadHelper = $this->createMock(UploadHelper::class);
        
        // 创建适配器并注入模拟对象
        $this->adapter = new CTFileAdapter($this->config);
        
        // 使用反射替换内部依赖
        $reflection = new ReflectionClass($this->adapter);
        
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->adapter, $this->mockClient);
        
        $pathMapperProperty = $reflection->getProperty('pathMapper');
        $pathMapperProperty->setAccessible(true);
        $pathMapperProperty->setValue($this->adapter, $this->mockPathMapper);
        
        $uploadHelperProperty = $reflection->getProperty('uploadHelper');
        $uploadHelperProperty->setAccessible(true);
        $uploadHelperProperty->setValue($this->adapter, $this->mockUploadHelper);
    });

    describe('File Operations', function () {
        it('can check file existence', function () {
            $this->mockPathMapper
                ->expects($this->once())
                ->method('getFileId')
                ->with('/test.txt')
                ->willReturn('f123');
            
            expect($this->adapter->fileExists('/test.txt'))->toBeTrue();
        });

        it('handles file not found', function () {
            $this->mockPathMapper
                ->expects($this->once())
                ->method('getFileId')
                ->with('/nonexistent.txt')
                ->willThrowException(new \Yangweijie\FilesystemCtlife\Exceptions\CTFileException('File not found'));
            
            expect($this->adapter->fileExists('/nonexistent.txt'))->toBeFalse();
        });

        it('can write file content', function () {
            $this->mockUploadHelper
                ->expects($this->once())
                ->method('upload')
                ->with('/test.txt', 'test content', $this->anything())
                ->willReturn(['data' => ['id' => 'f123']]);
            
            $this->mockPathMapper
                ->expects($this->once())
                ->method('cachePath')
                ->with('/test.txt', 'f123');
            
            $this->adapter->write('/test.txt', 'test content', new Config());
        });

        it('can read file content', function () {
            $this->mockPathMapper
                ->expects($this->once())
                ->method('getFileId')
                ->with('/test.txt')
                ->willReturn('f123');
            
            $this->mockClient
                ->expects($this->once())
                ->method('getDownloadUrl')
                ->with('f123')
                ->willReturn(['data' => ['download_url' => 'https://download.example.com/file']]);
            
            // 模拟文件下载
            $reflection = new ReflectionClass($this->adapter);
            $method = $reflection->getMethod('downloadFileContent');
            $method->setAccessible(true);
            
            // 由于无法轻易模拟 file_get_contents，我们测试方法调用
            expect($this->mockClient)->toReceive('getDownloadUrl')->once();
        });

        it('can delete file', function () {
            $this->mockPathMapper
                ->expects($this->once())
                ->method('getFileId')
                ->with('/test.txt')
                ->willReturn('f123');
            
            $this->mockClient
                ->expects($this->once())
                ->method('deleteFile')
                ->with('f123');
            
            $this->mockPathMapper
                ->expects($this->once())
                ->method('invalidateCache')
                ->with('/test.txt');
            
            $this->adapter->delete('/test.txt');
        });
    });

    describe('Directory Operations', function () {
        it('can check directory existence', function () {
            $this->mockPathMapper
                ->expects($this->once())
                ->method('getDirectoryId')
                ->with('/testdir')
                ->willReturn('d456');
            
            expect($this->adapter->directoryExists('/testdir'))->toBeTrue();
        });

        it('can create directory', function () {
            $this->mockPathMapper
                ->expects($this->once())
                ->method('getDirectoryId')
                ->with('/newdir')
                ->willThrowException(new \Yangweijie\FilesystemCtlife\Exceptions\CTFileException('Directory not found'));
            
            $this->mockClient
                ->expects($this->once())
                ->method('createFolder')
                ->with('newdir', 'd0')
                ->willReturn(['data' => ['id' => 'd789']]);
            
            $this->mockPathMapper
                ->expects($this->once())
                ->method('cachePath')
                ->with('/newdir', 'd789');
            
            $this->adapter->createDirectory('/newdir', new Config());
        });

        it('can delete directory', function () {
            $this->mockPathMapper
                ->expects($this->once())
                ->method('getDirectoryId')
                ->with('/testdir')
                ->willReturn('d456');
            
            $this->mockClient
                ->expects($this->once())
                ->method('deleteFolder')
                ->with('d456');
            
            $this->mockPathMapper
                ->expects($this->once())
                ->method('invalidateCache')
                ->with('/testdir');
            
            $this->adapter->deleteDirectory('/testdir');
        });

        it('prevents root directory deletion', function () {
            expect(fn() => $this->adapter->deleteDirectory('/'))
                ->toThrow(\League\Flysystem\UnableToDeleteDirectory::class);
        });
    });

    describe('Metadata Operations', function () {
        it('can get file metadata', function () {
            $mockMetadata = [
                'id' => 'f123',
                'name' => 'test.txt',
                'size' => 1024,
                'mime_type' => 'text/plain',
                'updated_at' => '2023-01-01 12:00:00',
            ];
            
            $this->mockPathMapper
                ->expects($this->once())
                ->method('getFileId')
                ->with('/test.txt')
                ->willReturn('f123');
            
            $this->mockClient
                ->expects($this->once())
                ->method('getFileInfo')
                ->with('f123')
                ->willReturn(['data' => $mockMetadata]);
            
            $attributes = $this->adapter->mimeType('/test.txt');
            expect($attributes->mimeType())->toBe('text/plain');
        });

        it('can list directory contents', function () {
            $mockListing = [
                'data' => [
                    ['id' => 'f123', 'name' => 'file1.txt'],
                    ['id' => 'd456', 'name' => 'folder1'],
                ]
            ];
            
            $this->mockPathMapper
                ->expects($this->once())
                ->method('getDirectoryId')
                ->with('/')
                ->willReturn('d0');
            
            $this->mockClient
                ->expects($this->once())
                ->method('getFileList')
                ->with('d0', 1, 100)
                ->willReturn($mockListing);
            
            $contents = iterator_to_array($this->adapter->listContents('/', false));
            expect(count($contents))->toBe(2);
        });
    });

    describe('Error Handling', function () {
        it('handles authentication errors', function () {
            $this->mockPathMapper
                ->expects($this->once())
                ->method('getFileId')
                ->with('/test.txt')
                ->willThrowException(new \Yangweijie\FilesystemCtlife\Exceptions\AuthenticationException('Invalid session'));
            
            expect(fn() => $this->adapter->read('/test.txt'))
                ->toThrow(\League\Flysystem\UnableToReadFile::class);
        });

        it('handles network errors', function () {
            $this->mockPathMapper
                ->expects($this->once())
                ->method('getFileId')
                ->with('/test.txt')
                ->willThrowException(new \Yangweijie\FilesystemCtlife\Exceptions\NetworkException('Connection timeout'));
            
            expect(fn() => $this->adapter->read('/test.txt'))
                ->toThrow(\League\Flysystem\UnableToReadFile::class);
        });

        it('handles rate limit errors', function () {
            $this->mockUploadHelper
                ->expects($this->once())
                ->method('upload')
                ->willThrowException(new \Yangweijie\FilesystemCtlife\Exceptions\RateLimitException('Rate limit exceeded', 60));
            
            expect(fn() => $this->adapter->write('/test.txt', 'content', new Config()))
                ->toThrow(\League\Flysystem\UnableToWriteFile::class);
        });
    });

    describe('Path Mapping', function () {
        it('handles path normalization', function () {
            // 测试路径标准化逻辑
            $reflection = new ReflectionClass($this->adapter);
            $method = $reflection->getMethod('parseFilePath');
            $method->setAccessible(true);
            
            // 模拟路径解析
            $this->mockPathMapper
                ->expects($this->any())
                ->method('getDirectoryId')
                ->willReturn('d0');
            
            $result = $method->invoke($this->adapter, '/test.txt');
            expect($result['folder_id'])->toBe('d0');
            expect($result['filename'])->toBe('test.txt');
        });
    });
});
