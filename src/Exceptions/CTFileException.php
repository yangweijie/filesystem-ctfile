<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Exceptions;

use League\Flysystem\FilesystemException;

/**
 * Base exception class for all ctFile-related errors.
 *
 * Implements Flysystem's FilesystemException to maintain compatibility
 * with the Flysystem ecosystem while providing ctFile-specific context.
 */
class CtFileException extends \RuntimeException implements FilesystemException
{
    /**
     * The operation that was being performed when the exception occurred.
     */
    private string $operation;

    /**
     * The file or directory path involved in the operation.
     */
    private string $path;

    /**
     * Additional context information about the error.
     */
    private array $context;

    /**
     * Create a new CtFileException.
     *
     * @param string $message The exception message
     * @param string $operation The operation being performed
     * @param string $path The file or directory path
     * @param array $context Additional context information
     * @param int $code The exception code
     * @param \Throwable|null $previous The previous exception
     */
    public function __construct(
        string $message,
        string $operation = '',
        string $path = '',
        array $context = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $this->operation = $operation;
        $this->path = $path;
        $this->context = $context;

        // Enhance the message with operation and path context
        $enhancedMessage = $this->buildEnhancedMessage($message, $operation, $path);

        parent::__construct($enhancedMessage, $code, $previous);
    }

    /**
     * Get the operation that was being performed.
     *
     * @return string The operation name
     */
    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * Get the file or directory path involved.
     *
     * @return string The path
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get additional context information.
     *
     * @return array The context array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set additional context information.
     *
     * @param array $context The context array
     * @return self
     */
    public function setContext(array $context): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Add a single context item.
     *
     * @param string $key The context key
     * @param mixed $value The context value
     * @return self
     */
    public function addContext(string $key, $value): self
    {
        $this->context[$key] = $value;

        return $this;
    }

    /**
     * Build an enhanced error message with operation and path context.
     *
     * @param string $message The original message
     * @param string $operation The operation being performed
     * @param string $path The file or directory path
     * @return string The enhanced message
     */
    private function buildEnhancedMessage(string $message, string $operation, string $path): string
    {
        $parts = [$message];

        if (!empty($operation)) {
            $parts[] = "Operation: {$operation}";
        }

        if (!empty($path)) {
            $parts[] = "Path: {$path}";
        }

        return implode(' | ', $parts);
    }

    /**
     * Create a new exception with operation context.
     *
     * @param string $message The exception message
     * @param string $operation The operation being performed
     * @param string $path The file or directory path
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function forOperation(
        string $message,
        string $operation,
        string $path = '',
        ?\Throwable $previous = null
    ): static {
        return new static($message, $operation, $path, [], 0, $previous);
    }

    /**
     * Create a new exception with path context.
     *
     * @param string $message The exception message
     * @param string $path The file or directory path
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function forPath(string $message, string $path, ?\Throwable $previous = null): static
    {
        return new static($message, '', $path, [], 0, $previous);
    }
}
