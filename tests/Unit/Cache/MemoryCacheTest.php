<?php

declare(strict_types=1);

use YangWeijie\FilesystemCtfile\Cache\InvalidCacheKeyException;
use YangWeijie\FilesystemCtfile\Cache\MemoryCache;

beforeEach(function () {
    $this->cache = new MemoryCache();
});

describe('MemoryCache', function () {
    it('implements CacheInterface', function () {
        expect($this->cache)->toBeInstanceOf(\Psr\SimpleCache\CacheInterface::class);
    });

    describe('get and set', function () {
        it('can store and retrieve string values', function () {
            $result = $this->cache->set('string-key', 'string-value');
            expect($result)->toBeTrue();

            $value = $this->cache->get('string-key');
            expect($value)->toBe('string-value');
        });

        it('can store and retrieve array values', function () {
            $data = ['key1' => 'value1', 'key2' => 'value2'];
            $this->cache->set('array-key', $data);

            $retrieved = $this->cache->get('array-key');
            expect($retrieved)->toBe($data);
        });

        it('can store and retrieve object values', function () {
            $object = new stdClass();
            $object->property = 'value';

            $this->cache->set('object-key', $object);
            $retrieved = $this->cache->get('object-key');

            expect($retrieved)->toBe($object);
            expect($retrieved->property)->toBe('value');
        });

        it('returns default value for non-existent keys', function () {
            $value = $this->cache->get('non-existent', 'default-value');
            expect($value)->toBe('default-value');
        });

        it('returns null as default when no default specified', function () {
            $value = $this->cache->get('non-existent');
            expect($value)->toBeNull();
        });
    });

    describe('TTL support', function () {
        it('supports integer TTL', function () {
            $this->cache->set('ttl-key', 'ttl-value', 1);
            expect($this->cache->get('ttl-key'))->toBe('ttl-value');
            expect($this->cache->has('ttl-key'))->toBeTrue();

            sleep(2);
            expect($this->cache->has('ttl-key'))->toBeFalse();
            expect($this->cache->get('ttl-key', 'expired'))->toBe('expired');
        });

        it('supports DateInterval TTL', function () {
            $interval = new DateInterval('PT1S'); // 1 second
            $this->cache->set('interval-key', 'interval-value', $interval);

            expect($this->cache->get('interval-key'))->toBe('interval-value');

            sleep(2);
            expect($this->cache->get('interval-key', 'expired'))->toBe('expired');
        });

        it('stores permanently when TTL is null', function () {
            $this->cache->set('permanent-key', 'permanent-value', null);
            expect($this->cache->get('permanent-key'))->toBe('permanent-value');

            // Should still be there after some time
            sleep(1);
            expect($this->cache->get('permanent-key'))->toBe('permanent-value');
        });
    });

    describe('has', function () {
        it('returns true for existing keys', function () {
            $this->cache->set('existing-key', 'value');
            expect($this->cache->has('existing-key'))->toBeTrue();
        });

        it('returns false for non-existent keys', function () {
            expect($this->cache->has('non-existent'))->toBeFalse();
        });

        it('returns false for expired keys', function () {
            $this->cache->set('expired-key', 'value', 1);
            expect($this->cache->has('expired-key'))->toBeTrue();

            sleep(2);
            expect($this->cache->has('expired-key'))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('can delete existing keys', function () {
            $this->cache->set('delete-key', 'delete-value');
            expect($this->cache->has('delete-key'))->toBeTrue();

            $result = $this->cache->delete('delete-key');
            expect($result)->toBeTrue();
            expect($this->cache->has('delete-key'))->toBeFalse();
        });

        it('returns true even for non-existent keys', function () {
            $result = $this->cache->delete('non-existent');
            expect($result)->toBeTrue();
        });

        it('removes expiry information', function () {
            $this->cache->set('expiry-key', 'value', 300);
            $this->cache->delete('expiry-key');

            // Re-add without TTL
            $this->cache->set('expiry-key', 'new-value');

            // Should not expire
            sleep(1);
            expect($this->cache->get('expiry-key'))->toBe('new-value');
        });
    });

    describe('clear', function () {
        it('removes all cached values', function () {
            $this->cache->set('key1', 'value1');
            $this->cache->set('key2', 'value2');
            $this->cache->set('key3', 'value3');

            $result = $this->cache->clear();
            expect($result)->toBeTrue();

            expect($this->cache->has('key1'))->toBeFalse();
            expect($this->cache->has('key2'))->toBeFalse();
            expect($this->cache->has('key3'))->toBeFalse();
        });

        it('removes all expiry information', function () {
            $this->cache->set('ttl-key1', 'value1', 300);
            $this->cache->set('ttl-key2', 'value2', 600);

            $this->cache->clear();

            expect($this->cache->has('ttl-key1'))->toBeFalse();
            expect($this->cache->has('ttl-key2'))->toBeFalse();
        });
    });

    describe('multiple operations', function () {
        it('can get multiple values', function () {
            $this->cache->set('multi1', 'value1');
            $this->cache->set('multi2', 'value2');

            $values = $this->cache->getMultiple(['multi1', 'multi2', 'non-existent']);

            expect($values)->toBe([
                'multi1' => 'value1',
                'multi2' => 'value2',
                'non-existent' => null,
            ]);
        });

        it('can get multiple values with default', function () {
            $this->cache->set('multi1', 'value1');

            $values = $this->cache->getMultiple(['multi1', 'non-existent'], 'default');

            expect($values)->toBe([
                'multi1' => 'value1',
                'non-existent' => 'default',
            ]);
        });

        it('can set multiple values', function () {
            $values = [
                'setmulti1' => 'value1',
                'setmulti2' => 'value2',
            ];

            $result = $this->cache->setMultiple($values);
            expect($result)->toBeTrue();

            expect($this->cache->get('setmulti1'))->toBe('value1');
            expect($this->cache->get('setmulti2'))->toBe('value2');
        });

        it('can set multiple values with TTL', function () {
            $values = [
                'ttlmulti1' => 'value1',
                'ttlmulti2' => 'value2',
            ];

            $this->cache->setMultiple($values, 1);

            expect($this->cache->get('ttlmulti1'))->toBe('value1');
            expect($this->cache->get('ttlmulti2'))->toBe('value2');

            sleep(2);
            expect($this->cache->get('ttlmulti1', 'expired'))->toBe('expired');
            expect($this->cache->get('ttlmulti2', 'expired'))->toBe('expired');
        });

        it('can delete multiple values', function () {
            $this->cache->set('delmulti1', 'value1');
            $this->cache->set('delmulti2', 'value2');
            $this->cache->set('delmulti3', 'value3');

            $result = $this->cache->deleteMultiple(['delmulti1', 'delmulti2']);
            expect($result)->toBeTrue();

            expect($this->cache->has('delmulti1'))->toBeFalse();
            expect($this->cache->has('delmulti2'))->toBeFalse();
            expect($this->cache->has('delmulti3'))->toBeTrue();
        });
    });

    describe('key validation', function () {
        it('throws exception for empty key', function () {
            expect(fn () => $this->cache->get(''))
                ->toThrow(InvalidCacheKeyException::class);
        });

        it('throws exception for keys with invalid characters', function () {
            $invalidKeys = ['{key}', '(key)', 'key@domain', 'key/path'];

            foreach ($invalidKeys as $key) {
                expect(fn () => $this->cache->get($key))
                    ->toThrow(InvalidCacheKeyException::class);
            }
        });

        it('accepts valid keys', function () {
            $validKeys = ['key', 'key-name', 'key_name', 'key123', 'KEY'];

            foreach ($validKeys as $key) {
                $this->cache->set($key, 'value');
                expect($this->cache->get($key))->toBe('value');
            }
        });
    });
});
