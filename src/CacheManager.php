<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Manages caching for metadata and directory listings with TTL support.
 */
class CacheManager
{
    private ?CacheInterface $cache;

    private int $defaultTtl;

    private bool $enabled;

    private string $keyPrefix;

    public function __construct(?CacheInterface $cache = null, array $config = [])
    {
        $this->cache = $cache;
        $this->enabled = $config['enabled'] ?? false;
        $this->defaultTtl = $config['ttl'] ?? 300; // 5 minutes default
        $this->keyPrefix = $config['key_prefix'] ?? 'ctfile:';
    }

    /**
     * Check if caching is enabled and available.
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->cache !== null;
    }

    /**
     * Get cached value by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->isEnabled()) {
            return $default;
        }

        try {
            $cacheKey = $this->buildKey($key);

            return $this->cache->get($cacheKey, $default);
        } catch (InvalidArgumentException $e) {
            return $default;
        }
    }

    /**
     * Set cached value with TTL.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $cacheKey = $this->buildKey($key);
            $ttl ??= $this->defaultTtl;

            return $this->cache->set($cacheKey, $value, $ttl);
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Delete cached value by key.
     */
    public function delete(string $key): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $cacheKey = $this->buildKey($key);

            return $this->cache->delete($cacheKey);
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Clear all cached values with the configured prefix.
     */
    public function clear(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        return $this->cache->clear();
    }

    /**
     * Invalidate cache for a specific path and its parent directories.
     */
    public function invalidatePath(string $path): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        // Invalidate metadata cache for the path
        $metadataKey = $this->getMetadataKey($path);
        $this->delete($metadataKey);

        // Invalidate directory listing cache for parent directories
        $pathParts = explode('/', trim($path, '/'));
        $currentPath = '';

        foreach ($pathParts as $part) {
            if ($part !== '') {
                $currentPath .= '/' . $part;
                $listingKey = $this->getListingKey($currentPath);
                $this->delete($listingKey);

                // Also invalidate recursive listing
                $recursiveKey = $this->getListingKey($currentPath, true);
                $this->delete($recursiveKey);
            }
        }

        // Also invalidate root listing if path is not root
        if ($path !== '/' && $path !== '') {
            $rootKey = $this->getListingKey('/');
            $this->delete($rootKey);
            $rootRecursiveKey = $this->getListingKey('/', true);
            $this->delete($rootRecursiveKey);
        }
    }

    /**
     * Get cache key for file metadata.
     */
    public function getMetadataKey(string $path): string
    {
        return 'metadata_' . str_replace('/', '_', ltrim($path, '/'));
    }

    /**
     * Get cache key for directory listing.
     */
    public function getListingKey(string $path, bool $recursive = false): string
    {
        $suffix = $recursive ? '_recursive' : '';
        $normalizedPath = str_replace('/', '_', ltrim($path, '/'));

        return "listing_{$normalizedPath}{$suffix}";
    }

    /**
     * Build full cache key with prefix.
     */
    private function buildKey(string $key): string
    {
        return $this->keyPrefix . $key;
    }
}
