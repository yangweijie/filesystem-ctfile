<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Tests\Unit\Exceptions;

use YangWeijie\FilesystemCtfile\Exceptions\CtFileException;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileOperationException;
use YangWeijie\FilesystemCtfile\Tests\TestCase;

class CtFileOperationExceptionTest extends TestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new CtFileOperationException('Test message');

        $this->assertInstanceOf(CtFileException::class, $exception);
        $this->assertInstanceOf(CtFileOperationException::class, $exception);
    }

    public function testUploadFailed(): void
    {
        $previous = new \Exception('Upload error');
        $exception = CtFileOperationException::uploadFailed('/local/file.txt', '/remote/file.txt', $previous);

        $this->assertStringContainsString('Failed to upload file to ctFile server', $exception->getMessage());
        $this->assertSame('upload', $exception->getOperation());
        $this->assertSame('/remote/file.txt', $exception->getPath());
        $this->assertSame([
            'local_path' => '/local/file.txt',
            'remote_path' => '/remote/file.txt',
        ], $exception->getContext());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testDownloadFailed(): void
    {
        $exception = CtFileOperationException::downloadFailed('/remote/file.txt', '/local/file.txt');

        $this->assertStringContainsString('Failed to download file from ctFile server', $exception->getMessage());
        $this->assertSame('download', $exception->getOperation());
        $this->assertSame('/remote/file.txt', $exception->getPath());
        $this->assertSame([
            'remote_path' => '/remote/file.txt',
            'local_path' => '/local/file.txt',
        ], $exception->getContext());
    }

    public function testDeleteFailed(): void
    {
        $exception = CtFileOperationException::deleteFailed('/file.txt');

        $this->assertStringContainsString('Failed to delete file on ctFile server', $exception->getMessage());
        $this->assertSame('delete', $exception->getOperation());
        $this->assertSame('/file.txt', $exception->getPath());
    }

    public function testCreateDirectoryFailed(): void
    {
        $exception = CtFileOperationException::createDirectoryFailed('/new/directory');

        $this->assertStringContainsString('Failed to create directory on ctFile server', $exception->getMessage());
        $this->assertSame('create_directory', $exception->getOperation());
        $this->assertSame('/new/directory', $exception->getPath());
    }

    public function testDeleteDirectoryFailed(): void
    {
        $exception = CtFileOperationException::deleteDirectoryFailed('/directory');

        $this->assertStringContainsString('Failed to delete directory on ctFile server', $exception->getMessage());
        $this->assertSame('delete_directory', $exception->getOperation());
        $this->assertSame('/directory', $exception->getPath());
    }

    public function testListDirectoryFailed(): void
    {
        $exception = CtFileOperationException::listDirectoryFailed('/directory');

        $this->assertStringContainsString('Failed to list directory contents on ctFile server', $exception->getMessage());
        $this->assertSame('list_directory', $exception->getOperation());
        $this->assertSame('/directory', $exception->getPath());
    }

    public function testMoveFailed(): void
    {
        $exception = CtFileOperationException::moveFailed('/source.txt', '/destination.txt');

        $this->assertStringContainsString('Failed to move/rename file on ctFile server', $exception->getMessage());
        $this->assertSame('move', $exception->getOperation());
        $this->assertSame('/source.txt', $exception->getPath());
        $this->assertSame([
            'source_path' => '/source.txt',
            'destination_path' => '/destination.txt',
        ], $exception->getContext());
    }

    public function testCopyFailed(): void
    {
        $exception = CtFileOperationException::copyFailed('/source.txt', '/destination.txt');

        $this->assertStringContainsString('Failed to copy file on ctFile server', $exception->getMessage());
        $this->assertSame('copy', $exception->getOperation());
        $this->assertSame('/source.txt', $exception->getPath());
        $this->assertSame([
            'source_path' => '/source.txt',
            'destination_path' => '/destination.txt',
        ], $exception->getContext());
    }

    public function testFileNotFound(): void
    {
        $exception = CtFileOperationException::fileNotFound('/missing.txt');

        $this->assertStringContainsString('File not found on ctFile server', $exception->getMessage());
        $this->assertSame('file_access', $exception->getOperation());
        $this->assertSame('/missing.txt', $exception->getPath());
    }

    public function testDirectoryNotFound(): void
    {
        $exception = CtFileOperationException::directoryNotFound('/missing/directory');

        $this->assertStringContainsString('Directory not found on ctFile server', $exception->getMessage());
        $this->assertSame('directory_access', $exception->getOperation());
        $this->assertSame('/missing/directory', $exception->getPath());
    }

    public function testFileAlreadyExists(): void
    {
        $exception = CtFileOperationException::fileAlreadyExists('/existing.txt');

        $this->assertStringContainsString('File already exists on ctFile server', $exception->getMessage());
        $this->assertSame('file_creation', $exception->getOperation());
        $this->assertSame('/existing.txt', $exception->getPath());
    }

    public function testInsufficientSpace(): void
    {
        $exception = CtFileOperationException::insufficientSpace('/upload/path', 1048576, 524288);

        $this->assertStringContainsString('Insufficient disk space on ctFile server', $exception->getMessage());
        $this->assertSame('space_check', $exception->getOperation());
        $this->assertSame('/upload/path', $exception->getPath());

        $context = $exception->getContext();
        $this->assertSame(1048576, $context['required_bytes']);
        $this->assertSame(524288, $context['available_bytes']);
        $this->assertSame(1.0, $context['required_mb']);
        $this->assertSame(0.5, $context['available_mb']);
    }
}
