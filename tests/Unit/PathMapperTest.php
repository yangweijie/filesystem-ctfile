<?php

use Yangweijie\FilesystemCtlife\PathMapper;
use Yangweijie\FilesystemCtlife\CTFileClient;
use Yangweijie\FilesystemCtlife\CTFileConfig;
use Yangweijie\FilesystemCtlife\Exceptions\CTFileException;

describe('PathMapper', function () {
    beforeEach(function () {
        $this->config = new CTFileConfig([
            'session' => 'test_session_token_123456',
            'app_id' => 'test_app_id',
            'cache_ttl' => 3600,
        ]);
        
        $this->client = new CTFileClient($this->config);
        $this->mapper = new PathMapper($this->client, $this->config);
    });

    it('can be instantiated', function () {
        expect($this->mapper)->toBeInstanceOf(PathMapper::class);
    });

    it('handles root directory correctly', function () {
        expect($this->mapper->getDirectoryId('/'))->toBe('d0');
        expect($this->mapper->getDirectoryId(''))->toBe('d0');
        expect($this->mapper->getPath('d0'))->toBe('/');
    });

    it('normalizes paths correctly', function () {
        // 使用反射测试私有方法
        $reflection = new ReflectionClass($this->mapper);
        $method = $reflection->getMethod('normalizePath');
        $method->setAccessible(true);
        
        expect($method->invoke($this->mapper, '/'))->toBe('/');
        expect($method->invoke($this->mapper, ''))->toBe('/');
        expect($method->invoke($this->mapper, '/path/to/file'))->toBe('/path/to/file');
        expect($method->invoke($this->mapper, 'path/to/file'))->toBe('/path/to/file');
        expect($method->invoke($this->mapper, '/path/to/file/'))->toBe('/path/to/file');
        expect($method->invoke($this->mapper, '//path//to//file//'))->toBe('/path/to/file');
    });

    it('can cache and retrieve path mappings', function () {
        $this->mapper->cachePath('/test/file.txt', 'f123');
        
        // 使用反射访问私有属性
        $reflection = new ReflectionClass($this->mapper);
        $pathCache = $reflection->getProperty('pathCache');
        $pathCache->setAccessible(true);
        $idCache = $reflection->getProperty('idCache');
        $idCache->setAccessible(true);
        
        $pathCacheValue = $pathCache->getValue($this->mapper);
        $idCacheValue = $idCache->getValue($this->mapper);
        
        expect($pathCacheValue['/test/file.txt'])->toBe('f123');
        expect($idCacheValue['f123'])->toBe('/test/file.txt');
    });

    it('can invalidate cache', function () {
        $this->mapper->cachePath('/test/file.txt', 'f123');
        $this->mapper->cachePath('/test/folder', 'd456');
        
        // 使用反射访问私有属性
        $reflection = new ReflectionClass($this->mapper);
        $pathCache = $reflection->getProperty('pathCache');
        $pathCache->setAccessible(true);
        
        // 失效特定路径
        $this->mapper->invalidateCache('/test/file.txt');
        $pathCacheValue = $pathCache->getValue($this->mapper);
        
        expect($pathCacheValue)->not->toHaveKey('/test/file.txt');
        expect($pathCacheValue)->toHaveKey('/test/folder');
        
        // 失效所有缓存
        $this->mapper->invalidateCache();
        $pathCacheValue = $pathCache->getValue($this->mapper);
        
        expect($pathCacheValue)->not->toHaveKey('/test/folder');
        expect($pathCacheValue)->toHaveKey('/'); // 根目录应该保留
    });

    it('can parse file IDs correctly', function () {
        $reflection = new ReflectionClass($this->mapper);
        $method = $reflection->getMethod('parseFileId');
        $method->setAccessible(true);
        
        $fileResult = $method->invoke($this->mapper, 'f123');
        expect($fileResult)->toBe([
            'type' => 'file',
            'numeric_id' => 123
        ]);
        
        $dirResult = $method->invoke($this->mapper, 'd456');
        expect($dirResult)->toBe([
            'type' => 'directory',
            'numeric_id' => 456
        ]);
        
        $unknownResult = $method->invoke($this->mapper, 'x789');
        expect($unknownResult)->toBe([
            'type' => 'unknown',
            'numeric_id' => 0
        ]);
    });

    it('can identify file and directory IDs', function () {
        $reflection = new ReflectionClass($this->mapper);
        
        $isFileId = $reflection->getMethod('isFileId');
        $isFileId->setAccessible(true);
        
        $isDirectoryId = $reflection->getMethod('isDirectoryId');
        $isDirectoryId->setAccessible(true);
        
        expect($isFileId->invoke($this->mapper, 'f123'))->toBeTrue();
        expect($isFileId->invoke($this->mapper, 'd123'))->toBeFalse();
        
        expect($isDirectoryId->invoke($this->mapper, 'd456'))->toBeTrue();
        expect($isDirectoryId->invoke($this->mapper, 'f456'))->toBeFalse();
    });

    it('can check cache validity', function () {
        $reflection = new ReflectionClass($this->mapper);
        $method = $reflection->getMethod('isCacheValid');
        $method->setAccessible(true);
        
        // 不存在的路径
        expect($method->invoke($this->mapper, '/nonexistent'))->toBeFalse();
        
        // 缓存路径
        $this->mapper->cachePath('/test/file.txt', 'f123');
        expect($method->invoke($this->mapper, '/test/file.txt'))->toBeTrue();
        
        // 根目录（永久缓存）
        expect($method->invoke($this->mapper, '/'))->toBeTrue();
    });

    it('can build path from listing', function () {
        $listing = [
            'data' => [
                ['id' => 'f123', 'name' => 'file1.txt'],
                ['id' => 'd456', 'name' => 'folder1'],
                ['id' => 'f789', 'name' => 'file2.txt'],
            ]
        ];
        
        $this->mapper->buildPathFromListing($listing, '/test');
        
        // 使用反射访问私有属性
        $reflection = new ReflectionClass($this->mapper);
        $pathCache = $reflection->getProperty('pathCache');
        $pathCache->setAccessible(true);
        $directoryCache = $reflection->getProperty('directoryCache');
        $directoryCache->setAccessible(true);
        
        $pathCacheValue = $pathCache->getValue($this->mapper);
        $directoryCacheValue = $directoryCache->getValue($this->mapper);
        
        expect($pathCacheValue['/test/file1.txt'])->toBe('f123');
        expect($pathCacheValue['/test/folder1'])->toBe('d456');
        expect($pathCacheValue['/test/file2.txt'])->toBe('f789');
        
        // 检查目录缓存
        $testDirId = $pathCacheValue['/test'] ?? 'd0';
        expect($directoryCacheValue[$testDirId]['file1.txt'])->toBe('f123');
        expect($directoryCacheValue[$testDirId]['folder1'])->toBe('d456');
    });

    it('handles empty listing gracefully', function () {
        $emptyListing = [];
        // 应该不抛出异常
        $this->mapper->buildPathFromListing($emptyListing, '/test');
        expect(true)->toBeTrue(); // 如果到达这里说明没有抛出异常

        $invalidListing = ['data' => null];
        // 应该不抛出异常
        $this->mapper->buildPathFromListing($invalidListing, '/test');
        expect(true)->toBeTrue(); // 如果到达这里说明没有抛出异常
    });

    it('handles malformed listing items', function () {
        $malformedListing = [
            'data' => [
                ['id' => 'f123'], // 缺少 name
                ['name' => 'file.txt'], // 缺少 id
                ['id' => 'f456', 'name' => 'valid.txt'], // 正常项
            ]
        ];
        
        $this->mapper->buildPathFromListing($malformedListing, '/test');
        
        // 使用反射访问私有属性
        $reflection = new ReflectionClass($this->mapper);
        $pathCache = $reflection->getProperty('pathCache');
        $pathCache->setAccessible(true);
        
        $pathCacheValue = $pathCache->getValue($this->mapper);
        
        // 只有有效项应该被缓存
        expect($pathCacheValue)->toHaveKey('/test/valid.txt');
        expect($pathCacheValue)->not->toHaveKey('/test/file.txt');
    });

    it('handles root directory listing correctly', function () {
        $rootListing = [
            'data' => [
                ['id' => 'f123', 'name' => 'root-file.txt'],
                ['id' => 'd456', 'name' => 'root-folder'],
            ]
        ];
        
        $this->mapper->buildPathFromListing($rootListing, '/');
        
        // 使用反射访问私有属性
        $reflection = new ReflectionClass($this->mapper);
        $pathCache = $reflection->getProperty('pathCache');
        $pathCache->setAccessible(true);
        
        $pathCacheValue = $pathCache->getValue($this->mapper);
        
        expect($pathCacheValue['/root-file.txt'])->toBe('f123');
        expect($pathCacheValue['/root-folder'])->toBe('d456');
    });
});
