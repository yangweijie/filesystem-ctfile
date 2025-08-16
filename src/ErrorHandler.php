<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile;

use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileAuthenticationException;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileConfigurationException;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileConnectionException;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileException;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileOperationException;

/**
 * Centralized error handling and exception management for ctFile operations.
 *
 * Converts ctFile-specific errors to appropriate Flysystem exceptions while
 * maintaining detailed context information and integrating with PSR-3 logging.
 */
class ErrorHandler
{
    private LoggerInterface $logger;

    /**
     * Create a new ErrorHandler instance.
     *
     * @param LoggerInterface|null $logger PSR-3 logger instance
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Handle ctFile errors and convert them to appropriate Flysystem exceptions.
     *
     * @param \Throwable $error The original ctFile error
     * @param string $operation The operation being performed
     * @param string $path The file or directory path involved
     * @throws \Throwable The converted Flysystem exception
     */
    public function handleCtFileError(\Throwable $error, string $operation, string $path = ''): void
    {
        $context = $this->extractErrorContext($error, $operation, $path);

        // Log the error with appropriate severity
        $this->logError($error, $context);

        // Convert to appropriate Flysystem exception
        $flysystemException = $this->convertToFlysystemException($error, $operation, $path, $context);

        throw $flysystemException;
    }

    /**
     * Create a Flysystem exception of the specified type.
     *
     * @param string $type The exception type (read, write, delete, etc.)
     * @param string $message The exception message
     * @param string $path The file or directory path
     * @param \Throwable|null $previous The previous exception
     * @return \Throwable The created Flysystem exception
     */
    public function createFlysystemException(
        string $type,
        string $message,
        string $path = '',
        ?\Throwable $previous = null
    ): \Throwable {
        return match ($type) {
            'read' => new UnableToReadFile($message, 0, $previous),
            'write' => new UnableToWriteFile($message, 0, $previous),
            'delete' => new UnableToDeleteFile($message, 0, $previous),
            'delete_directory' => new UnableToDeleteDirectory($message, 0, $previous),
            'create_directory' => new UnableToCreateDirectory($message, 0, $previous),
            'copy' => new UnableToCopyFile($message, 0, $previous),
            'move' => new UnableToMoveFile($message, 0, $previous),
            'metadata' => new UnableToRetrieveMetadata($message, 0, $previous),
            'visibility' => new UnableToSetVisibility($message, 0, $previous),
            'file_exists' => new UnableToCheckFileExistence($message, 0, $previous),
            'directory_exists' => new UnableToCheckDirectoryExistence($message, 0, $previous),
            default => $previous ?? new CtFileException($message, $type, $path)
        };
    }

    /**
     * Log an error with appropriate context information.
     *
     * @param \Throwable $error The error to log
     * @param array $context Additional context information
     */
    public function logError(\Throwable $error, array $context = []): void
    {
        $logContext = $this->sanitizeLogContext($context);
        $logContext['exception_class'] = get_class($error);
        $logContext['exception_code'] = $error->getCode();

        if ($error instanceof CtFileException) {
            $logContext['ctfile_operation'] = $error->getOperation();
            $logContext['ctfile_path'] = $error->getPath();
            $logContext['ctfile_context'] = $error->getContext();
        }

        // Determine log level based on exception type
        $level = $this->determineLogLevel($error);

        $this->logger->log($level, $this->formatLogMessage($error), $logContext);
    }

    /**
     * Convert ctFile error to appropriate Flysystem exception.
     *
     * @param \Throwable $error The original error
     * @param string $operation The operation being performed
     * @param string $path The file or directory path
     * @param array $context Error context information
     * @return \Throwable The converted exception
     */
    private function convertToFlysystemException(
        \Throwable $error,
        string $operation,
        string $path,
        array $context
    ): \Throwable {
        // If it's already a CtFile exception, convert based on operation
        if ($error instanceof CtFileException) {
            return $this->convertCtFileException($error, $operation, $path);
        }

        // Handle generic errors based on operation type
        return $this->convertGenericError($error, $operation, $path, $context);
    }

    /**
     * Convert CtFileException to appropriate Flysystem exception.
     *
     * @param CtFileException $error The CtFile exception
     * @param string $operation The operation being performed
     * @param string $path The file or directory path
     * @return \Throwable The converted exception
     */
    private function convertCtFileException(CtFileException $error, string $operation, string $path): \Throwable
    {
        $message = $this->buildFlysystemMessage($error->getMessage(), $operation, $path);

        return match (true) {
            $error instanceof CtFileConnectionException => $this->createFlysystemException(
                $this->mapOperationToExceptionType($operation),
                $message,
                $path,
                $error
            ),
            $error instanceof CtFileAuthenticationException => $this->createFlysystemException(
                $this->mapOperationToExceptionType($operation),
                $message,
                $path,
                $error
            ),
            $error instanceof CtFileOperationException => $this->createFlysystemException(
                $this->mapOperationToExceptionType($operation),
                $message,
                $path,
                $error
            ),
            $error instanceof CtFileConfigurationException => $this->createFlysystemException(
                $this->mapOperationToExceptionType($operation),
                $message,
                $path,
                $error
            ),
            default => $this->createFlysystemException(
                $this->mapOperationToExceptionType($operation),
                $message,
                $path,
                $error
            )
        };
    }

    /**
     * Convert generic error to appropriate Flysystem exception.
     *
     * @param \Throwable $error The original error
     * @param string $operation The operation being performed
     * @param string $path The file or directory path
     * @param array $context Error context information
     * @return \Throwable The converted exception
     */
    private function convertGenericError(
        \Throwable $error,
        string $operation,
        string $path,
        array $context
    ): \Throwable {
        $message = $this->buildFlysystemMessage($error->getMessage(), $operation, $path);
        $exceptionType = $this->mapOperationToExceptionType($operation);

        return $this->createFlysystemException($exceptionType, $message, $path, $error);
    }

    /**
     * Map operation name to Flysystem exception type.
     *
     * @param string $operation The operation name
     * @return string The exception type
     */
    private function mapOperationToExceptionType(string $operation): string
    {
        return match ($operation) {
            'read', 'download', 'read_stream' => 'read',
            'write', 'upload', 'write_stream' => 'write',
            'delete', 'delete_file' => 'delete',
            'delete_directory', 'remove_directory' => 'delete_directory',
            'create_directory', 'mkdir' => 'create_directory',
            'copy' => 'copy',
            'move', 'rename' => 'move',
            'file_size', 'last_modified', 'mime_type', 'get_metadata' => 'metadata',
            'set_visibility' => 'visibility',
            'file_exists' => 'file_exists',
            'directory_exists' => 'directory_exists',
            default => 'operation'
        };
    }

    /**
     * Extract error context information.
     *
     * @param \Throwable $error The error
     * @param string $operation The operation
     * @param string $path The path
     * @return array Context information
     */
    private function extractErrorContext(\Throwable $error, string $operation, string $path): array
    {
        $context = [
            'operation' => $operation,
            'path' => $path,
            'error_message' => $error->getMessage(),
            'error_code' => $error->getCode(),
            'error_file' => $error->getFile(),
            'error_line' => $error->getLine(),
        ];

        if ($error instanceof CtFileException) {
            $context['ctfile_operation'] = $error->getOperation();
            $context['ctfile_path'] = $error->getPath();
            $context['ctfile_context'] = $error->getContext();
        }

        return $context;
    }

    /**
     * Build a Flysystem-compatible error message.
     *
     * @param string $originalMessage The original error message
     * @param string $operation The operation
     * @param string $path The path
     * @return string The formatted message
     */
    private function buildFlysystemMessage(string $originalMessage, string $operation, string $path): string
    {
        $parts = [];

        if (!empty($operation)) {
            $parts[] = "Unable to {$operation}";
        }

        if (!empty($path)) {
            $parts[] = "file at path: {$path}";
        }

        if (!empty($originalMessage)) {
            $parts[] = "Reason: {$originalMessage}";
        }

        return implode(' ', $parts);
    }

    /**
     * Format log message for the error.
     *
     * @param \Throwable $error The error
     * @return string The formatted log message
     */
    private function formatLogMessage(\Throwable $error): string
    {
        $message = "ctFile operation failed: {$error->getMessage()}";

        if ($error instanceof CtFileException) {
            $operation = $error->getOperation();
            $path = $error->getPath();

            if (!empty($operation)) {
                $message .= " [Operation: {$operation}]";
            }

            if (!empty($path)) {
                $message .= " [Path: {$path}]";
            }
        }

        return $message;
    }

    /**
     * Determine appropriate log level based on exception type.
     *
     * @param \Throwable $error The error
     * @return string The log level
     */
    private function determineLogLevel(\Throwable $error): string
    {
        return match (true) {
            $error instanceof CtFileConnectionException => 'error',
            $error instanceof CtFileAuthenticationException => 'error',
            $error instanceof CtFileConfigurationException => 'error',
            $error instanceof CtFileOperationException => 'warning',
            default => 'error'
        };
    }

    /**
     * Sanitize log context to remove sensitive information.
     *
     * @param array $context The context array
     * @return array The sanitized context
     */
    private function sanitizeLogContext(array $context): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'auth', 'credential'];

        return array_map(function ($value) use ($sensitiveKeys) {
            if (is_array($value)) {
                return $this->sanitizeLogContext($value);
            }

            return $value;
        }, array_filter($context, function ($key) use ($sensitiveKeys) {
            $keyLower = strtolower((string) $key);
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (str_contains($keyLower, $sensitiveKey)) {
                    return false;
                }
            }

            return true;
        }, ARRAY_FILTER_USE_KEY));
    }
}
