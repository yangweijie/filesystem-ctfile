<?php

declare(strict_types=1);

namespace Yangweijie\FilesystemCtlife\Exceptions;

use Exception;

/**
 * CTFile 基础异常类
 * 
 * 所有 CTFile 相关异常的基类
 */
class CTFileException extends Exception
{
    /**
     * 错误代码
     */
    protected int $errorCode;

    /**
     * 错误详细信息
     */
    protected array $errorDetails;

    /**
     * 构造函数
     *
     * @param string $message 错误消息
     * @param int $code 异常代码
     * @param int $errorCode CTFile 错误代码
     * @param array $errorDetails 错误详细信息
     * @param Exception|null $previous 前一个异常
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        int $errorCode = 0,
        array $errorDetails = [],
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->errorDetails = $errorDetails;
    }

    /**
     * 获取 CTFile 错误代码
     *
     * @return int
     */
    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    /**
     * 获取错误详细信息
     *
     * @return array
     */
    public function getErrorDetails(): array
    {
        return $this->errorDetails;
    }

    /**
     * 创建一个通用的 CTFile 异常
     *
     * @param string $message 错误消息
     * @param int $errorCode CTFile 错误代码
     * @param array $errorDetails 错误详细信息
     * @return static
     */
    public static function create(string $message, int $errorCode = 0, array $errorDetails = []): static
    {
        return new static($message, 0, $errorCode, $errorDetails);
    }
}
