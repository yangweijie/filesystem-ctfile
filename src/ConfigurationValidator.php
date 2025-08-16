<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile;

use YangWeijie\FilesystemCtfile\Exceptions\CtFileConfigurationException;

/**
 * Configuration validation rules and logic for ctFile filesystem adapter.
 *
 * Provides comprehensive validation for ctFile connection parameters,
 * adapter settings, and optional features with detailed error reporting.
 * Supports nested configuration validation using dot notation.
 */
class ConfigurationValidator
{
    /**
     * Array of validation rules organized by configuration key.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $validationRules;

    /**
     * Create a new ConfigurationValidator instance.
     *
     * Initializes all validation rules for ctFile configuration parameters,
     * adapter settings, logging, caching, and retry mechanisms.
     */
    public function __construct()
    {
        $this->initializeValidationRules();
    }

    /**
     * Validate configuration array against defined rules.
     *
     * Performs comprehensive validation of the entire configuration array,
     * checking all defined rules and collecting validation errors.
     *
     * @param array $config The configuration array to validate
     * @return bool True if validation passes
     * @throws CtFileConfigurationException If validation fails with detailed error messages
     */
    public function validate(array $config): bool
    {
        $errors = [];

        foreach ($this->validationRules as $key => $rules) {
            $value = $this->getNestedValue($config, $key);

            foreach ($rules as $rule => $ruleValue) {
                $error = $this->validateRule($key, $value, $rule, $ruleValue);
                if ($error) {
                    $errors[] = $error;
                }
            }
        }

        if (!empty($errors)) {
            throw new CtFileConfigurationException(
                'Configuration validation failed: ' . implode(', ', $errors)
            );
        }

        return true;
    }

    /**
     * Validate a specific configuration key against its defined rules.
     *
     * Validates only the specified configuration key, useful for
     * incremental validation or when updating specific config values.
     *
     * @param array $config The configuration array containing the key
     * @param string $key The configuration key to validate (supports dot notation)
     * @return bool True if validation passes
     * @throws CtFileConfigurationException If validation fails for the specified key
     */
    public function validateKey(array $config, string $key): bool
    {
        if (!isset($this->validationRules[$key])) {
            return true; // No rules defined for this key
        }

        $value = $this->getNestedValue($config, $key);
        $errors = [];

        foreach ($this->validationRules[$key] as $rule => $ruleValue) {
            $error = $this->validateRule($key, $value, $rule, $ruleValue);
            if ($error) {
                $errors[] = $error;
            }
        }

        if (!empty($errors)) {
            throw new CtFileConfigurationException(
                'Configuration validation failed for key "' . $key . '": ' . implode(', ', $errors)
            );
        }

        return true;
    }

    /**
     * Get all validation rules.
     *
     * Returns the complete array of validation rules organized by
     * configuration key. Useful for debugging or extending validation.
     *
     * @return array<string, array<string, mixed>> Complete validation rules array
     */
    public function getValidationRules(): array
    {
        return $this->validationRules;
    }

    /**
     * Get validation rules for a specific configuration key.
     *
     * @param string $key The configuration key to get rules for
     * @return array<string, mixed> Array of validation rules for the key
     */
    public function getRulesForKey(string $key): array
    {
        return $this->validationRules[$key] ?? [];
    }

    /**
     * Check if a configuration key has validation rules defined.
     *
     * @param string $key The configuration key to check
     * @return bool True if rules exist for the key, false otherwise
     */
    public function hasRulesForKey(string $key): bool
    {
        return isset($this->validationRules[$key]);
    }

    /**
     * Add or update validation rules for a configuration key.
     *
     * Allows dynamic modification of validation rules at runtime.
     * Useful for extending validation or customizing rules for specific use cases.
     *
     * @param string $key The configuration key to set rules for
     * @param array<string, mixed> $rules Array of validation rules
     * @return void
     */
    public function setRulesForKey(string $key, array $rules): void
    {
        $this->validationRules[$key] = $rules;
    }

    /**
     * Remove validation rules for a configuration key.
     *
     * @param string $key The configuration key to remove rules for
     * @return void
     */
    public function removeRulesForKey(string $key): void
    {
        unset($this->validationRules[$key]);
    }

    /**
     * Initialize all validation rules for ctFile configuration.
     *
     * Sets up comprehensive validation rules for:
     * - ctFile connection parameters (host, port, credentials, timeouts)
     * - Adapter settings (paths, separators, behavior flags)
     * - Logging configuration (levels, channels, formatting)
     * - Cache configuration (drivers, TTL, behavior)
     * - Retry mechanism settings (attempts, delays, backoff)
     *
     * @return void
     */
    private function initializeValidationRules(): void
    {
        $this->validationRules = [
            // ctFile connection parameters
            'ctfile.host' => [
                'required' => true,
                'type' => 'string',
                'min_length' => 1,
                'max_length' => 255,
                'pattern' => '/^[a-zA-Z0-9.-]+$/',
            ],
            'ctfile.port' => [
                'type' => 'integer',
                'min' => 1,
                'max' => 65535,
            ],
            'ctfile.username' => [
                'required' => true,
                'type' => 'string',
                'min_length' => 1,
                'max_length' => 255,
            ],
            'ctfile.password' => [
                'required' => true,
                'type' => 'string',
                'min_length' => 1,
                'max_length' => 255,
            ],
            'ctfile.timeout' => [
                'type' => 'integer',
                'min' => 1,
                'max' => 3600, // 1 hour max
            ],
            'ctfile.ssl' => [
                'type' => 'boolean',
            ],
            'ctfile.passive' => [
                'type' => 'boolean',
            ],

            // Adapter settings
            'adapter.root_path' => [
                'type' => 'string',
                'pattern' => '/^\/.*$/', // Must start with /
            ],
            'adapter.path_separator' => [
                'type' => 'string',
                'length' => 1,
                'in' => ['/', '\\'],
            ],
            'adapter.case_sensitive' => [
                'type' => 'boolean',
            ],
            'adapter.create_directories' => [
                'type' => 'boolean',
            ],

            // Logging configuration
            'logging.enabled' => [
                'type' => 'boolean',
            ],
            'logging.level' => [
                'type' => 'string',
                'in' => ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'],
            ],
            'logging.channel' => [
                'type' => 'string',
                'min_length' => 1,
                'max_length' => 100,
                'pattern' => '/^[a-zA-Z0-9_-]+$/',
            ],

            // Cache configuration
            'cache.enabled' => [
                'type' => 'boolean',
            ],
            'cache.ttl' => [
                'type' => 'integer',
                'min' => 0,
                'max' => 86400, // 24 hours max
            ],
            'cache.driver' => [
                'type' => 'string',
                'in' => ['memory', 'file', 'redis', 'memcached'],
            ],

            // Optional advanced settings
            'retry.enabled' => [
                'type' => 'boolean',
            ],
            'retry.max_attempts' => [
                'type' => 'integer',
                'min' => 1,
                'max' => 10,
            ],
            'retry.delay' => [
                'type' => 'integer',
                'min' => 100, // milliseconds
                'max' => 10000,
            ],
        ];
    }

    /**
     * Validate a single rule against a configuration value.
     *
     * Supports various validation rules:
     * - required: Value must not be null or empty
     * - type: Value must match specified type (string, integer, boolean, etc.)
     * - min/max: Numeric range validation
     * - min_length/max_length/length: String length validation
     * - in: Value must be in specified array of allowed values
     * - pattern: Value must match specified regular expression
     *
     * @param string $key The configuration key being validated
     * @param mixed $value The value to validate
     * @param string $rule The validation rule name
     * @param mixed $ruleValue The rule parameter/constraint
     * @return string|null Error message if validation fails, null if passes
     */
    private function validateRule(string $key, $value, string $rule, $ruleValue): ?string
    {
        switch ($rule) {
            case 'required':
                if ($ruleValue && ($value === null || $value === '')) {
                    return "'{$key}' is required";
                }
                break;

            case 'type':
                if ($value !== null && !$this->validateType($value, $ruleValue)) {
                    return "'{$key}' must be of type {$ruleValue}";
                }
                break;

            case 'min':
                if (is_numeric($value) && $value < $ruleValue) {
                    return "'{$key}' must be at least {$ruleValue}";
                }
                break;

            case 'max':
                if (is_numeric($value) && $value > $ruleValue) {
                    return "'{$key}' must be at most {$ruleValue}";
                }
                break;

            case 'min_length':
                if (is_string($value) && strlen($value) < $ruleValue) {
                    return "'{$key}' must be at least {$ruleValue} character(s) long";
                }
                break;

            case 'max_length':
                if (is_string($value) && strlen($value) > $ruleValue) {
                    return "'{$key}' must be at most {$ruleValue} character(s) long";
                }
                break;

            case 'length':
                if (is_string($value) && strlen($value) !== $ruleValue) {
                    return "'{$key}' must be exactly {$ruleValue} character(s) long";
                }
                break;

            case 'in':
                if ($value !== null && !in_array($value, $ruleValue, true)) {
                    return "'{$key}' must be one of: " . implode(', ', $ruleValue);
                }
                break;

            case 'pattern':
                if (is_string($value) && !preg_match($ruleValue, $value)) {
                    return "'{$key}' format is invalid";
                }
                break;
        }

        return null;
    }

    /**
     * Validate that a value matches the expected type.
     *
     * Supports PHP native types: string, integer, boolean, array, float, numeric.
     *
     * @param mixed $value The value to check
     * @param string $expectedType The expected type name
     * @return bool True if value matches expected type, false otherwise
     */
    private function validateType($value, string $expectedType): bool
    {
        return match ($expectedType) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            'float' => is_float($value),
            'numeric' => is_numeric($value),
            default => false,
        };
    }

    /**
     * Get nested configuration value using dot notation.
     *
     * Allows accessing nested array values using dot notation (e.g., 'ctfile.host').
     * Returns null if any part of the path doesn't exist.
     *
     * @param array $config The configuration array to search
     * @param string $key The dot-notation key path
     * @return mixed The configuration value or null if not found
     */
    private function getNestedValue(array $config, string $key)
    {
        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }
}
