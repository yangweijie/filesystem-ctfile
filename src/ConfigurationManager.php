<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile;

use YangWeijie\FilesystemCtfile\Exceptions\CtFileConfigurationException;

/**
 * Configuration manager for ctFile filesystem adapter.
 *
 * Handles configuration validation, management, and provides default values
 * for ctFile connection parameters and adapter settings.
 */
class ConfigurationManager
{
    private array $config;

    private ConfigurationValidator $validator;

    public function __construct(array $config = [], ?ConfigurationValidator $validator = null)
    {
        $this->validator = $validator ?? new ConfigurationValidator();
        $this->config = $this->mergeConfigArrays($this->getDefaultConfig(), $config);
    }

    /**
     * Validate the current configuration.
     *
     * @throws CtFileConfigurationException
     */
    public function validate(): bool
    {
        return $this->validator->validate($this->config);
    }

    /**
     * Validate a specific configuration key.
     *
     * @throws CtFileConfigurationException
     */
    public function validateKey(string $key): bool
    {
        return $this->validator->validateKey($this->config, $key);
    }

    /**
     * Get the configuration validator instance.
     */
    public function getValidator(): ConfigurationValidator
    {
        return $this->validator;
    }

    /**
     * Get a configuration value by key.
     */
    public function get(string $key, $default = null)
    {
        return $this->getNestedValue($key) ?? $default;
    }

    /**
     * Set a configuration value.
     */
    public function set(string $key, $value): void
    {
        $this->setNestedValue($key, $value);
    }

    /**
     * Merge additional configuration.
     */
    public function merge(array $config): void
    {
        $this->config = $this->mergeConfigArrays($this->config, $config);
    }

    /**
     * Get all configuration as array.
     */
    public function toArray(): array
    {
        return $this->config;
    }

    /**
     * Get default configuration.
     */
    public static function getDefaultConfig(): array
    {
        return [
            'ctfile' => [
                'host' => '',
                'port' => 21,
                'username' => '',
                'password' => '',
                'timeout' => 30,
                'ssl' => false,
                'passive' => true,
            ],
            'adapter' => [
                'root_path' => '/',
                'path_separator' => '/',
                'case_sensitive' => true,
                'create_directories' => true,
            ],
            'logging' => [
                'enabled' => false,
                'level' => 'info',
                'channel' => 'filesystem-ctfile',
            ],
            'cache' => [
                'enabled' => false,
                'ttl' => 300,
                'driver' => 'memory',
            ],
        ];
    }

    /**
     * Get nested configuration value using dot notation.
     */
    private function getNestedValue(string $key)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set nested configuration value using dot notation.
     */
    private function setNestedValue(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    /**
     * Recursively merge configuration arrays.
     */
    private function mergeConfigArrays(array $default, array $override): array
    {
        $result = $default;

        foreach ($override as $key => $value) {
            if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                $result[$key] = $this->mergeConfigArrays($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
