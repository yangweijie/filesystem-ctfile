<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Exceptions;

/**
 * Exception thrown when ctFile authentication operations fail.
 *
 * This includes login failures, credential validation errors,
 * permission denied errors, and session-related authentication issues.
 */
class CtFileAuthenticationException extends CtFileException
{
    /**
     * Create an exception for invalid credentials.
     *
     * @param string $username The username that failed authentication
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function invalidCredentials(string $username, ?\Throwable $previous = null): static
    {
        $message = 'Invalid credentials provided for ctFile authentication';
        $context = ['username' => $username];

        return new static($message, 'authenticate', '', $context, 0, $previous);
    }

    /**
     * Create an exception for expired credentials.
     *
     * @param string $username The username with expired credentials
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function credentialsExpired(string $username, ?\Throwable $previous = null): static
    {
        $message = 'Credentials have expired for ctFile authentication';
        $context = ['username' => $username];

        return new static($message, 'authenticate', '', $context, 0, $previous);
    }

    /**
     * Create an exception for account locked.
     *
     * @param string $username The locked username
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function accountLocked(string $username, ?\Throwable $previous = null): static
    {
        $message = 'Account is locked for ctFile authentication';
        $context = ['username' => $username];

        return new static($message, 'authenticate', '', $context, 0, $previous);
    }

    /**
     * Create an exception for insufficient permissions.
     *
     * @param string $operation The operation that was denied
     * @param string $path The path that access was denied to
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function permissionDenied(string $operation, string $path = '', ?\Throwable $previous = null): static
    {
        $message = 'Permission denied for ctFile operation';
        $context = ['required_permission' => $operation];

        return new static($message, $operation, $path, $context, 0, $previous);
    }

    /**
     * Create an exception for session expired.
     *
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function sessionExpired(?\Throwable $previous = null): static
    {
        $message = 'ctFile session has expired';

        return new static($message, 'session_check', '', [], 0, $previous);
    }

    /**
     * Create an exception for authentication required.
     *
     * @param string $operation The operation that requires authentication
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function authenticationRequired(string $operation, ?\Throwable $previous = null): static
    {
        $message = 'Authentication required for ctFile operation';

        return new static($message, $operation, '', [], 0, $previous);
    }

    /**
     * Create an exception for two-factor authentication required.
     *
     * @param string $username The username requiring 2FA
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function twoFactorRequired(string $username, ?\Throwable $previous = null): static
    {
        $message = 'Two-factor authentication required for ctFile access';
        $context = ['username' => $username];

        return new static($message, 'authenticate', '', $context, 0, $previous);
    }
}
