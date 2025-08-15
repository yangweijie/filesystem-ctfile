<?php

declare(strict_types=1);

namespace Yangweijie\FilesystemCtlife;

use InvalidArgumentException;

/**
 * CTFile 配置管理类
 * 
 * 负责管理 CTFile API 的配置信息，包括认证凭据、API 端点、应用标识等。
 * 提供配置验证、默认值设置和配置项访问方法。
 */
class CTFileConfig
{
    /**
     * 配置数组
     */
    private array $config;

    /**
     * 默认配置
     */
    private const DEFAULT_CONFIG = [
        'api_base_url' => 'https://rest.ctfile.com/v1',
        'upload_base_url' => 'https://upload.ctfile.com',
        'storage_type' => 'public',
        'cache_ttl' => 3600,
        'retry_attempts' => 3,
        'timeout' => 30,
        'connect_timeout' => 10,
    ];

    /**
     * 必需的配置项
     */
    private const REQUIRED_CONFIG = [
        'session',
        'app_id',
    ];

    /**
     * 构造函数
     *
     * @param array $config 配置数组
     * @throws InvalidArgumentException 当配置无效时
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge(self::DEFAULT_CONFIG, $config);
        $this->validate();
    }

    /**
     * 获取 session token
     *
     * @return string
     */
    public function getSession(): string
    {
        return $this->config['session'];
    }

    /**
     * 获取应用标识
     *
     * @return string
     */
    public function getAppId(): string
    {
        return $this->config['app_id'];
    }

    /**
     * 获取 API 基础 URL
     *
     * @return string
     */
    public function getApiBaseUrl(): string
    {
        return rtrim($this->config['api_base_url'], '/');
    }

    /**
     * 获取上传基础 URL
     *
     * @return string
     */
    public function getUploadBaseUrl(): string
    {
        return rtrim($this->config['upload_base_url'], '/');
    }

    /**
     * 获取存储类型
     *
     * @return string
     */
    public function getStorageType(): string
    {
        return $this->config['storage_type'];
    }

    /**
     * 获取缓存 TTL（秒）
     *
     * @return int
     */
    public function getCacheTtl(): int
    {
        return (int) $this->config['cache_ttl'];
    }

    /**
     * 获取重试次数
     *
     * @return int
     */
    public function getRetryAttempts(): int
    {
        return (int) $this->config['retry_attempts'];
    }

    /**
     * 获取请求超时时间（秒）
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return (int) $this->config['timeout'];
    }

    /**
     * 获取连接超时时间（秒）
     *
     * @return int
     */
    public function getConnectTimeout(): int
    {
        return (int) $this->config['connect_timeout'];
    }

    /**
     * 验证配置
     *
     * @throws InvalidArgumentException 当配置无效时
     */
    public function validate(): void
    {
        // 检查必需的配置项
        foreach (self::REQUIRED_CONFIG as $key) {
            if (empty($this->config[$key])) {
                throw new InvalidArgumentException("Missing required config: {$key}");
            }
        }

        // 验证 session token 格式
        if (!is_string($this->config['session']) || strlen($this->config['session']) < 10) {
            throw new InvalidArgumentException('Session token must be a valid string with at least 10 characters');
        }

        // 验证 app_id 格式
        if (!is_string($this->config['app_id']) || strlen($this->config['app_id']) < 3) {
            throw new InvalidArgumentException('App ID must be a valid string with at least 3 characters');
        }

        // 验证存储类型
        if (!in_array($this->config['storage_type'], ['public', 'private'], true)) {
            throw new InvalidArgumentException('Storage type must be either "public" or "private"');
        }

        // 验证数值配置
        $numericConfigs = ['cache_ttl', 'retry_attempts', 'timeout', 'connect_timeout'];
        foreach ($numericConfigs as $key) {
            if (!is_numeric($this->config[$key]) || $this->config[$key] < 0) {
                throw new InvalidArgumentException("Config {$key} must be a non-negative number");
            }
        }

        // 验证 URL 格式
        $urlConfigs = ['api_base_url', 'upload_base_url'];
        foreach ($urlConfigs as $key) {
            if (!filter_var($this->config[$key], FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException("Config {$key} must be a valid URL");
            }
        }
    }

    /**
     * 获取所有配置
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->config;
    }

    /**
     * 获取指定配置项
     *
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * 检查是否为私有云存储
     *
     * @return bool
     */
    public function isPrivateStorage(): bool
    {
        return $this->getStorageType() === 'private';
    }

    /**
     * 检查是否为公有云存储
     *
     * @return bool
     */
    public function isPublicStorage(): bool
    {
        return $this->getStorageType() === 'public';
    }
}
