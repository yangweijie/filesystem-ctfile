<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Tests\Unit\Exceptions;

use YangWeijie\FilesystemCtfile\Exceptions\CtFileConfigurationException;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileException;
use YangWeijie\FilesystemCtfile\Tests\TestCase;

class CtFileConfigurationExceptionTest extends TestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new CtFileConfigurationException('Test message');

        $this->assertInstanceOf(CtFileException::class, $exception);
        $this->assertInstanceOf(CtFileConfigurationException::class, $exception);
    }

    public function testMissingRequiredConfig(): void
    {
        $previous = new \Exception('Config error');
        $exception = CtFileConfigurationException::missingRequiredConfig('host', $previous);

        $this->assertStringContainsString('Missing required ctFile configuration', $exception->getMessage());
        $this->assertSame('validate_config', $exception->getOperation());
        $this->assertSame(['config_key' => 'host'], $exception->getContext());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testInvalidConfigValue(): void
    {
        $exception = CtFileConfigurationException::invalidConfigValue('port', 'invalid', 'integer');

        $this->assertStringContainsString('Invalid ctFile configuration value', $exception->getMessage());
        $this->assertSame('validate_config', $exception->getOperation());
        $this->assertSame([
            'config_key' => 'port',
            'provided_value' => 'invalid',
            'expected_type' => 'integer',
        ], $exception->getContext());
    }

    public function testInvalidHost(): void
    {
        $exception = CtFileConfigurationException::invalidHost('');

        $this->assertStringContainsString('Invalid ctFile host configuration', $exception->getMessage());
        $this->assertSame('validate_config', $exception->getOperation());
        $this->assertSame(['host' => ''], $exception->getContext());
    }

    public function testInvalidPort(): void
    {
        $exception = CtFileConfigurationException::invalidPort(-1);

        $this->assertStringContainsString('Invalid ctFile port configuration', $exception->getMessage());
        $this->assertSame('validate_config', $exception->getOperation());
        $this->assertSame(['port' => -1], $exception->getContext());
    }

    public function testInvalidTimeout(): void
    {
        $exception = CtFileConfigurationException::invalidTimeout('not_a_number');

        $this->assertStringContainsString('Invalid ctFile timeout configuration', $exception->getMessage());
        $this->assertSame('validate_config', $exception->getOperation());
        $this->assertSame(['timeout' => 'not_a_number'], $exception->getContext());
    }

    public function testInvalidCredentials(): void
    {
        $exception = CtFileConfigurationException::invalidCredentials('username cannot be empty');

        $this->assertStringContainsString('Invalid ctFile credentials configuration: username cannot be empty', $exception->getMessage());
        $this->assertSame('validate_config', $exception->getOperation());
    }

    public function testConfigFileNotFound(): void
    {
        $exception = CtFileConfigurationException::configFileNotFound('/path/to/config.json');

        $this->assertStringContainsString('ctFile configuration file not found', $exception->getMessage());
        $this->assertSame('load_config', $exception->getOperation());
        $this->assertSame('/path/to/config.json', $exception->getPath());
        $this->assertSame(['config_path' => '/path/to/config.json'], $exception->getContext());
    }

    public function testConfigParseError(): void
    {
        $exception = CtFileConfigurationException::configParseError('/config.json', 'Invalid JSON syntax');

        $this->assertStringContainsString('Failed to parse ctFile configuration file: Invalid JSON syntax', $exception->getMessage());
        $this->assertSame('parse_config', $exception->getOperation());
        $this->assertSame('/config.json', $exception->getPath());
        $this->assertSame([
            'config_path' => '/config.json',
            'parse_error' => 'Invalid JSON syntax',
        ], $exception->getContext());
    }

    public function testUnsupportedConfigOption(): void
    {
        $supportedOptions = ['host', 'port', 'username', 'password'];
        $exception = CtFileConfigurationException::unsupportedConfigOption('invalid_option', $supportedOptions);

        $this->assertStringContainsString('Unsupported ctFile configuration option', $exception->getMessage());
        $this->assertSame('validate_config', $exception->getOperation());
        $this->assertSame([
            'config_key' => 'invalid_option',
            'supported_options' => $supportedOptions,
        ], $exception->getContext());
    }

    public function testValidationFailed(): void
    {
        $validationErrors = [
            'host is required',
            'port must be between 1 and 65535',
            'username cannot be empty',
        ];
        $exception = CtFileConfigurationException::validationFailed($validationErrors);

        $this->assertStringContainsString('ctFile configuration validation failed', $exception->getMessage());
        $this->assertSame('validate_config', $exception->getOperation());
        $this->assertSame(['validation_errors' => $validationErrors], $exception->getContext());
    }
}
