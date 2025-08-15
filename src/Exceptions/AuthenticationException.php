<?php

declare(strict_types=1);

namespace Yangweijie\FilesystemCtlife\Exceptions;

/**
 * 认证异常类
 * 
 * 当 CTFile API 认证失败时抛出此异常
 */
class AuthenticationException extends CTFileException
{
    /**
     * 构造函数
     *
     * @param string $message 错误消息
     * @param int $code 错误代码
     * @param int $errorCode CTFile 错误代码
     * @param \Throwable|null $previous 前一个异常
     */
    public function __construct(string $message = 'Authentication failed', int $code = 401, int $errorCode = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $errorCode, [], $previous);
    }

    /**
     * 创建无效会话异常
     *
     * @param string $session 会话标识
     * @return static
     */
    public static function invalidSession(string $session): static
    {
        return new static("Invalid session token: {$session}", 401);
    }

    /**
     * 创建无效应用ID异常
     *
     * @param string $appId 应用ID
     * @return static
     */
    public static function invalidAppId(string $appId): static
    {
        return new static("Invalid app ID: {$appId}", 401);
    }

    /**
     * 创建会话过期异常
     *
     * @return static
     */
    public static function sessionExpired(): static
    {
        return new static('Session has expired', 401);
    }

    /**
     * 创建权限不足异常
     *
     * @param string $operation 操作名称
     * @return static
     */
    public static function insufficientPermissions(string $operation): static
    {
        return new static("Insufficient permissions for operation: {$operation}", 403);
    }

    /**
     * 创建账户被禁用异常
     *
     * @return static
     */
    public static function accountDisabled(): static
    {
        return new static('Account has been disabled', 403);
    }
}
