<?php

declare(strict_types=1);

namespace Yangweijie\FilesystemCtlife;

use Yangweijie\FilesystemCtlife\Exceptions\CTFileException;

/**
 * 路径与文件ID映射器
 * 
 * 负责 Flysystem 路径与 CTFile 文件ID之间的双向映射。
 * 维护路径缓存，处理特殊ID格式（f+ID, d+ID），支持根目录和嵌套目录的路径解析。
 */
class PathMapper
{
    /**
     * 路径到ID的缓存 [path => id]
     */
    private array $pathCache = [];

    /**
     * ID到路径的缓存 [id => path]
     */
    private array $idCache = [];

    /**
     * 目录结构缓存 [parent_id => [child_name => child_id]]
     */
    private array $directoryCache = [];

    /**
     * CTFile API 客户端
     */
    private CTFileClient $client;

    /**
     * 配置实例
     */
    private CTFileConfig $config;

    /**
     * 缓存过期时间戳 [cache_key => timestamp]
     */
    private array $cacheExpiry = [];

    /**
     * 构造函数
     *
     * @param CTFileClient $client API 客户端
     * @param CTFileConfig|null $config 配置实例
     */
    public function __construct(CTFileClient $client, ?CTFileConfig $config = null)
    {
        $this->client = $client;
        $this->config = $config ?? new CTFileConfig([
            'session' => 'dummy',
            'app_id' => 'dummy'
        ]);

        // 初始化根目录映射
        $this->cachePath('/', 'd0');
        $this->cachePath('', 'd0');
    }

    /**
     * 获取文件ID
     *
     * @param string $path 文件路径
     * @return string 文件ID
     * @throws CTFileException 当文件不存在时
     */
    public function getFileId(string $path): string
    {
        $path = $this->normalizePath($path);
        
        // 检查缓存
        if ($this->isCacheValid($path)) {
            return $this->pathCache[$path];
        }

        // 解析路径获取文件ID
        $id = $this->resolvePath($path, 'file');
        
        if ($id === null) {
            throw new CTFileException("File not found: {$path}");
        }

        $this->cachePath($path, $id);
        return $id;
    }

    /**
     * 获取目录ID
     *
     * @param string $path 目录路径
     * @return string 目录ID
     * @throws CTFileException 当目录不存在时
     */
    public function getDirectoryId(string $path): string
    {
        $path = $this->normalizePath($path);
        
        // 根目录特殊处理
        if ($path === '/' || $path === '') {
            return 'd0';
        }

        // 检查缓存
        if ($this->isCacheValid($path)) {
            return $this->pathCache[$path];
        }

        // 解析路径获取目录ID
        $id = $this->resolvePath($path, 'directory');
        
        if ($id === null) {
            throw new CTFileException("Directory not found: {$path}");
        }

        $this->cachePath($path, $id);
        return $id;
    }

    /**
     * 根据ID获取路径
     *
     * @param string $id 文件或目录ID
     * @return string 路径
     * @throws CTFileException 当ID无效时
     */
    public function getPath(string $id): string
    {
        // 根目录特殊处理
        if ($id === 'd0') {
            return '/';
        }

        // 检查缓存
        if (isset($this->idCache[$id])) {
            return $this->idCache[$id];
        }

        // 通过API获取文件/目录信息来构建路径
        $path = $this->buildPathFromId($id);
        
        if ($path === null) {
            throw new CTFileException("Invalid ID: {$id}");
        }

        $this->cachePath($path, $id);
        return $path;
    }

    /**
     * 缓存路径映射
     *
     * @param string $path 路径
     * @param string $id 文件或目录ID
     */
    public function cachePath(string $path, string $id): void
    {
        $path = $this->normalizePath($path);
        $this->pathCache[$path] = $id;
        $this->idCache[$id] = $path;
        $this->cacheExpiry[$path] = time() + $this->config->getCacheTtl();
    }

    /**
     * 使缓存失效
     *
     * @param string|null $path 要失效的路径，null表示清空所有缓存
     */
    public function invalidateCache(?string $path = null): void
    {
        if ($path === null) {
            $this->pathCache = [];
            $this->idCache = [];
            $this->directoryCache = [];
            $this->cacheExpiry = [];
            
            // 重新初始化根目录映射
            $this->cachePath('/', 'd0');
            $this->cachePath('', 'd0');
        } else {
            $path = $this->normalizePath($path);
            
            if (isset($this->pathCache[$path])) {
                $id = $this->pathCache[$path];
                unset($this->pathCache[$path]);
                unset($this->idCache[$id]);
                unset($this->cacheExpiry[$path]);
            }

            // 清理相关的目录缓存
            $this->invalidateDirectoryCache($path);
        }
    }

    /**
     * 从文件列表构建路径映射
     *
     * @param array $listing 文件列表数据
     * @param string $basePath 基础路径
     */
    public function buildPathFromListing(array $listing, string $basePath = '/'): void
    {
        $basePath = $this->normalizePath($basePath);
        
        if (!isset($listing['data']) || !is_array($listing['data'])) {
            return;
        }

        foreach ($listing['data'] as $item) {
            if (!isset($item['id']) || !isset($item['name'])) {
                continue;
            }

            $itemPath = $basePath === '/' ? '/' . $item['name'] : $basePath . '/' . $item['name'];
            $this->cachePath($itemPath, $item['id']);
            
            // 缓存到目录结构中
            $parentId = $this->pathCache[$basePath] ?? 'd0';
            if (!isset($this->directoryCache[$parentId])) {
                $this->directoryCache[$parentId] = [];
            }
            $this->directoryCache[$parentId][$item['name']] = $item['id'];
        }
    }

    /**
     * 解析路径获取ID
     *
     * @param string $path 路径
     * @param string $type 类型：'file' 或 'directory'
     * @return string|null 文件或目录ID，不存在时返回null
     */
    private function resolvePath(string $path, string $type = 'file'): ?string
    {
        $path = $this->normalizePath($path);
        
        // 根目录
        if ($path === '/' || $path === '') {
            return 'd0';
        }

        // 分解路径
        $parts = explode('/', trim($path, '/'));
        $currentId = 'd0';
        $currentPath = '';

        foreach ($parts as $part) {
            if (empty($part)) {
                continue;
            }

            $currentPath = $currentPath === '' ? $part : $currentPath . '/' . $part;
            $fullPath = '/' . $currentPath;

            // 检查缓存
            if ($this->isCacheValid($fullPath)) {
                $currentId = $this->pathCache[$fullPath];
                continue;
            }

            // 从目录缓存中查找
            if (isset($this->directoryCache[$currentId][$part])) {
                $currentId = $this->directoryCache[$currentId][$part];
                $this->cachePath($fullPath, $currentId);
                continue;
            }

            // 通过API查找
            $foundId = $this->findInDirectory($currentId, $part);
            if ($foundId === null) {
                return null;
            }

            $currentId = $foundId;
            $this->cachePath($fullPath, $currentId);
        }

        // 验证最终类型
        if ($type === 'file' && !$this->isFileId($currentId)) {
            return null;
        }
        if ($type === 'directory' && !$this->isDirectoryId($currentId)) {
            return null;
        }

        return $currentId;
    }

    /**
     * 在指定目录中查找文件或文件夹
     *
     * @param string $parentId 父目录ID
     * @param string $name 要查找的名称
     * @return string|null 找到的ID，不存在时返回null
     */
    private function findInDirectory(string $parentId, string $name): ?string
    {
        try {
            $listing = $this->client->getFileList($parentId, 1, 100);
            
            if (!isset($listing['data']) || !is_array($listing['data'])) {
                return null;
            }

            // 更新目录缓存
            if (!isset($this->directoryCache[$parentId])) {
                $this->directoryCache[$parentId] = [];
            }

            foreach ($listing['data'] as $item) {
                if (!isset($item['id']) || !isset($item['name'])) {
                    continue;
                }

                $this->directoryCache[$parentId][$item['name']] = $item['id'];
                
                if ($item['name'] === $name) {
                    return $item['id'];
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 根据ID构建路径
     *
     * @param string $id 文件或目录ID
     * @return string|null 路径，失败时返回null
     */
    private function buildPathFromId(string $id): ?string
    {
        try {
            // 尝试获取文件信息
            $info = $this->client->getFileInfo($id);
            
            if (!isset($info['data'])) {
                return null;
            }

            $data = $info['data'];
            $name = $data['name'] ?? null;
            $parentId = $data['parent_id'] ?? 'd0';

            if ($name === null) {
                return null;
            }

            // 递归构建父路径
            if ($parentId === 'd0') {
                return '/' . $name;
            }

            $parentPath = $this->getPath($parentId);
            return $parentPath === '/' ? '/' . $name : $parentPath . '/' . $name;
            
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 标准化路径
     *
     * @param string $path 原始路径
     * @return string 标准化后的路径
     */
    private function normalizePath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        // 移除多余的斜杠
        $path = preg_replace('#/+#', '/', $path);
        
        // 确保以斜杠开头
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        // 移除末尾斜杠（除非是根目录）
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    /**
     * 检查缓存是否有效
     *
     * @param string $path 路径
     * @return bool 缓存是否有效
     */
    private function isCacheValid(string $path): bool
    {
        if (!isset($this->pathCache[$path])) {
            return false;
        }

        if (!isset($this->cacheExpiry[$path])) {
            return true; // 永久缓存（如根目录）
        }

        return time() < $this->cacheExpiry[$path];
    }

    /**
     * 使目录缓存失效
     *
     * @param string $path 路径
     */
    private function invalidateDirectoryCache(string $path): void
    {
        // 清理所有以该路径开头的缓存项
        foreach (array_keys($this->pathCache) as $cachedPath) {
            if (str_starts_with($cachedPath, $path . '/') || $cachedPath === $path) {
                $id = $this->pathCache[$cachedPath];
                unset($this->pathCache[$cachedPath]);
                unset($this->idCache[$id]);
                unset($this->cacheExpiry[$cachedPath]);
            }
        }
    }

    /**
     * 解析文件ID信息
     *
     * @param string $id 文件或目录ID
     * @return array 解析结果 ['type' => 'file|directory', 'numeric_id' => int]
     */
    private function parseFileId(string $id): array
    {
        if (str_starts_with($id, 'f')) {
            return [
                'type' => 'file',
                'numeric_id' => (int) substr($id, 1)
            ];
        }

        if (str_starts_with($id, 'd')) {
            return [
                'type' => 'directory',
                'numeric_id' => (int) substr($id, 1)
            ];
        }

        return [
            'type' => 'unknown',
            'numeric_id' => 0
        ];
    }

    /**
     * 检查是否为文件ID
     *
     * @param string $id ID
     * @return bool 是否为文件ID
     */
    private function isFileId(string $id): bool
    {
        return str_starts_with($id, 'f');
    }

    /**
     * 检查是否为目录ID
     *
     * @param string $id ID
     * @return bool 是否为目录ID
     */
    private function isDirectoryId(string $id): bool
    {
        return str_starts_with($id, 'd');
    }
}
