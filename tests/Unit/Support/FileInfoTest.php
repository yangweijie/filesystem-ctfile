<?php

use Yangweijie\FilesystemCtlife\Support\FileInfo;
use League\Flysystem\FileAttributes;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\Visibility;

describe('FileInfo', function () {
    it('can create file attributes from API response', function () {
        $apiData = [
            'id' => 'f123',
            'name' => 'test.txt',
            'size' => 1024,
            'mime_type' => 'text/plain',
            'updated_at' => '2023-01-01 12:00:00',
            'is_public' => true,
            'checksum' => 'abc123',
            'download_count' => 5,
        ];
        
        $attributes = FileInfo::fromApiResponse($apiData, '/test.txt');
        
        expect($attributes)->toBeInstanceOf(FileAttributes::class);
        expect($attributes->path())->toBe('/test.txt');
        expect($attributes->fileSize())->toBe(1024);
        expect($attributes->mimeType())->toBe('text/plain');
        expect($attributes->visibility())->toBe(Visibility::PUBLIC);
        expect($attributes->extraMetadata()['ctfile_id'])->toBe('f123');
        expect($attributes->extraMetadata()['checksum'])->toBe('abc123');
        expect($attributes->extraMetadata()['download_count'])->toBe(5);
    });

    it('can create directory attributes from API response', function () {
        $apiData = [
            'id' => 'd456',
            'name' => 'folder',
            'updated_at' => '2023-01-01 12:00:00',
            'is_public' => false,
            'file_count' => 10,
            'folder_count' => 3,
        ];
        
        $attributes = FileInfo::fromDirectoryResponse($apiData, '/folder');
        
        expect($attributes)->toBeInstanceOf(DirectoryAttributes::class);
        expect($attributes->path())->toBe('/folder');
        expect($attributes->visibility())->toBe(Visibility::PRIVATE);
        expect($attributes->extraMetadata()['ctfile_id'])->toBe('d456');
        expect($attributes->extraMetadata()['file_count'])->toBe(10);
        expect($attributes->extraMetadata()['folder_count'])->toBe(3);
    });

    it('can calculate checksum correctly', function () {
        $content = 'Hello, World!';
        $checksum = FileInfo::calculateChecksum($content);
        
        expect($checksum)->toBe(md5($content));
        
        // 测试不同算法
        $sha1Checksum = FileInfo::calculateChecksum($content, 'sha1');
        expect($sha1Checksum)->toBe(sha1($content));
    });

    it('can determine MIME types correctly', function () {
        expect(FileInfo::getMimeType('test.txt'))->toBe('text/plain');
        expect(FileInfo::getMimeType('image.jpg'))->toBe('image/jpeg');
        expect(FileInfo::getMimeType('image.jpeg'))->toBe('image/jpeg');
        expect(FileInfo::getMimeType('image.png'))->toBe('image/png');
        expect(FileInfo::getMimeType('video.mp4'))->toBe('video/mp4');
        expect(FileInfo::getMimeType('audio.mp3'))->toBe('audio/mpeg');
        expect(FileInfo::getMimeType('document.pdf'))->toBe('application/pdf');
        expect(FileInfo::getMimeType('archive.zip'))->toBe('application/zip');
        expect(FileInfo::getMimeType('unknown.xyz'))->toBe('application/octet-stream');
    });

    it('can extract file information correctly', function () {
        $path = '/path/to/file.txt';
        
        expect(FileInfo::getExtension($path))->toBe('txt');
        expect(FileInfo::getBasename($path))->toBe('file');
        expect(FileInfo::getFilename($path))->toBe('file.txt');
    });

    it('can identify file types correctly', function () {
        expect(FileInfo::isImage('photo.jpg'))->toBeTrue();
        expect(FileInfo::isImage('photo.png'))->toBeTrue();
        expect(FileInfo::isImage('document.pdf'))->toBeFalse();
        
        expect(FileInfo::isVideo('movie.mp4'))->toBeTrue();
        expect(FileInfo::isVideo('movie.avi'))->toBeTrue();
        expect(FileInfo::isVideo('photo.jpg'))->toBeFalse();
        
        expect(FileInfo::isAudio('song.mp3'))->toBeTrue();
        expect(FileInfo::isAudio('song.wav'))->toBeTrue();
        expect(FileInfo::isAudio('movie.mp4'))->toBeFalse();
    });

    it('can format file sizes correctly', function () {
        expect(FileInfo::formatFileSize(1024))->toBe('1 KB');
        expect(FileInfo::formatFileSize(1024 * 1024))->toBe('1 MB');
        expect(FileInfo::formatFileSize(1024 * 1024 * 1024))->toBe('1 GB');
        expect(FileInfo::formatFileSize(1536))->toBe('1.5 KB');
        expect(FileInfo::formatFileSize(512))->toBe('512 B');
    });

    it('can validate filenames correctly', function () {
        // 有效文件名
        expect(FileInfo::isValidFilename('test.txt'))->toBeTrue();
        expect(FileInfo::isValidFilename('my-file_123.pdf'))->toBeTrue();
        expect(FileInfo::isValidFilename('中文文件.txt'))->toBeTrue();
        
        // 无效文件名 - 包含非法字符
        expect(FileInfo::isValidFilename('file/name.txt'))->toBeFalse();
        expect(FileInfo::isValidFilename('file\\name.txt'))->toBeFalse();
        expect(FileInfo::isValidFilename('file:name.txt'))->toBeFalse();
        expect(FileInfo::isValidFilename('file*name.txt'))->toBeFalse();
        expect(FileInfo::isValidFilename('file?name.txt'))->toBeFalse();
        expect(FileInfo::isValidFilename('file"name.txt'))->toBeFalse();
        expect(FileInfo::isValidFilename('file<name.txt'))->toBeFalse();
        expect(FileInfo::isValidFilename('file>name.txt'))->toBeFalse();
        expect(FileInfo::isValidFilename('file|name.txt'))->toBeFalse();
        
        // 无效文件名 - 空字符串
        expect(FileInfo::isValidFilename(''))->toBeFalse();
        
        // 无效文件名 - 保留名称
        expect(FileInfo::isValidFilename('CON.txt'))->toBeFalse();
        expect(FileInfo::isValidFilename('PRN.txt'))->toBeFalse();
        expect(FileInfo::isValidFilename('AUX.txt'))->toBeFalse();
        expect(FileInfo::isValidFilename('NUL.txt'))->toBeFalse();
        expect(FileInfo::isValidFilename('COM1.txt'))->toBeFalse();
        expect(FileInfo::isValidFilename('LPT1.txt'))->toBeFalse();
    });

    it('handles missing data gracefully', function () {
        $minimalData = [
            'id' => 'f123',
            'name' => 'test.txt',
        ];
        
        $attributes = FileInfo::fromApiResponse($minimalData, '/test.txt');
        
        expect($attributes)->toBeInstanceOf(FileAttributes::class);
        expect($attributes->path())->toBe('/test.txt');
        expect($attributes->fileSize())->toBeNull();
        expect($attributes->mimeType())->toBe('text/plain'); // 从路径推断
        expect($attributes->visibility())->toBe(Visibility::PRIVATE); // 默认值
    });

    it('determines visibility correctly', function () {
        // 使用反射测试私有方法
        $reflection = new ReflectionClass(FileInfo::class);
        $method = $reflection->getMethod('determineVisibility');
        $method->setAccessible(true);
        
        // 测试 is_public 字段
        expect($method->invoke(null, ['is_public' => true]))->toBe(Visibility::PUBLIC);
        expect($method->invoke(null, ['is_public' => false]))->toBe(Visibility::PRIVATE);
        
        // 测试 visibility 字段
        expect($method->invoke(null, ['visibility' => 'public']))->toBe(Visibility::PUBLIC);
        expect($method->invoke(null, ['visibility' => 'private']))->toBe(Visibility::PRIVATE);
        
        // 测试默认值
        expect($method->invoke(null, []))->toBe(Visibility::PRIVATE);
    });
});
