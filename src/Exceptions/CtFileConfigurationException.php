<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Exceptions;

/**
 * Exception thrown when ctFile configuration is invalid or missing.
 *
 * This includes validation errors, missing required configuration,
 * invalid configuration values, and configuration-related issues.
 */
class CtFileConfigurationException extends CtFileException
{
    /**
     * Create an exception for missing required configuration.
     *
     * @param string $configKey The missing configuration key
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function missingRequiredConfig(string $configKey, ?\Throwable $previous = null): static
    {
        $message = 'Missing required ctFile configuration';
        $context = ['config_key' => $configKey];

        return new static($message, 'validate_config', '', $context, 0, $previous);
    }

    /**
     * Create an exception for invalid configuration value.
     *
     * @param string $configKey The invalid configuration key
     * @param mixed $value The invalid value
     * @param string $expectedType The expected type or format
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function invalidConfigValue(
        string $configKey,
        $value,
        string $expectedType,
        ?\Throwable $previous = null
    ): static {
        $message = 'Invalid ctFile configuration value';
        $context = [
            'config_key' => $configKey,
            'provided_value' => $value,
            'expected_type' => $expectedType,
        ];

        return new static($message, 'validate_config', '', $context, 0, $previous);
    }

    /**
     * Create an exception for invalid host configuration.
     *
     * @param string $host The invalid host value
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function invalidHost(string $host, ?\Throwable $previous = null): static
    {
        $message = 'Invalid ctFile host configuration';
        $context = ['host' => $host];

        return new static($message, 'validate_config', '', $context, 0, $previous);
    }

    /**
     * Create an exception for invalid port configuration.
     *
     * @param mixed $port The invalid port value
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function invalidPort($port, ?\Throwable $previous = null): static
    {
        $message = 'Invalid ctFile port configuration';
        $context = ['port' => $port];

        return new static($message, 'validate_config', '', $context, 0, $previous);
    }

    /**
     * Create an exception for invalid timeout configuration.
     *
     * @param mixed $timeout The invalid timeout value
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function invalidTimeout($timeout, ?\Throwable $previous = null): static
    {
        $message = 'Invalid ctFile timeout configuration';
        $context = ['timeout' => $timeout];

        return new static($message, 'validate_config', '', $context, 0, $previous);
    }

    /**
     * Create an exception for invalid credentials configuration.
     *
     * @param string $issue The specific issue with credentials
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function invalidCredentials(string $issue, ?\Throwable $previous = null): static
    {
        $message = "Invalid ctFile credentials configuration: {$issue}";

        return new static($message, 'validate_config', '', [], 0, $previous);
    }

    /**
     * Create an exception for configuration file not found.
     *
     * @param string $configPath The configuration file path
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function configFileNotFound(string $configPath, ?\Throwable $previous = null): static
    {
        $message = 'ctFile configuration file not found';
        $context = ['config_path' => $configPath];

        return new static($message, 'load_config', $configPath, $context, 0, $previous);
    }

    /**
     * Create an exception for configuration file parsing error.
     *
     * @param string $configPath The configuration file path
     * @param string $parseError The parsing error message
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function configParseError(string $configPath, string $parseError, ?\Throwable $previous = null): static
    {
        $message = "Failed to parse ctFile configuration file: {$parseError}";
        $context = ['config_path' => $configPath, 'parse_error' => $parseError];

        return new static($message, 'parse_config', $configPath, $context, 0, $previous);
    }

    /**
     * Create an exception for unsupported configuration option.
     *
     * @param string $configKey The unsupported configuration key
     * @param array $supportedOptions List of supported options
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function unsupportedConfigOption(
        string $configKey,
        array $supportedOptions = [],
        ?\Throwable $previous = null
    ): static {
        $message = 'Unsupported ctFile configuration option';
        $context = [
            'config_key' => $configKey,
            'supported_options' => $supportedOptions,
        ];

        return new static($message, 'validate_config', '', $context, 0, $previous);
    }

    /**
     * Create an exception for configuration validation failure.
     *
     * @param array $validationErrors Array of validation error messages
     * @param \Throwable|null $previous The previous exception
     * @return static
     */
    public static function validationFailed(array $validationErrors, ?\Throwable $previous = null): static
    {
        $message = 'ctFile configuration validation failed';
        $context = ['validation_errors' => $validationErrors];

        return new static($message, 'validate_config', '', $context, 0, $previous);
    }
}
