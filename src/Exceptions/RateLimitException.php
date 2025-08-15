<?php

declare(strict_types=1);

namespace Yangweijie\FilesystemCtlife\Exceptions;

/**
 * API 限制异常类
 * 
 * 当达到 API 调用频率限制时抛出此异常
 */
class RateLimitException extends CTFileException
{
    /**
     * 重试等待时间（秒）
     */
    private int $retryAfter;

    /**
     * 构造函数
     *
     * @param string $message 错误消息
     * @param int $retryAfter 重试等待时间（秒）
     * @param int $code 错误代码
     * @param int $errorCode CTFile 错误代码
     * @param \Throwable|null $previous 前一个异常
     */
    public function __construct(string $message = 'Rate limit exceeded', int $retryAfter = 60, int $code = 429, int $errorCode = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $errorCode, [], $previous);
        $this->retryAfter = $retryAfter;
    }

    /**
     * 获取重试等待时间
     *
     * @return int 等待时间（秒）
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * 创建请求频率限制异常
     *
     * @param int $retryAfter 重试等待时间
     * @return static
     */
    public static function requestRateLimit(int $retryAfter = 60): static
    {
        return new static("Request rate limit exceeded. Retry after {$retryAfter} seconds.", $retryAfter, 429);
    }

    /**
     * 创建上传频率限制异常
     *
     * @param int $retryAfter 重试等待时间
     * @return static
     */
    public static function uploadRateLimit(int $retryAfter = 300): static
    {
        return new static("Upload rate limit exceeded. Retry after {$retryAfter} seconds.", $retryAfter, 429);
    }

    /**
     * 创建下载频率限制异常
     *
     * @param int $retryAfter 重试等待时间
     * @return static
     */
    public static function downloadRateLimit(int $retryAfter = 60): static
    {
        return new static("Download rate limit exceeded. Retry after {$retryAfter} seconds.", $retryAfter, 429);
    }

    /**
     * 创建存储空间限制异常
     *
     * @param int $usedSpace 已使用空间（字节）
     * @param int $totalSpace 总空间（字节）
     * @return static
     */
    public static function storageQuotaExceeded(int $usedSpace, int $totalSpace): static
    {
        $usedMB = round($usedSpace / 1024 / 1024, 2);
        $totalMB = round($totalSpace / 1024 / 1024, 2);
        return new static("Storage quota exceeded. Used: {$usedMB}MB / {$totalMB}MB", 0, 507);
    }

    /**
     * 创建并发连接限制异常
     *
     * @param int $maxConnections 最大连接数
     * @return static
     */
    public static function concurrentConnectionLimit(int $maxConnections): static
    {
        return new static("Concurrent connection limit exceeded. Max: {$maxConnections}", 60, 429);
    }
}
