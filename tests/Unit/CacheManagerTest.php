<?php

declare(strict_types=1);

use Psr\SimpleCache\CacheInterface;
use YangWeijie\FilesystemCtfile\Cache\MemoryCache;
use YangWeijie\FilesystemCtfile\CacheManager;
use YangWeiJie\FilesystemCtfile\CacheManager as CtFileCacheManager;

beforeEach(function () {
    $this->cache = new MemoryCache();
    $this->config = [
        'enabled' => true,
        'ttl' => 300,
        'key_prefix' => 'test:',
    ];
    $this->cacheManager = new CtFileCacheManager($this->cache, $this->config);
});

describe('CacheManager', function () {
    it('can be instantiated with cache and config', function () {
        expect($this->cacheManager)->toBeInstanceOf(CtFileCacheManager::class);
    });

    it('can be instantiated without cache', function () {
        $manager = new CtFileCacheManager(null, ['enabled' => false]);
        expect($manager)->toBeInstanceOf(CtFileCacheManager::class);
        expect($manager->isEnabled())->toBeFalse();
    });

    describe('isEnabled', function () {
        it('returns true when cache is available and enabled', function () {
            expect($this->cacheManager->isEnabled())->toBeTrue();
        });

        it('returns false when cache is null', function () {
            $manager = new CtFileCacheManager(null, ['enabled' => true]);
            expect($manager->isEnabled())->toBeFalse();
        });

        it('returns false when disabled in config', function () {
            $manager = new CtFileCacheManager($this->cache, ['enabled' => false]);
            expect($manager->isEnabled())->toBeFalse();
        });
    });

    describe('get and set', function () {
        it('can store and retrieve values', function () {
            $result = $this->cacheManager->set('test-key', 'test-value');
            expect($result)->toBeTrue();

            $value = $this->cacheManager->get('test-key');
            expect($value)->toBe('test-value');
        });

        it('returns default value when key does not exist', function () {
            $value = $this->cacheManager->get('non-existent', 'default');
            expect($value)->toBe('default');
        });

        it('can store complex data structures', function () {
            $data = [
                'metadata' => ['size' => 1024, 'type' => 'file'],
                'timestamp' => time(),
            ];

            $this->cacheManager->set('complex-key', $data);
            $retrieved = $this->cacheManager->get('complex-key');

            expect($retrieved)->toBe($data);
        });

        it('respects TTL settings', function () {
            $this->cacheManager->set('ttl-key', 'ttl-value', 1);
            expect($this->cacheManager->get('ttl-key'))->toBe('ttl-value');

            sleep(2);
            expect($this->cacheManager->get('ttl-key', 'default'))->toBe('default');
        });

        it('returns false when caching is disabled', function () {
            $manager = new CtFileCacheManager(null, ['enabled' => false]);
            $result = $manager->set('key', 'value');
            expect($result)->toBeFalse();

            $value = $manager->get('key', 'default');
            expect($value)->toBe('default');
        });
    });

    describe('delete', function () {
        it('can delete cached values', function () {
            $this->cacheManager->set('delete-key', 'delete-value');
            expect($this->cacheManager->get('delete-key'))->toBe('delete-value');

            $result = $this->cacheManager->delete('delete-key');
            expect($result)->toBeTrue();
            expect($this->cacheManager->get('delete-key', 'default'))->toBe('default');
        });

        it('returns false when caching is disabled', function () {
            $manager = new CtFileCacheManager(null, ['enabled' => false]);
            $result = $manager->delete('key');
            expect($result)->toBeFalse();
        });
    });

    describe('clear', function () {
        it('can clear all cached values', function () {
            $this->cacheManager->set('key1', 'value1');
            $this->cacheManager->set('key2', 'value2');

            $result = $this->cacheManager->clear();
            expect($result)->toBeTrue();

            expect($this->cacheManager->get('key1', 'default'))->toBe('default');
            expect($this->cacheManager->get('key2', 'default'))->toBe('default');
        });
    });

    describe('invalidatePath', function () {
        it('invalidates metadata cache for specific path', function () {
            $path = '/test/file.txt';
            $metadataKey = $this->cacheManager->getMetadataKey($path);

            // Set the cache using the CacheManager's set method which handles prefixing
            $result = $this->cacheManager->set($metadataKey, ['size' => 1024]);
            expect($result)->toBeTrue();

            $cached = $this->cacheManager->get($metadataKey);
            expect($cached)->not->toBeNull();
            expect($cached)->toBe(['size' => 1024]);

            $this->cacheManager->invalidatePath($path);
            expect($this->cacheManager->get($metadataKey, 'default'))->toBe('default');
        });

        it('invalidates directory listing cache for parent directories', function () {
            $path = '/test/subdir/file.txt';

            // Cache listings for parent directories
            $rootKey = $this->cacheManager->getListingKey('/');
            $testKey = $this->cacheManager->getListingKey('/test');
            $subdirKey = $this->cacheManager->getListingKey('/test/subdir');

            $this->cacheManager->set($rootKey, ['root-listing']);
            $this->cacheManager->set($testKey, ['test-listing']);
            $this->cacheManager->set($subdirKey, ['subdir-listing']);

            $this->cacheManager->invalidatePath($path);

            // All parent directory listings should be invalidated
            expect($this->cacheManager->get($rootKey, 'default'))->toBe('default');
            expect($this->cacheManager->get($testKey, 'default'))->toBe('default');
            expect($this->cacheManager->get($subdirKey, 'default'))->toBe('default');
        });

        it('does nothing when caching is disabled', function () {
            $manager = new CtFileCacheManager(null, ['enabled' => false]);
            // Should not throw any exceptions
            $manager->invalidatePath('/test/path');
            expect(true)->toBeTrue(); // Test passes if no exception is thrown
        });
    });

    describe('cache key generation', function () {
        it('generates correct metadata keys', function () {
            $key = $this->cacheManager->getMetadataKey('/test/file.txt');
            expect($key)->toBe('metadata_test_file.txt');
        });

        it('generates correct listing keys', function () {
            $key = $this->cacheManager->getListingKey('/test/dir');
            expect($key)->toBe('listing_test_dir');

            $recursiveKey = $this->cacheManager->getListingKey('/test/dir', true);
            expect($recursiveKey)->toBe('listing_test_dir_recursive');
        });
    });

    describe('error handling', function () {
        it('handles cache exceptions gracefully', function () {
            $mockCache = Mockery::mock(CacheInterface::class);
            $mockCache->shouldReceive('get')
                ->andThrow(new class ('Invalid key') extends \InvalidArgumentException implements \Psr\SimpleCache\InvalidArgumentException {});

            $manager = new CtFileCacheManager($mockCache, ['enabled' => true]);

            $result = $manager->get('invalid-key', 'default');
            expect($result)->toBe('default');
        });

        it('handles set exceptions gracefully', function () {
            $mockCache = Mockery::mock(CacheInterface::class);
            $mockCache->shouldReceive('set')
                ->andThrow(new class ('Invalid key') extends \InvalidArgumentException implements \Psr\SimpleCache\InvalidArgumentException {});

            $manager = new CtFileCacheManager($mockCache, ['enabled' => true]);

            $result = $manager->set('invalid-key', 'value');
            expect($result)->toBeFalse();
        });

        it('handles delete exceptions gracefully', function () {
            $mockCache = Mockery::mock(CacheInterface::class);
            $mockCache->shouldReceive('delete')
                ->andThrow(new class ('Invalid key') extends \InvalidArgumentException implements \Psr\SimpleCache\InvalidArgumentException {});

            $manager = new CtFileCacheManager($mockCache, ['enabled' => true]);

            $result = $manager->delete('invalid-key');
            expect($result)->toBeFalse();
        });
    });
});
