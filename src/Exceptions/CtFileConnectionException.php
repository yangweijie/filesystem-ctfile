<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Exceptions;

/**
 * Exception thrown when ctFile connection operations fail.
 *
 * This includes connection establishment, authentication,
 * network timeouts, and connection-related errors.
 */
class CtFileConnectionException extends CtFileException
{
    /**
     * Create an exception for connection failure.
     *
     * @param string $host The host that failed to connect
     * @param int $port The port that failed to connect
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function connectionFailed(string $host, int $port, ?\Throwable $previous = null): static
    {
        $message = 'Failed to establish connection to ctFile server';
        $context = ['host' => $host, 'port' => $port];

        return new static($message, 'connect', "{$host}:{$port}", $context, 0, $previous);
    }

    /**
     * Create an exception for authentication failure.
     *
     * @param string $username The username that failed to authenticate
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function authenticationFailed(string $username, ?\Throwable $previous = null): static
    {
        $message = 'Authentication failed for ctFile server';
        $context = ['username' => $username];

        return new static($message, 'authenticate', '', $context, 0, $previous);
    }

    /**
     * Create an exception for connection timeout.
     *
     * @param string $host The host that timed out
     * @param int $port The port that timed out
     * @param int $timeout The timeout value in seconds
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function connectionTimeout(string $host, int $port, int $timeout, ?\Throwable $previous = null): static
    {
        $message = "Connection to ctFile server timed out after {$timeout} seconds";
        $context = ['host' => $host, 'port' => $port, 'timeout' => $timeout];

        return new static($message, 'connect', "{$host}:{$port}", $context, 0, $previous);
    }

    /**
     * Create an exception for connection lost.
     *
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function connectionLost(?\Throwable $previous = null): static
    {
        $message = 'Connection to ctFile server was lost';

        return new static($message, 'maintain_connection', '', [], 0, $previous);
    }

    /**
     * Create an exception for SSL/TLS errors.
     *
     * @param string $host The host with SSL issues
     * @param int $port The port with SSL issues
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function sslError(string $host, int $port, ?\Throwable $previous = null): static
    {
        $message = 'SSL/TLS connection error with ctFile server';
        $context = ['host' => $host, 'port' => $port];

        return new static($message, 'ssl_connect', "{$host}:{$port}", $context, 0, $previous);
    }

    /**
     * Create an exception for network errors.
     *
     * @param string $message The network error message
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function networkError(string $message, ?\Throwable $previous = null): static
    {
        return new static("Network error: {$message}", 'network_operation', '', [], 0, $previous);
    }
}
