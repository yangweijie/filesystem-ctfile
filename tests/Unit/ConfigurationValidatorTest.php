<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Tests\Unit;

use PHPUnit\Framework\TestCase;
use YangWeijie\FilesystemCtfile\ConfigurationValidator;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileConfigurationException;

class ConfigurationValidatorTest extends TestCase
{
    private ConfigurationValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ConfigurationValidator();
    }

    public function test_validate_passes_with_valid_configuration(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'port' => 21,
                'username' => 'user',
                'password' => 'pass',
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

        $this->assertTrue($this->validator->validate($config));
    }

    public function test_validate_throws_exception_for_missing_required_host(): void
    {
        $config = [
            'ctfile' => [
                'username' => 'user',
                'password' => 'pass',
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'ctfile.host' is required");

        $this->validator->validate($config);
    }

    public function test_validate_throws_exception_for_missing_required_username(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'password' => 'pass',
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'ctfile.username' is required");

        $this->validator->validate($config);
    }

    public function test_validate_throws_exception_for_missing_required_password(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'username' => 'user',
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'ctfile.password' is required");

        $this->validator->validate($config);
    }

    public function test_validate_throws_exception_for_invalid_port_type(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'port' => 'invalid',
                'username' => 'user',
                'password' => 'pass',
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'ctfile.port' must be of type integer");

        $this->validator->validate($config);
    }

    public function test_validate_throws_exception_for_port_out_of_range(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'port' => 70000,
                'username' => 'user',
                'password' => 'pass',
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'ctfile.port' must be at most 65535");

        $this->validator->validate($config);
    }

    public function test_validate_throws_exception_for_port_below_minimum(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'port' => 0,
                'username' => 'user',
                'password' => 'pass',
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'ctfile.port' must be at least 1");

        $this->validator->validate($config);
    }

    public function test_validate_throws_exception_for_invalid_timeout(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'username' => 'user',
                'password' => 'pass',
                'timeout' => -1,
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'ctfile.timeout' must be at least 1");

        $this->validator->validate($config);
    }

    public function test_validate_throws_exception_for_timeout_above_maximum(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'username' => 'user',
                'password' => 'pass',
                'timeout' => 4000,
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'ctfile.timeout' must be at most 3600");

        $this->validator->validate($config);
    }

    public function test_validate_throws_exception_for_invalid_boolean_type(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'username' => 'user',
                'password' => 'pass',
                'ssl' => 'yes',
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'ctfile.ssl' must be of type boolean");

        $this->validator->validate($config);
    }

    public function test_validate_throws_exception_for_invalid_host_pattern(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'invalid_host!@#',
                'username' => 'user',
                'password' => 'pass',
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'ctfile.host' format is invalid");

        $this->validator->validate($config);
    }

    public function test_validate_throws_exception_for_empty_host(): void
    {
        $config = [
            'ctfile' => [
                'host' => '',
                'username' => 'user',
                'password' => 'pass',
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'ctfile.host' is required");

        $this->validator->validate($config);
    }

    public function test_validate_throws_exception_for_host_too_long(): void
    {
        $config = [
            'ctfile' => [
                'host' => str_repeat('a', 256),
                'username' => 'user',
                'password' => 'pass',
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'ctfile.host' must be at most 255 character(s) long");

        $this->validator->validate($config);
    }

    public function test_validate_throws_exception_for_invalid_path_separator_length(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'username' => 'user',
                'password' => 'pass',
            ],
            'adapter' => [
                'path_separator' => '//',
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'adapter.path_separator' must be exactly 1 character(s) long");

        $this->validator->validate($config);
    }

    public function test_validate_throws_exception_for_invalid_path_separator_value(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'username' => 'user',
                'password' => 'pass',
            ],
            'adapter' => [
                'path_separator' => '|',
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'adapter.path_separator' must be one of: /, \\");

        $this->validator->validate($config);
    }

    public function test_validate_throws_exception_for_invalid_root_path_pattern(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'username' => 'user',
                'password' => 'pass',
            ],
            'adapter' => [
                'root_path' => 'relative/path',
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'adapter.root_path' format is invalid");

        $this->validator->validate($config);
    }

    public function test_validate_throws_exception_for_invalid_log_level(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'username' => 'user',
                'password' => 'pass',
            ],
            'logging' => [
                'level' => 'invalid_level',
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'logging.level' must be one of: debug, info, notice, warning, error, critical, alert, emergency");

        $this->validator->validate($config);
    }

    public function test_validate_throws_exception_for_invalid_cache_driver(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'username' => 'user',
                'password' => 'pass',
            ],
            'cache' => [
                'driver' => 'invalid_driver',
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'cache.driver' must be one of: memory, file, redis, memcached");

        $this->validator->validate($config);
    }

    public function test_validate_throws_exception_for_invalid_cache_ttl(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'username' => 'user',
                'password' => 'pass',
            ],
            'cache' => [
                'ttl' => -1,
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'cache.ttl' must be at least 0");

        $this->validator->validate($config);
    }

    public function test_validate_throws_exception_for_cache_ttl_above_maximum(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'username' => 'user',
                'password' => 'pass',
            ],
            'cache' => [
                'ttl' => 90000,
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'cache.ttl' must be at most 86400");

        $this->validator->validate($config);
    }

    public function test_validate_key_validates_specific_key(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'port' => 21,
            ],
        ];

        $this->assertTrue($this->validator->validateKey($config, 'ctfile.host'));
        $this->assertTrue($this->validator->validateKey($config, 'ctfile.port'));
    }

    public function test_validate_key_throws_exception_for_invalid_key(): void
    {
        $config = [
            'ctfile' => [
                'port' => 'invalid',
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage('Configuration validation failed for key "ctfile.port"');

        $this->validator->validateKey($config, 'ctfile.port');
    }

    public function test_validate_key_returns_true_for_undefined_key(): void
    {
        $config = [];

        $this->assertTrue($this->validator->validateKey($config, 'undefined.key'));
    }

    public function test_get_validation_rules_returns_all_rules(): void
    {
        $rules = $this->validator->getValidationRules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('ctfile.host', $rules);
        $this->assertArrayHasKey('ctfile.port', $rules);
        $this->assertArrayHasKey('adapter.root_path', $rules);
        $this->assertArrayHasKey('logging.level', $rules);
        $this->assertArrayHasKey('cache.driver', $rules);
    }

    public function test_get_rules_for_key_returns_specific_rules(): void
    {
        $rules = $this->validator->getRulesForKey('ctfile.host');

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('required', $rules);
        $this->assertArrayHasKey('type', $rules);
        $this->assertTrue($rules['required']);
        $this->assertEquals('string', $rules['type']);
    }

    public function test_get_rules_for_key_returns_empty_for_undefined_key(): void
    {
        $rules = $this->validator->getRulesForKey('undefined.key');

        $this->assertIsArray($rules);
        $this->assertEmpty($rules);
    }

    public function test_has_rules_for_key_returns_correct_boolean(): void
    {
        $this->assertTrue($this->validator->hasRulesForKey('ctfile.host'));
        $this->assertFalse($this->validator->hasRulesForKey('undefined.key'));
    }

    public function test_set_rules_for_key_adds_new_rules(): void
    {
        $newRules = ['type' => 'string', 'required' => true];

        $this->validator->setRulesForKey('custom.key', $newRules);

        $this->assertTrue($this->validator->hasRulesForKey('custom.key'));
        $this->assertEquals($newRules, $this->validator->getRulesForKey('custom.key'));
    }

    public function test_remove_rules_for_key_removes_rules(): void
    {
        $this->assertTrue($this->validator->hasRulesForKey('ctfile.host'));

        $this->validator->removeRulesForKey('ctfile.host');

        $this->assertFalse($this->validator->hasRulesForKey('ctfile.host'));
    }

    public function test_validate_allows_valid_retry_configuration(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'username' => 'user',
                'password' => 'pass',
            ],
            'retry' => [
                'enabled' => true,
                'max_attempts' => 3,
                'delay' => 1000,
            ],
        ];

        $this->assertTrue($this->validator->validate($config));
    }

    public function test_validate_throws_exception_for_invalid_retry_max_attempts(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'username' => 'user',
                'password' => 'pass',
            ],
            'retry' => [
                'max_attempts' => 15,
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'retry.max_attempts' must be at most 10");

        $this->validator->validate($config);
    }

    public function test_validate_throws_exception_for_invalid_retry_delay(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'username' => 'user',
                'password' => 'pass',
            ],
            'retry' => [
                'delay' => 50,
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'retry.delay' must be at least 100");

        $this->validator->validate($config);
    }

    public function test_validate_allows_valid_logging_channel_pattern(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'username' => 'user',
                'password' => 'pass',
            ],
            'logging' => [
                'channel' => 'my-custom_channel123',
            ],
        ];

        $this->assertTrue($this->validator->validate($config));
    }

    public function test_validate_throws_exception_for_invalid_logging_channel_pattern(): void
    {
        $config = [
            'ctfile' => [
                'host' => 'example.com',
                'username' => 'user',
                'password' => 'pass',
            ],
            'logging' => [
                'channel' => 'invalid channel!',
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'logging.channel' format is invalid");

        $this->validator->validate($config);
    }

    public function test_multiple_validation_errors_are_combined(): void
    {
        $config = [
            'ctfile' => [
                'port' => 'invalid',
                'timeout' => -5,
            ],
        ];

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessageMatches("/Configuration validation failed:.*'ctfile.host' is required.*'ctfile.port' must be of type integer.*'ctfile.username' is required.*'ctfile.password' is required.*'ctfile.timeout' must be at least 1/");

        $this->validator->validate($config);
    }
}
