<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Utilities;

/**
 * Utility class for path normalization, validation, and manipulation.
 *
 * Provides static methods to handle file paths safely and consistently
 * across different operating systems and storage backends.
 */
class PathNormalizer
{
    /**
     * Path separator used internally.
     */
    private const SEPARATOR = '/';

    /**
     * Normalize a file path by removing redundant separators, resolving relative references,
     * and ensuring consistent separator usage.
     *
     * @param string $path The path to normalize
     * @return string The normalized path
     */
    public static function normalize(string $path): string
    {
        if (empty($path)) {
            return '';
        }

        // Convert backslashes to forward slashes for consistency
        $path = str_replace('\\', self::SEPARATOR, $path);

        // Remove duplicate separators
        $path = preg_replace('#' . preg_quote(self::SEPARATOR) . '+#', self::SEPARATOR, $path);

        // Split path into parts
        $parts = explode(self::SEPARATOR, $path);
        $normalizedParts = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                // Skip empty parts and current directory references
                continue;
            }

            if ($part === '..') {
                // Handle parent directory references
                if (!empty($normalizedParts) && end($normalizedParts) !== '..') {
                    array_pop($normalizedParts);
                } elseif (!self::isAbsolute($path)) {
                    // Only allow .. in relative paths when we can't go up further
                    $normalizedParts[] = $part;
                }
                // For absolute paths, ignore .. that would go above root
            } else {
                $normalizedParts[] = $part;
            }
        }

        $normalizedPath = implode(self::SEPARATOR, $normalizedParts);

        // Preserve leading separator for absolute paths
        if (self::isAbsolute($path) && !empty($normalizedPath)) {
            $normalizedPath = self::SEPARATOR . $normalizedPath;
        }

        return $normalizedPath;
    }

    /**
     * Validate a path to ensure it's safe and doesn't contain malicious patterns.
     *
     * @param string $path The path to validate
     * @return bool True if the path is valid, false otherwise
     */
    public static function validate(string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        // Check for null bytes (security risk)
        if (strpos($path, "\0") !== false) {
            return false;
        }

        // Check for control characters
        if (preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return false;
        }

        // Normalize the path first
        $normalizedPath = self::normalize($path);

        // Check for path traversal attempts that couldn't be resolved
        if (strpos($normalizedPath, '..') !== false) {
            return false;
        }

        // Check for reserved names on Windows
        $basename = basename($normalizedPath);
        $reservedNames = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'];

        if (in_array(strtoupper($basename), $reservedNames, true)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a path is absolute.
     *
     * @param string $path The path to check
     * @return bool True if the path is absolute, false otherwise
     */
    public static function isAbsolute(string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        // Unix-style absolute path
        if ($path[0] === '/') {
            return true;
        }

        // Windows-style absolute path (C:\ or C:/)
        if (strlen($path) >= 3 && ctype_alpha($path[0]) && $path[1] === ':' && ($path[2] === '\\' || $path[2] === '/')) {
            return true;
        }

        return false;
    }

    /**
     * Join multiple path parts into a single normalized path.
     *
     * @param string ...$parts The path parts to join
     * @return string The joined and normalized path
     */
    public static function join(string ...$parts): string
    {
        if (empty($parts)) {
            return '';
        }

        $path = implode(self::SEPARATOR, $parts);

        return self::normalize($path);
    }

    /**
     * Get the directory name from a path.
     *
     * @param string $path The path
     * @return string The directory name
     */
    public static function dirname(string $path): string
    {
        $normalizedPath = self::normalize($path);

        if (empty($normalizedPath) || $normalizedPath === self::SEPARATOR) {
            return '';
        }

        $lastSeparatorPos = strrpos($normalizedPath, self::SEPARATOR);

        if ($lastSeparatorPos === false) {
            return '';
        }

        if ($lastSeparatorPos === 0) {
            return self::SEPARATOR;
        }

        return substr($normalizedPath, 0, $lastSeparatorPos);
    }

    /**
     * Get the base name from a path.
     *
     * @param string $path The path
     * @return string The base name
     */
    public static function basename(string $path): string
    {
        $normalizedPath = self::normalize($path);

        if (empty($normalizedPath) || $normalizedPath === self::SEPARATOR) {
            return '';
        }

        $lastSeparatorPos = strrpos($normalizedPath, self::SEPARATOR);

        if ($lastSeparatorPos === false) {
            return $normalizedPath;
        }

        return substr($normalizedPath, $lastSeparatorPos + 1);
    }
}
