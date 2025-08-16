<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * Simple in-memory cache implementation for testing and basic usage.
 */
class MemoryCache implements CacheInterface
{
    private array $cache = [];

    private array $expiry = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);

        if (!$this->has($key)) {
            return $default;
        }

        return $this->cache[$key];
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->validateKey($key);

        $this->cache[$key] = $value;

        if ($ttl !== null) {
            if ($ttl instanceof \DateInterval) {
                $expiry = (new \DateTime())->add($ttl)->getTimestamp();
            } else {
                $expiry = time() + $ttl;
            }
            $this->expiry[$key] = $expiry;
        } else {
            unset($this->expiry[$key]);
        }

        return true;
    }

    public function delete(string $key): bool
    {
        $this->validateKey($key);

        unset($this->cache[$key], $this->expiry[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];
        $this->expiry = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                return false;
            }
        }

        return true;
    }

    public function has(string $key): bool
    {
        $this->validateKey($key);

        if (!array_key_exists($key, $this->cache)) {
            return false;
        }

        // Check if expired
        if (isset($this->expiry[$key]) && $this->expiry[$key] < time()) {
            unset($this->cache[$key], $this->expiry[$key]);

            return false;
        }

        return true;
    }

    /**
     * Validate cache key according to PSR-16.
     */
    private function validateKey(string $key): void
    {
        if ($key === '' || preg_match('/[{}()\/@]/', $key)) {
            throw new InvalidCacheKeyException("Invalid cache key: {$key}");
        }
    }
}
