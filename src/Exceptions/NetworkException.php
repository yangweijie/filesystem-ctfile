<?php

declare(strict_types=1);

namespace Yangweijie\FilesystemCtlife\Exceptions;

/**
 * 网络异常类
 * 
 * 当网络连接或通信出现问题时抛出此异常
 */
class NetworkException extends CTFileException
{
    /**
     * 构造函数
     *
     * @param string $message 错误消息
     * @param int $code 错误代码
     * @param int $errorCode CTFile 错误代码
     * @param \Throwable|null $previous 前一个异常
     */
    public function __construct(string $message = 'Network error occurred', int $code = 0, int $errorCode = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $errorCode, [], $previous);
    }

    /**
     * 创建连接超时异常
     *
     * @param string $url 请求URL
     * @param int $timeout 超时时间
     * @return static
     */
    public static function connectionTimeout(string $url, int $timeout): static
    {
        return new static("Connection timeout after {$timeout}s for URL: {$url}", 408);
    }

    /**
     * 创建连接失败异常
     *
     * @param string $url 请求URL
     * @param string $reason 失败原因
     * @return static
     */
    public static function connectionFailed(string $url, string $reason = ''): static
    {
        $message = "Failed to connect to: {$url}";
        if (!empty($reason)) {
            $message .= " - {$reason}";
        }
        return new static($message, 0);
    }

    /**
     * 创建DNS解析失败异常
     *
     * @param string $hostname 主机名
     * @return static
     */
    public static function dnsResolutionFailed(string $hostname): static
    {
        return new static("DNS resolution failed for hostname: {$hostname}", 0);
    }

    /**
     * 创建SSL证书验证失败异常
     *
     * @param string $url 请求URL
     * @return static
     */
    public static function sslVerificationFailed(string $url): static
    {
        return new static("SSL certificate verification failed for: {$url}", 0);
    }

    /**
     * 创建网络不可达异常
     *
     * @param string $url 请求URL
     * @return static
     */
    public static function networkUnreachable(string $url): static
    {
        return new static("Network unreachable for: {$url}", 0);
    }

    /**
     * 创建响应读取失败异常
     *
     * @param string $reason 失败原因
     * @return static
     */
    public static function responseReadFailed(string $reason = ''): static
    {
        $message = 'Failed to read response from server';
        if (!empty($reason)) {
            $message .= " - {$reason}";
        }
        return new static($message, 0);
    }
}
