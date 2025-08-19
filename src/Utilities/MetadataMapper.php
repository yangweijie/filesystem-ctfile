<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Utilities;

use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\Visibility;

/**
 * Utility class for converting ctFile metadata to Flysystem FileAttributes objects.
 *
 * Provides static methods to map between ctFile-specific metadata formats
 * and standardized Flysystem attribute objects.
 */
class MetadataMapper
{
    /**
     * Default MIME type for files when detection fails.
     */
    private const DEFAULT_MIME_TYPE = 'application/octet-stream';

    /**
     * Default visibility when not specified.
     */
    private const DEFAULT_VISIBILITY = Visibility::PRIVATE;

    /**
     * Convert ctFile metadata to Flysystem FileAttributes object.
     *
     * @param array $ctFileMetadata Raw metadata from ctFile
     * @return FileAttributes Flysystem file attributes object
     */
    public static function toFileAttributes(array $ctFileMetadata): FileAttributes
    {
        $path = $ctFileMetadata['path'] ?? '';
        $fileSize = isset($ctFileMetadata['size']) ? (int) $ctFileMetadata['size'] : null;
        $lastModified = self::extractTimestamp($ctFileMetadata);
        $mimeType = self::extractMimeType($ctFileMetadata);
        $visibility = self::extractVisibility($ctFileMetadata);

        $attributes = new FileAttributes(
            $path,
            $fileSize,
            $visibility,
            $lastModified,
            $mimeType,
            ['id'=>$ctFileMetadata['id']]
        );

        return $attributes;
    }

    /**
     * Convert ctFile metadata to Flysystem DirectoryAttributes object.
     *
     * @param array $ctFileMetadata Raw metadata from ctFile
     * @return DirectoryAttributes Flysystem directory attributes object
     */
    public static function toDirectoryAttributes(array $ctFileMetadata): DirectoryAttributes
    {
        $path = $ctFileMetadata['path'] ?? '';
        $lastModified = self::extractTimestamp($ctFileMetadata);
        $visibility = self::extractVisibility($ctFileMetadata);

        $attributes = new DirectoryAttributes(
            $path,
            $visibility,
            $lastModified
        );

        return $attributes;
    }

    /**
     * Extract MIME type from ctFile metadata.
     *
     * @param array $metadata ctFile metadata array
     * @return string MIME type string
     */
    public static function extractMimeType(array $metadata): string
    {
        // Check for explicit MIME type
        if (isset($metadata['mime_type']) && is_string($metadata['mime_type'])) {
            return $metadata['mime_type'];
        }

        if (isset($metadata['mimetype']) && is_string($metadata['mimetype'])) {
            return $metadata['mimetype'];
        }

        if (isset($metadata['content_type']) && is_string($metadata['content_type'])) {
            return $metadata['content_type'];
        }

        // Try to determine from file extension
        if (isset($metadata['path']) && is_string($metadata['path'])) {
            $extension = strtolower(pathinfo($metadata['path'], PATHINFO_EXTENSION));
            $mimeType = self::getMimeTypeFromExtension($extension);
            if ($mimeType !== null) {
                return $mimeType;
            }
        }

        // Try to determine from filename
        if (isset($metadata['name']) && is_string($metadata['name'])) {
            $extension = strtolower(pathinfo($metadata['name'], PATHINFO_EXTENSION));
            $mimeType = self::getMimeTypeFromExtension($extension);
            if ($mimeType !== null) {
                return $mimeType;
            }
        }

        return self::DEFAULT_MIME_TYPE;
    }

    /**
     * Extract visibility from ctFile metadata.
     *
     * @param array $metadata ctFile metadata array
     * @return string Flysystem visibility constant
     */
    public static function extractVisibility(array $metadata): string
    {
        // Check for explicit visibility
        if (isset($metadata['visibility'])) {
            $visibility = strtolower((string) $metadata['visibility']);
            if (in_array($visibility, [Visibility::PUBLIC, Visibility::PRIVATE], true)) {
                return $visibility;
            }
        }

        // Check for permissions (Unix-style)
        if (isset($metadata['permissions'])) {
            $permissions = $metadata['permissions'];
            if (is_string($permissions)) {
                $permissions = octdec($permissions);
            }
            if (is_int($permissions)) {
                // Check if others have read permission (world-readable)
                return ($permissions & 0o004) ? Visibility::PUBLIC : Visibility::PRIVATE;
            }
        }

        // Check for mode (Unix-style)
        if (isset($metadata['mode'])) {
            $mode = $metadata['mode'];
            if (is_string($mode)) {
                $mode = octdec($mode);
            }
            if (is_int($mode)) {
                // Check if others have read permission (world-readable)
                return ($mode & 0o004) ? Visibility::PUBLIC : Visibility::PRIVATE;
            }
        }

        // Check for public flag
        if (isset($metadata['public'])) {
            return $metadata['public'] ? Visibility::PUBLIC : Visibility::PRIVATE;
        }

        // Check for private flag
        if (isset($metadata['private'])) {
            return $metadata['private'] ? Visibility::PRIVATE : Visibility::PUBLIC;
        }

        return self::DEFAULT_VISIBILITY;
    }

    /**
     * Extract timestamp from ctFile metadata.
     *
     * @param array $metadata ctFile metadata array
     * @return int|null Unix timestamp or null if not available
     */
    public static function extractTimestamp(array $metadata): ?int
    {
        // Check for various timestamp fields
        $timestampFields = [
            'timestamp',
            'last_modified',
            'lastmodified',
            'modified',
            'mtime',
            'modification_time',
            'date_modified',
        ];

        foreach ($timestampFields as $field) {
            if (isset($metadata[$field])) {
                $timestamp = $metadata[$field];

                if (is_int($timestamp)) {
                    return $timestamp;
                }

                if (is_string($timestamp)) {
                    $parsed = strtotime($timestamp);
                    if ($parsed !== false) {
                        return $parsed;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get MIME type from file extension.
     *
     * @param string $extension File extension (without dot)
     * @return string|null MIME type or null if unknown
     */
    private static function getMimeTypeFromExtension(string $extension): ?string
    {
        $mimeTypes = [
            // Text files
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'html' => 'text/html',
            'htm' => 'text/html',
            'xml' => 'text/xml',
            'css' => 'text/css',
            'js' => 'text/javascript',
            'json' => 'application/json',

            // Images
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',

            // Documents
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',

            // Archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            '7z' => 'application/x-7z-compressed',

            // Audio
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'flac' => 'audio/flac',

            // Video
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'wmv' => 'video/x-ms-wmv',
            'flv' => 'video/x-flv',
            'webm' => 'video/webm',
        ];

        return $mimeTypes[$extension] ?? null;
    }

    /**
     * Create FileAttributes with minimal information.
     *
     * @param string $path File path
     * @param int|null $size File size in bytes
     * @return FileAttributes Minimal file attributes object
     */
    public static function createMinimalFileAttributes(string $path, ?int $size = null): FileAttributes
    {
        return new FileAttributes($path, $size);
    }

    /**
     * Create DirectoryAttributes with minimal information.
     *
     * @param string $path Directory path
     * @return DirectoryAttributes Minimal directory attributes object
     */
    public static function createMinimalDirectoryAttributes(string $path): DirectoryAttributes
    {
        return new DirectoryAttributes($path);
    }

    /**
     * Merge additional metadata into existing FileAttributes.
     *
     * @param FileAttributes $attributes Existing attributes
     * @param array $additionalMetadata Additional metadata to merge
     * @return FileAttributes New FileAttributes with merged data
     */
    public static function mergeFileAttributes(FileAttributes $attributes, array $additionalMetadata): FileAttributes
    {
        $path = $attributes->path();
        $fileSize = $attributes->fileSize() ?? (isset($additionalMetadata['size']) ? (int) $additionalMetadata['size'] : null);
        $visibility = $attributes->visibility() ?? self::extractVisibility($additionalMetadata);
        $lastModified = $attributes->lastModified() ?? self::extractTimestamp($additionalMetadata);
        $mimeType = $attributes->mimeType() ?? self::extractMimeType($additionalMetadata);

        return new FileAttributes($path, $fileSize, $visibility, $lastModified, $mimeType);
    }

    /**
     * Merge additional metadata into existing DirectoryAttributes.
     *
     * @param DirectoryAttributes $attributes Existing attributes
     * @param array $additionalMetadata Additional metadata to merge
     * @return DirectoryAttributes New DirectoryAttributes with merged data
     */
    public static function mergeDirectoryAttributes(DirectoryAttributes $attributes, array $additionalMetadata): DirectoryAttributes
    {
        $path = $attributes->path();
        $visibility = $attributes->visibility() ?? self::extractVisibility($additionalMetadata);
        $lastModified = $attributes->lastModified() ?? self::extractTimestamp($additionalMetadata);

        return new DirectoryAttributes($path, $visibility, $lastModified);
    }
}
