<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Tests\Unit;

use PHPUnit\Framework\TestCase;
use YangWeijie\FilesystemCtfile\ConfigurationManager;
use YangWeijie\FilesystemCtfile\ConfigurationValidator;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileConfigurationException;

class ConfigurationManagerTest extends TestCase
{
    private ConfigurationManager $configManager;

    protected function setUp(): void
    {
        $this->configManager = new ConfigurationManager();
    }

    public function test_constructor_with_empty_config_uses_defaults(): void
    {
        $manager = new ConfigurationManager();
        $config = $manager->toArray();

        $this->assertArrayHasKey('ctfile', $config);
        $this->assertArrayHasKey('adapter', $config);
        $this->assertArrayHasKey('logging', $config);
        $this->assertArrayHasKey('cache', $config);

        $this->assertEquals(21, $config['ctfile']['port']);
        $this->assertEquals('/', $config['adapter']['root_path']);
        $this->assertFalse($config['logging']['enabled']);
    }

    public function test_constructor_merges_provided_config_with_defaults(): void
    {
        $customConfig = [
            'ctfile' => [
                'host' => 'example.com',
                'port' => 2121,
            ],
            'logging' => [
                'enabled' => true,
            ],
        ];

        $manager = new ConfigurationManager($customConfig);
        $config = $manager->toArray();

        $this->assertEquals('example.com', $config['ctfile']['host']);
        $this->assertEquals(2121, $config['ctfile']['port']);
        $this->assertEquals('', $config['ctfile']['username']); // Default preserved
        $this->assertTrue($config['logging']['enabled']);
        $this->assertEquals('info', $config['logging']['level']); // Default preserved
    }

    public function test_get_returns_correct_values(): void
    {
        $this->configManager->set('ctfile.host', 'test.com');

        $this->assertEquals('test.com', $this->configManager->get('ctfile.host'));
        $this->assertEquals(21, $this->configManager->get('ctfile.port'));
        $this->assertNull($this->configManager->get('nonexistent.key'));
        $this->assertEquals('default', $this->configManager->get('nonexistent.key', 'default'));
    }

    public function test_set_updates_configuration_values(): void
    {
        $this->configManager->set('ctfile.host', 'newhost.com');
        $this->configManager->set('ctfile.port', 2222);
        $this->configManager->set('logging.enabled', true);

        $this->assertEquals('newhost.com', $this->configManager->get('ctfile.host'));
        $this->assertEquals(2222, $this->configManager->get('ctfile.port'));
        $this->assertTrue($this->configManager->get('logging.enabled'));
    }

    public function test_merge_combines_configurations(): void
    {
        $this->configManager->set('ctfile.host', 'original.com');

        $additionalConfig = [
            'ctfile' => [
                'port' => 3333,
                'ssl' => true,
            ],
            'cache' => [
                'enabled' => true,
                'ttl' => 600,
            ],
        ];

        $this->configManager->merge($additionalConfig);

        $this->assertEquals('original.com', $this->configManager->get('ctfile.host'));
        $this->assertEquals(3333, $this->configManager->get('ctfile.port'));
        $this->assertTrue($this->configManager->get('ctfile.ssl'));
        $this->assertTrue($this->configManager->get('cache.enabled'));
        $this->assertEquals(600, $this->configManager->get('cache.ttl'));
    }

    public function test_validate_passes_with_valid_configuration(): void
    {
        $this->configManager->set('ctfile.host', 'valid.com');
        $this->configManager->set('ctfile.username', 'user');
        $this->configManager->set('ctfile.password', 'pass');

        $this->assertTrue($this->configManager->validate());
    }

    public function test_validate_throws_exception_for_missing_required_fields(): void
    {
        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessageMatches("/Configuration validation failed:.*'ctfile.host' is required.*'ctfile.username' is required.*'ctfile.password' is required/");

        $this->configManager->validate();
    }

    public function test_validate_throws_exception_for_invalid_types(): void
    {
        $this->configManager->set('ctfile.host', 'valid.com');
        $this->configManager->set('ctfile.username', 'user');
        $this->configManager->set('ctfile.password', 'pass');
        $this->configManager->set('ctfile.port', 'invalid_port'); // Should be integer

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'ctfile.port' must be of type integer");

        $this->configManager->validate();
    }

    public function test_validate_throws_exception_for_out_of_range_values(): void
    {
        $this->configManager->set('ctfile.host', 'valid.com');
        $this->configManager->set('ctfile.username', 'user');
        $this->configManager->set('ctfile.password', 'pass');
        $this->configManager->set('ctfile.port', 70000); // Above max port

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'ctfile.port' must be at most 65535");

        $this->configManager->validate();
    }

    public function test_validate_throws_exception_for_invalid_enum_values(): void
    {
        $this->configManager->set('ctfile.host', 'valid.com');
        $this->configManager->set('ctfile.username', 'user');
        $this->configManager->set('ctfile.password', 'pass');
        $this->configManager->set('logging.level', 'invalid_level');

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'logging.level' must be one of: debug, info, notice, warning, error, critical, alert, emergency");

        $this->configManager->validate();
    }

    public function test_validate_throws_exception_for_invalid_path_separator_length(): void
    {
        $this->configManager->set('ctfile.host', 'valid.com');
        $this->configManager->set('ctfile.username', 'user');
        $this->configManager->set('ctfile.password', 'pass');
        $this->configManager->set('adapter.path_separator', '//'); // Should be 1 character

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'adapter.path_separator' must be exactly 1 character(s) long");

        $this->configManager->validate();
    }

    public function test_validate_allows_valid_boolean_values(): void
    {
        $this->configManager->set('ctfile.host', 'valid.com');
        $this->configManager->set('ctfile.username', 'user');
        $this->configManager->set('ctfile.password', 'pass');
        $this->configManager->set('ctfile.ssl', true);
        $this->configManager->set('ctfile.passive', false);
        $this->configManager->set('logging.enabled', true);
        $this->configManager->set('cache.enabled', false);

        $this->assertTrue($this->configManager->validate());
    }

    public function test_validate_allows_valid_integer_ranges(): void
    {
        $this->configManager->set('ctfile.host', 'valid.com');
        $this->configManager->set('ctfile.username', 'user');
        $this->configManager->set('ctfile.password', 'pass');
        $this->configManager->set('ctfile.port', 22);
        $this->configManager->set('ctfile.timeout', 60);
        $this->configManager->set('cache.ttl', 0); // Minimum allowed

        $this->assertTrue($this->configManager->validate());
    }

    public function test_validate_throws_exception_for_negative_timeout(): void
    {
        $this->configManager->set('ctfile.host', 'valid.com');
        $this->configManager->set('ctfile.username', 'user');
        $this->configManager->set('ctfile.password', 'pass');
        $this->configManager->set('ctfile.timeout', -1);

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'ctfile.timeout' must be at least 1");

        $this->configManager->validate();
    }

    public function test_validate_throws_exception_for_negative_cache_ttl(): void
    {
        $this->configManager->set('ctfile.host', 'valid.com');
        $this->configManager->set('ctfile.username', 'user');
        $this->configManager->set('ctfile.password', 'pass');
        $this->configManager->set('cache.ttl', -1);

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'cache.ttl' must be at least 0");

        $this->configManager->validate();
    }

    public function test_validate_allows_valid_cache_drivers(): void
    {
        $this->configManager->set('ctfile.host', 'valid.com');
        $this->configManager->set('ctfile.username', 'user');
        $this->configManager->set('ctfile.password', 'pass');

        foreach (['memory', 'file', 'redis'] as $driver) {
            $this->configManager->set('cache.driver', $driver);
            $this->assertTrue($this->configManager->validate());
        }
    }

    public function test_validate_throws_exception_for_invalid_cache_driver(): void
    {
        $this->configManager->set('ctfile.host', 'valid.com');
        $this->configManager->set('ctfile.username', 'user');
        $this->configManager->set('ctfile.password', 'pass');
        $this->configManager->set('cache.driver', 'invalid_driver');

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage("'cache.driver' must be one of: memory, file, redis, memcached");

        $this->configManager->validate();
    }

    public function test_get_default_config_returns_expected_structure(): void
    {
        $defaultConfig = ConfigurationManager::getDefaultConfig();

        $this->assertArrayHasKey('ctfile', $defaultConfig);
        $this->assertArrayHasKey('adapter', $defaultConfig);
        $this->assertArrayHasKey('logging', $defaultConfig);
        $this->assertArrayHasKey('cache', $defaultConfig);

        // Test ctfile defaults
        $this->assertEquals('', $defaultConfig['ctfile']['host']);
        $this->assertEquals(21, $defaultConfig['ctfile']['port']);
        $this->assertEquals('', $defaultConfig['ctfile']['username']);
        $this->assertEquals('', $defaultConfig['ctfile']['password']);
        $this->assertEquals(30, $defaultConfig['ctfile']['timeout']);
        $this->assertFalse($defaultConfig['ctfile']['ssl']);
        $this->assertTrue($defaultConfig['ctfile']['passive']);

        // Test adapter defaults
        $this->assertEquals('/', $defaultConfig['adapter']['root_path']);
        $this->assertEquals('/', $defaultConfig['adapter']['path_separator']);
        $this->assertTrue($defaultConfig['adapter']['case_sensitive']);
        $this->assertTrue($defaultConfig['adapter']['create_directories']);

        // Test logging defaults
        $this->assertFalse($defaultConfig['logging']['enabled']);
        $this->assertEquals('info', $defaultConfig['logging']['level']);
        $this->assertEquals('filesystem-ctfile', $defaultConfig['logging']['channel']);

        // Test cache defaults
        $this->assertFalse($defaultConfig['cache']['enabled']);
        $this->assertEquals(300, $defaultConfig['cache']['ttl']);
        $this->assertEquals('memory', $defaultConfig['cache']['driver']);
    }

    public function test_to_array_returns_complete_configuration(): void
    {
        $this->configManager->set('ctfile.host', 'test.com');
        $this->configManager->set('logging.enabled', true);

        $config = $this->configManager->toArray();

        $this->assertIsArray($config);
        $this->assertEquals('test.com', $config['ctfile']['host']);
        $this->assertTrue($config['logging']['enabled']);
        $this->assertArrayHasKey('adapter', $config);
        $this->assertArrayHasKey('cache', $config);
    }

    public function test_nested_configuration_access(): void
    {
        $this->configManager->set('deep.nested.value', 'test');

        $this->assertEquals('test', $this->configManager->get('deep.nested.value'));
        $this->assertNull($this->configManager->get('deep.nested.nonexistent'));
    }

    public function test_multiple_validation_errors_are_combined(): void
    {
        $this->configManager->set('ctfile.port', 'invalid');
        $this->configManager->set('ctfile.timeout', -5);

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessageMatches("/Configuration validation failed:.*'ctfile.host' is required.*'ctfile.port' must be of type integer.*'ctfile.username' is required.*'ctfile.password' is required.*'ctfile.timeout' must be at least 1/");

        $this->configManager->validate();
    }

    public function test_validate_key_validates_specific_key(): void
    {
        $this->configManager->set('ctfile.host', 'valid.com');

        $this->assertTrue($this->configManager->validateKey('ctfile.host'));
    }

    public function test_validate_key_throws_exception_for_invalid_key(): void
    {
        $this->configManager->set('ctfile.port', 'invalid');

        $this->expectException(CtFileConfigurationException::class);
        $this->expectExceptionMessage('Configuration validation failed for key "ctfile.port"');

        $this->configManager->validateKey('ctfile.port');
    }

    public function test_get_validator_returns_validator_instance(): void
    {
        $validator = $this->configManager->getValidator();

        $this->assertInstanceOf(ConfigurationValidator::class, $validator);
    }
}
