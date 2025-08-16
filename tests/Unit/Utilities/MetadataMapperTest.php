<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Tests\Unit\Utilities;

use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\Visibility;
use YangWeijie\FilesystemCtfile\Tests\TestCase;
use YangWeijie\FilesystemCtfile\Utilities\MetadataMapper;

class MetadataMapperTest extends TestCase
{
    public function testToFileAttributesWithCompleteMetadata(): void
    {
        $metadata = [
            'path' => 'folder/file.txt',
            'size' => 1024,
            'timestamp' => 1640995200, // 2022-01-01 00:00:00 UTC
            'mime_type' => 'text/plain',
            'visibility' => Visibility::PUBLIC,
        ];

        $attributes = MetadataMapper::toFileAttributes($metadata);

        $this->assertInstanceOf(FileAttributes::class, $attributes);
        $this->assertSame('folder/file.txt', $attributes->path());
        $this->assertSame(1024, $attributes->fileSize());
        $this->assertSame(1640995200, $attributes->lastModified());
        $this->assertSame('text/plain', $attributes->mimeType());
        $this->assertSame(Visibility::PUBLIC, $attributes->visibility());
    }

    public function testToFileAttributesWithMinimalMetadata(): void
    {
        $metadata = [
            'path' => 'file.txt',
        ];

        $attributes = MetadataMapper::toFileAttributes($metadata);

        $this->assertInstanceOf(FileAttributes::class, $attributes);
        $this->assertSame('file.txt', $attributes->path());
        $this->assertNull($attributes->fileSize());
        $this->assertNull($attributes->lastModified());
        $this->assertSame('text/plain', $attributes->mimeType()); // MIME type detected from .txt extension
        $this->assertSame(Visibility::PRIVATE, $attributes->visibility());
    }

    public function testToDirectoryAttributesWithCompleteMetadata(): void
    {
        $metadata = [
            'path' => 'folder',
            'timestamp' => 1640995200,
            'visibility' => Visibility::PUBLIC,
        ];

        $attributes = MetadataMapper::toDirectoryAttributes($metadata);

        $this->assertInstanceOf(DirectoryAttributes::class, $attributes);
        $this->assertSame('folder', $attributes->path());
        $this->assertSame(1640995200, $attributes->lastModified());
        $this->assertSame(Visibility::PUBLIC, $attributes->visibility());
    }

    public function testToDirectoryAttributesWithMinimalMetadata(): void
    {
        $metadata = [
            'path' => 'folder',
        ];

        $attributes = MetadataMapper::toDirectoryAttributes($metadata);

        $this->assertInstanceOf(DirectoryAttributes::class, $attributes);
        $this->assertSame('folder', $attributes->path());
        $this->assertNull($attributes->lastModified());
        $this->assertSame(Visibility::PRIVATE, $attributes->visibility());
    }

    public function testExtractMimeTypeFromExplicitField(): void
    {
        $metadata = ['mime_type' => 'text/plain'];
        $this->assertSame('text/plain', MetadataMapper::extractMimeType($metadata));

        $metadata = ['mimetype' => 'image/jpeg'];
        $this->assertSame('image/jpeg', MetadataMapper::extractMimeType($metadata));

        $metadata = ['content_type' => 'application/json'];
        $this->assertSame('application/json', MetadataMapper::extractMimeType($metadata));
    }

    public function testExtractMimeTypeFromPath(): void
    {
        $metadata = ['path' => 'document.pdf'];
        $this->assertSame('application/pdf', MetadataMapper::extractMimeType($metadata));

        $metadata = ['path' => 'image.jpg'];
        $this->assertSame('image/jpeg', MetadataMapper::extractMimeType($metadata));

        $metadata = ['path' => 'folder/file.txt'];
        $this->assertSame('text/plain', MetadataMapper::extractMimeType($metadata));
    }

    public function testExtractMimeTypeFromName(): void
    {
        $metadata = ['name' => 'document.pdf'];
        $this->assertSame('application/pdf', MetadataMapper::extractMimeType($metadata));

        $metadata = ['name' => 'image.PNG']; // Test case insensitivity
        $this->assertSame('image/png', MetadataMapper::extractMimeType($metadata));
    }

    public function testExtractMimeTypeDefault(): void
    {
        $metadata = ['path' => 'file.unknown'];
        $this->assertSame('application/octet-stream', MetadataMapper::extractMimeType($metadata));

        $metadata = [];
        $this->assertSame('application/octet-stream', MetadataMapper::extractMimeType($metadata));
    }

    public function testExtractVisibilityFromExplicitField(): void
    {
        $metadata = ['visibility' => Visibility::PUBLIC];
        $this->assertSame(Visibility::PUBLIC, MetadataMapper::extractVisibility($metadata));

        $metadata = ['visibility' => Visibility::PRIVATE];
        $this->assertSame(Visibility::PRIVATE, MetadataMapper::extractVisibility($metadata));

        $metadata = ['visibility' => 'PUBLIC']; // Test case insensitivity
        $this->assertSame(Visibility::PUBLIC, MetadataMapper::extractVisibility($metadata));
    }

    public function testExtractVisibilityFromPermissions(): void
    {
        $metadata = ['permissions' => 0o644]; // World-readable
        $this->assertSame(Visibility::PUBLIC, MetadataMapper::extractVisibility($metadata));

        $metadata = ['permissions' => 0o600]; // Not world-readable
        $this->assertSame(Visibility::PRIVATE, MetadataMapper::extractVisibility($metadata));

        $metadata = ['permissions' => '644']; // String format
        $this->assertSame(Visibility::PUBLIC, MetadataMapper::extractVisibility($metadata));
    }

    public function testExtractVisibilityFromMode(): void
    {
        $metadata = ['mode' => 0o755]; // World-readable
        $this->assertSame(Visibility::PUBLIC, MetadataMapper::extractVisibility($metadata));

        $metadata = ['mode' => 0o700]; // Not world-readable
        $this->assertSame(Visibility::PRIVATE, MetadataMapper::extractVisibility($metadata));
    }

    public function testExtractVisibilityFromFlags(): void
    {
        $metadata = ['public' => true];
        $this->assertSame(Visibility::PUBLIC, MetadataMapper::extractVisibility($metadata));

        $metadata = ['public' => false];
        $this->assertSame(Visibility::PRIVATE, MetadataMapper::extractVisibility($metadata));

        $metadata = ['private' => true];
        $this->assertSame(Visibility::PRIVATE, MetadataMapper::extractVisibility($metadata));

        $metadata = ['private' => false];
        $this->assertSame(Visibility::PUBLIC, MetadataMapper::extractVisibility($metadata));
    }

    public function testExtractVisibilityDefault(): void
    {
        $metadata = [];
        $this->assertSame(Visibility::PRIVATE, MetadataMapper::extractVisibility($metadata));

        $metadata = ['visibility' => 'invalid'];
        $this->assertSame(Visibility::PRIVATE, MetadataMapper::extractVisibility($metadata));
    }

    public function testExtractTimestampFromVariousFields(): void
    {
        $timestamp = 1640995200;

        $fields = [
            'timestamp',
            'last_modified',
            'lastmodified',
            'modified',
            'mtime',
            'modification_time',
            'date_modified',
        ];

        foreach ($fields as $field) {
            $metadata = [$field => $timestamp];
            $this->assertSame($timestamp, MetadataMapper::extractTimestamp($metadata));
        }
    }

    public function testExtractTimestampFromString(): void
    {
        $metadata = ['timestamp' => '2022-01-01 00:00:00'];
        $result = MetadataMapper::extractTimestamp($metadata);
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);

        $metadata = ['timestamp' => 'invalid date'];
        $this->assertNull(MetadataMapper::extractTimestamp($metadata));
    }

    public function testExtractTimestampDefault(): void
    {
        $metadata = [];
        $this->assertNull(MetadataMapper::extractTimestamp($metadata));
    }

    public function testCreateMinimalFileAttributes(): void
    {
        $attributes = MetadataMapper::createMinimalFileAttributes('file.txt', 1024);

        $this->assertInstanceOf(FileAttributes::class, $attributes);
        $this->assertSame('file.txt', $attributes->path());
        $this->assertSame(1024, $attributes->fileSize());
        $this->assertNull($attributes->visibility());
        $this->assertNull($attributes->lastModified());
        $this->assertNull($attributes->mimeType());
    }

    public function testCreateMinimalFileAttributesWithoutSize(): void
    {
        $attributes = MetadataMapper::createMinimalFileAttributes('file.txt');

        $this->assertInstanceOf(FileAttributes::class, $attributes);
        $this->assertSame('file.txt', $attributes->path());
        $this->assertNull($attributes->fileSize());
    }

    public function testCreateMinimalDirectoryAttributes(): void
    {
        $attributes = MetadataMapper::createMinimalDirectoryAttributes('folder');

        $this->assertInstanceOf(DirectoryAttributes::class, $attributes);
        $this->assertSame('folder', $attributes->path());
        $this->assertNull($attributes->visibility());
        $this->assertNull($attributes->lastModified());
    }

    public function testMergeFileAttributes(): void
    {
        $originalAttributes = new FileAttributes('file.txt', 1024);
        $additionalMetadata = [
            'mime_type' => 'text/plain',
            'visibility' => Visibility::PUBLIC,
            'timestamp' => 1640995200,
        ];

        $mergedAttributes = MetadataMapper::mergeFileAttributes($originalAttributes, $additionalMetadata);

        $this->assertSame('file.txt', $mergedAttributes->path());
        $this->assertSame(1024, $mergedAttributes->fileSize());
        $this->assertSame('text/plain', $mergedAttributes->mimeType());
        $this->assertSame(Visibility::PUBLIC, $mergedAttributes->visibility());
        $this->assertSame(1640995200, $mergedAttributes->lastModified());
    }

    public function testMergeFileAttributesPreservesExisting(): void
    {
        $originalAttributes = new FileAttributes(
            'file.txt',
            1024,
            Visibility::PRIVATE,
            1640995200,
            'text/plain'
        );
        $additionalMetadata = [
            'size' => 2048,
            'mime_type' => 'application/json',
            'visibility' => Visibility::PUBLIC,
            'timestamp' => 1641081600,
        ];

        $mergedAttributes = MetadataMapper::mergeFileAttributes($originalAttributes, $additionalMetadata);

        // Original values should be preserved
        $this->assertSame('file.txt', $mergedAttributes->path());
        $this->assertSame(1024, $mergedAttributes->fileSize());
        $this->assertSame('text/plain', $mergedAttributes->mimeType());
        $this->assertSame(Visibility::PRIVATE, $mergedAttributes->visibility());
        $this->assertSame(1640995200, $mergedAttributes->lastModified());
    }

    public function testMergeDirectoryAttributes(): void
    {
        $originalAttributes = new DirectoryAttributes('folder');
        $additionalMetadata = [
            'visibility' => Visibility::PUBLIC,
            'timestamp' => 1640995200,
        ];

        $mergedAttributes = MetadataMapper::mergeDirectoryAttributes($originalAttributes, $additionalMetadata);

        $this->assertSame('folder', $mergedAttributes->path());
        $this->assertSame(Visibility::PUBLIC, $mergedAttributes->visibility());
        $this->assertSame(1640995200, $mergedAttributes->lastModified());
    }

    public function testMergeDirectoryAttributesPreservesExisting(): void
    {
        $originalAttributes = new DirectoryAttributes(
            'folder',
            Visibility::PRIVATE,
            1640995200
        );
        $additionalMetadata = [
            'visibility' => Visibility::PUBLIC,
            'timestamp' => 1641081600,
        ];

        $mergedAttributes = MetadataMapper::mergeDirectoryAttributes($originalAttributes, $additionalMetadata);

        // Original values should be preserved
        $this->assertSame('folder', $mergedAttributes->path());
        $this->assertSame(Visibility::PRIVATE, $mergedAttributes->visibility());
        $this->assertSame(1640995200, $mergedAttributes->lastModified());
    }
}
