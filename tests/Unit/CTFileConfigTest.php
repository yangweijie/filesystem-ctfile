<?php

use Yangweijie\FilesystemCtlife\CTFileConfig;

describe('CTFileConfig', function () {
    it('can create basic configuration', function () {
        $config = new CTFileConfig([
            'session' => 'test_session_token_123456',
            'app_id' => 'test_app_id',
        ]);

        expect($config->getSession())->toBe('test_session_token_123456');
        expect($config->getAppId())->toBe('test_app_id');
        expect($config->getApiBaseUrl())->toBe('https://rest.ctfile.com/v1');
        expect($config->getUploadBaseUrl())->toBe('https://upload.ctfile.com');
        expect($config->getStorageType())->toBe('public');
        expect($config->getCacheTtl())->toBe(3600);
        expect($config->getRetryAttempts())->toBe(3);
        expect($config->getTimeout())->toBe(30);
        expect($config->getConnectTimeout())->toBe(10);
    });

    it('can create custom configuration', function () {
        $config = new CTFileConfig([
            'session' => 'custom_session_token_123456',
            'app_id' => 'custom_app_id',
            'api_base_url' => 'https://custom.api.com/v2',
            'upload_base_url' => 'https://custom.upload.com',
            'storage_type' => 'private',
            'cache_ttl' => 7200,
            'retry_attempts' => 5,
            'timeout' => 60,
            'connect_timeout' => 20,
        ]);

        expect($config->getSession())->toBe('custom_session_token_123456');
        expect($config->getAppId())->toBe('custom_app_id');
        expect($config->getApiBaseUrl())->toBe('https://custom.api.com/v2');
        expect($config->getUploadBaseUrl())->toBe('https://custom.upload.com');
        expect($config->getStorageType())->toBe('private');
        expect($config->getCacheTtl())->toBe(7200);
        expect($config->getRetryAttempts())->toBe(5);
        expect($config->getTimeout())->toBe(60);
        expect($config->getConnectTimeout())->toBe(20);
    });

    it('throws exception when missing required session', function () {
        expect(fn() => new CTFileConfig([
            'app_id' => 'test_app_id',
        ]))->toThrow(InvalidArgumentException::class, 'Missing required config: session');
    });

    it('throws exception when missing required app_id', function () {
        expect(fn() => new CTFileConfig([
            'session' => 'test_session_token_123456',
        ]))->toThrow(InvalidArgumentException::class, 'Missing required config: app_id');
    });

    it('throws exception for invalid session token', function () {
        expect(fn() => new CTFileConfig([
            'session' => 'short',
            'app_id' => 'test_app_id',
        ]))->toThrow(InvalidArgumentException::class, 'Session token must be a valid string with at least 10 characters');
    });

    it('throws exception for invalid app_id', function () {
        expect(fn() => new CTFileConfig([
            'session' => 'test_session_token_123456',
            'app_id' => 'ab',
        ]))->toThrow(InvalidArgumentException::class, 'App ID must be a valid string with at least 3 characters');
    });

    it('throws exception for invalid storage type', function () {
        expect(fn() => new CTFileConfig([
            'session' => 'test_session_token_123456',
            'app_id' => 'test_app_id',
            'storage_type' => 'invalid',
        ]))->toThrow(InvalidArgumentException::class, 'Storage type must be either "public" or "private"');
    });

    it('throws exception for invalid numeric config', function () {
        expect(fn() => new CTFileConfig([
            'session' => 'test_session_token_123456',
            'app_id' => 'test_app_id',
            'cache_ttl' => -1,
        ]))->toThrow(InvalidArgumentException::class, 'Config cache_ttl must be a non-negative number');
    });

    it('throws exception for invalid URL config', function () {
        expect(fn() => new CTFileConfig([
            'session' => 'test_session_token_123456',
            'app_id' => 'test_app_id',
            'api_base_url' => 'invalid-url',
        ]))->toThrow(InvalidArgumentException::class, 'Config api_base_url must be a valid URL');
    });

    it('handles URL trailing slash correctly', function () {
        $config = new CTFileConfig([
            'session' => 'test_session_token_123456',
            'app_id' => 'test_app_id',
            'api_base_url' => 'https://api.example.com/v1/',
            'upload_base_url' => 'https://upload.example.com/',
        ]);

        expect($config->getApiBaseUrl())->toBe('https://api.example.com/v1');
        expect($config->getUploadBaseUrl())->toBe('https://upload.example.com');
    });

    it('can check storage type', function () {
        $publicConfig = new CTFileConfig([
            'session' => 'test_session_token_123456',
            'app_id' => 'test_app_id',
            'storage_type' => 'public',
        ]);

        expect($publicConfig->isPublicStorage())->toBeTrue();
        expect($publicConfig->isPrivateStorage())->toBeFalse();

        $privateConfig = new CTFileConfig([
            'session' => 'test_session_token_123456',
            'app_id' => 'test_app_id',
            'storage_type' => 'private',
        ]);

        expect($privateConfig->isPrivateStorage())->toBeTrue();
        expect($privateConfig->isPublicStorage())->toBeFalse();
    });

    it('can convert to array', function () {
        $configData = [
            'session' => 'test_session_token_123456',
            'app_id' => 'test_app_id',
            'storage_type' => 'private',
        ];

        $config = new CTFileConfig($configData);
        $result = $config->toArray();

        expect($result)->toHaveKey('session');
        expect($result)->toHaveKey('app_id');
        expect($result)->toHaveKey('storage_type');
        expect($result)->toHaveKey('api_base_url');
        expect($result)->toHaveKey('upload_base_url');
        expect($result['session'])->toBe('test_session_token_123456');
        expect($result['app_id'])->toBe('test_app_id');
        expect($result['storage_type'])->toBe('private');
    });

    it('can get config values with get method', function () {
        $config = new CTFileConfig([
            'session' => 'test_session_token_123456',
            'app_id' => 'test_app_id',
        ]);

        expect($config->get('session'))->toBe('test_session_token_123456');
        expect($config->get('app_id'))->toBe('test_app_id');
        expect($config->get('non_existent_key', 'default_value'))->toBe('default_value');
        expect($config->get('non_existent_key'))->toBeNull();
    });
});
