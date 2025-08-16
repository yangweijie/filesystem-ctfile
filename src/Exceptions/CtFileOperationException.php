<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Exceptions;

/**
 * Exception thrown when ctFile file or directory operations fail.
 *
 * This includes file upload/download failures, directory operations,
 * file system errors, and other operation-specific issues.
 */
class CtFileOperationException extends CtFileException
{
    /**
     * Create an exception for file upload failure.
     *
     * @param string $localPath The local file path
     * @param string $remotePath The remote file path
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function uploadFailed(string $localPath, string $remotePath, ?\Throwable $previous = null): static
    {
        $message = 'Failed to upload file to ctFile server';
        $context = ['local_path' => $localPath, 'remote_path' => $remotePath];

        return new static($message, 'upload', $remotePath, $context, 0, $previous);
    }

    /**
     * Create an exception for file download failure.
     *
     * @param string $remotePath The remote file path
     * @param string $localPath The local file path
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function downloadFailed(string $remotePath, string $localPath, ?\Throwable $previous = null): static
    {
        $message = 'Failed to download file from ctFile server';
        $context = ['remote_path' => $remotePath, 'local_path' => $localPath];

        return new static($message, 'download', $remotePath, $context, 0, $previous);
    }

    /**
     * Create an exception for file deletion failure.
     *
     * @param string $path The file path that failed to delete
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function deleteFailed(string $path, ?\Throwable $previous = null): static
    {
        $message = 'Failed to delete file on ctFile server';

        return new static($message, 'delete', $path, [], 0, $previous);
    }

    /**
     * Create an exception for directory creation failure.
     *
     * @param string $path The directory path that failed to create
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function createDirectoryFailed(string $path, ?\Throwable $previous = null): static
    {
        $message = 'Failed to create directory on ctFile server';

        return new static($message, 'create_directory', $path, [], 0, $previous);
    }

    /**
     * Create an exception for directory deletion failure.
     *
     * @param string $path The directory path that failed to delete
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function deleteDirectoryFailed(string $path, ?\Throwable $previous = null): static
    {
        $message = 'Failed to delete directory on ctFile server';

        return new static($message, 'delete_directory', $path, [], 0, $previous);
    }

    /**
     * Create an exception for directory listing failure.
     *
     * @param string $path The directory path that failed to list
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function listDirectoryFailed(string $path, ?\Throwable $previous = null): static
    {
        $message = 'Failed to list directory contents on ctFile server';

        return new static($message, 'list_directory', $path, [], 0, $previous);
    }

    /**
     * Create an exception for file move/rename failure.
     *
     * @param string $sourcePath The source file path
     * @param string $destinationPath The destination file path
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function moveFailed(string $sourcePath, string $destinationPath, ?\Throwable $previous = null): static
    {
        $message = 'Failed to move/rename file on ctFile server';
        $context = ['source_path' => $sourcePath, 'destination_path' => $destinationPath];

        return new static($message, 'move', $sourcePath, $context, 0, $previous);
    }

    /**
     * Create an exception for file copy failure.
     *
     * @param string $sourcePath The source file path
     * @param string $destinationPath The destination file path
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function copyFailed(string $sourcePath, string $destinationPath, ?\Throwable $previous = null): static
    {
        $message = 'Failed to copy file on ctFile server';
        $context = ['source_path' => $sourcePath, 'destination_path' => $destinationPath];

        return new static($message, 'copy', $sourcePath, $context, 0, $previous);
    }

    /**
     * Create an exception for file not found.
     *
     * @param string $path The file path that was not found
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function fileNotFound(string $path, ?\Throwable $previous = null): static
    {
        $message = 'File not found on ctFile server';

        return new static($message, 'file_access', $path, [], 0, $previous);
    }

    /**
     * Create an exception for directory not found.
     *
     * @param string $path The directory path that was not found
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function directoryNotFound(string $path, ?\Throwable $previous = null): static
    {
        $message = 'Directory not found on ctFile server';

        return new static($message, 'directory_access', $path, [], 0, $previous);
    }

    /**
     * Create an exception for file already exists.
     *
     * @param string $path The file path that already exists
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function fileAlreadyExists(string $path, ?\Throwable $previous = null): static
    {
        $message = 'File already exists on ctFile server';

        return new static($message, 'file_creation', $path, [], 0, $previous);
    }

    /**
     * Create an exception for insufficient disk space.
     *
     * @param string $path The path where space is insufficient
     * @param int $requiredBytes The required bytes
     * @param int $availableBytes The available bytes
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function insufficientSpace(
        string $path,
        int $requiredBytes,
        int $availableBytes,
        ?\Throwable $previous = null
    ): static {
        $message = 'Insufficient disk space on ctFile server';
        $context = [
            'required_bytes' => $requiredBytes,
            'available_bytes' => $availableBytes,
            'required_mb' => round($requiredBytes / 1024 / 1024, 2),
            'available_mb' => round($availableBytes / 1024 / 1024, 2),
        ];

        return new static($message, 'space_check', $path, $context, 0, $previous);
    }
}
