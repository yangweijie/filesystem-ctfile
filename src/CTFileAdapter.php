<?php

declare(strict_types=1);

namespace Yangweijie\FilesystemCtlife;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\UnableToListContents;
use Yangweijie\FilesystemCtlife\Exceptions\CTFileException;
use Yangweijie\FilesystemCtlife\Exceptions\AuthenticationException;
use Yangweijie\FilesystemCtlife\Exceptions\NetworkException;
use Yangweijie\FilesystemCtlife\Exceptions\RateLimitException;
use Yangweijie\FilesystemCtlife\Support\FileInfo;
use Yangweijie\FilesystemCtlife\Support\UploadHelper;

/**
 * CTFile Flysystem 适配器主类
 * 
 * 实现 League\Flysystem\FilesystemAdapter 接口，提供完整的文件系统抽象
 * 支持 CTFile 云存储的所有基本操作
 */
class CTFileAdapter implements FilesystemAdapter
{
    /**
     * 配置实例
     */
    private CTFileConfig $config;

    /**
     * API 客户端
     */
    private CTFileClient $client;

    /**
     * 路径映射器
     */
    private PathMapper $pathMapper;

    /**
     * 上传辅助器
     */
    private UploadHelper $uploadHelper;

    /**
     * 构造函数
     *
     * @param CTFileConfig $config 配置实例
     */
    public function __construct(CTFileConfig $config)
    {
        $this->config = $config;
        $this->client = new CTFileClient($config);
        $this->pathMapper = new PathMapper($this->client, $config);
        $this->uploadHelper = new UploadHelper($this->client);
    }

    /**
     * 检查文件是否存在
     *
     * @param string $path 文件路径
     * @return bool 文件是否存在
     * @throws UnableToCheckFileExistence 当检查失败时
     */
    public function fileExists(string $path): bool
    {
        try {
            return $this->checkExistence($path, 'file');
        } catch (CTFileException $e) {
            throw UnableToCheckFileExistence::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * 检查目录是否存在
     *
     * @param string $path 目录路径
     * @return bool 目录是否存在
     * @throws UnableToCheckDirectoryExistence 当检查失败时
     */
    public function directoryExists(string $path): bool
    {
        try {
            return $this->checkExistence($path, 'directory');
        } catch (CTFileException $e) {
            throw UnableToCheckDirectoryExistence::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * 写入文件内容
     *
     * @param string $path 文件路径
     * @param string $contents 文件内容
     * @param Config $config 配置
     * @throws \League\Flysystem\FilesystemException
     */
    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $this->performWrite($path, $contents, $config);
        } catch (CTFileException $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * 写入文件流
     *
     * @param string $path 文件路径
     * @param mixed $contents 文件流
     * @param Config $config 配置
     * @throws \League\Flysystem\FilesystemException
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            // 将流转换为字符串内容
            if (is_resource($contents)) {
                rewind($contents);
                $stringContents = stream_get_contents($contents);
                if ($stringContents === false) {
                    throw new CTFileException("Failed to read from stream for path: {$path}");
                }
            } else {
                $stringContents = (string) $contents;
            }

            $this->performWrite($path, $stringContents, $config);
        } catch (CTFileException $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * 读取文件内容
     *
     * @param string $path 文件路径
     * @return string 文件内容
     * @throws \League\Flysystem\FilesystemException
     */
    public function read(string $path): string
    {
        try {
            // 1. 获取文件ID
            $fileId = $this->pathMapper->getFileId($path);

            // 2. 获取动态下载链接
            $downloadResponse = $this->client->getDownloadUrl($fileId);

            if (!isset($downloadResponse['data']['download_url'])) {
                throw new CTFileException("Failed to get download URL for file: {$path}");
            }

            $downloadUrl = $downloadResponse['data']['download_url'];

            // 3. 下载文件内容
            $content = $this->downloadFileContent($downloadUrl);

            return $content;
        } catch (CTFileException $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * 读取文件流
     *
     * @param string $path 文件路径
     * @return resource 文件流
     * @throws \League\Flysystem\FilesystemException
     */
    public function readStream(string $path)
    {
        try {
            // 读取文件内容并创建内存流
            $content = $this->read($path);

            $stream = fopen('php://memory', 'r+');
            if ($stream === false) {
                throw new CTFileException("Failed to create memory stream for file: {$path}");
            }

            fwrite($stream, $content);
            rewind($stream);

            return $stream;
        } catch (CTFileException $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * 删除文件
     *
     * @param string $path 文件路径
     * @throws \League\Flysystem\FilesystemException
     */
    public function delete(string $path): void
    {
        try {
            // 1. 获取文件ID
            $fileId = $this->pathMapper->getFileId($path);

            // 2. 调用删除API（移动到回收站）
            $this->client->deleteFile($fileId);

            // 3. 清理路径映射缓存
            $this->pathMapper->invalidateCache($path);

        } catch (CTFileException $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * 删除目录
     *
     * @param string $path 目录路径
     * @throws \League\Flysystem\FilesystemException
     */
    public function deleteDirectory(string $path): void
    {
        try {
            // 不允许删除根目录
            if ($path === '/' || $path === '') {
                throw new CTFileException("Cannot delete root directory");
            }

            // 1. 获取目录ID
            $directoryId = $this->pathMapper->getDirectoryId($path);

            // 2. 调用删除API（移动到回收站）
            $this->client->deleteFolder($directoryId);

            // 3. 清理路径映射缓存（包括子路径）
            $this->pathMapper->invalidateCache($path);

        } catch (CTFileException $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * 创建目录
     *
     * @param string $path 目录路径
     * @param Config $config 配置
     * @throws \League\Flysystem\FilesystemException
     */
    public function createDirectory(string $path, Config $config): void
    {
        try {
            // 不允许创建根目录
            if ($path === '/' || $path === '') {
                throw new CTFileException("Cannot create root directory");
            }

            // 检查目录是否已存在
            if ($this->directoryExists($path)) {
                return; // 目录已存在，直接返回
            }

            // 1. 解析父目录路径和目录名
            $pathInfo = $this->parseDirectoryPath($path);
            $parentId = $pathInfo['parent_id'];
            $directoryName = $pathInfo['directory_name'];

            // 2. 调用创建文件夹API
            $createResult = $this->client->createFolder($directoryName, $parentId);

            if (!isset($createResult['data']['id'])) {
                throw new CTFileException("Failed to create directory: {$path}");
            }

            // 3. 更新路径映射
            $this->pathMapper->cachePath($path, $createResult['data']['id']);

        } catch (CTFileException $e) {
            throw UnableToCreateDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * 设置文件可见性
     *
     * @param string $path 文件路径
     * @param string $visibility 可见性
     * @throws \League\Flysystem\FilesystemException
     */
    public function setVisibility(string $path, string $visibility): void
    {
        try {
            // CTFile 不直接支持可见性设置，这里记录但不执行实际操作
            // 在实际应用中，可见性通常在上传时设置
            // 这里我们抛出一个说明性异常
            throw new CTFileException("CTFile does not support changing visibility after upload. Visibility should be set during upload.");
        } catch (CTFileException $e) {
            throw UnableToSetVisibility::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * 获取文件可见性
     *
     * @param string $path 文件路径
     * @return FileAttributes 文件属性
     * @throws \League\Flysystem\FilesystemException
     */
    public function visibility(string $path): FileAttributes
    {
        try {
            $metadata = $this->getFileMetadata($path);
            return FileInfo::fromApiResponse($metadata, $path);
        } catch (CTFileException $e) {
            throw UnableToRetrieveMetadata::visibility($path, $e->getMessage(), $e);
        }
    }

    /**
     * 获取文件 MIME 类型
     *
     * @param string $path 文件路径
     * @return FileAttributes 文件属性
     * @throws \League\Flysystem\FilesystemException
     */
    public function mimeType(string $path): FileAttributes
    {
        try {
            $metadata = $this->getFileMetadata($path);
            return FileInfo::fromApiResponse($metadata, $path);
        } catch (CTFileException $e) {
            throw UnableToRetrieveMetadata::mimeType($path, $e->getMessage(), $e);
        }
    }

    /**
     * 获取文件最后修改时间
     *
     * @param string $path 文件路径
     * @return FileAttributes 文件属性
     * @throws \League\Flysystem\FilesystemException
     */
    public function lastModified(string $path): FileAttributes
    {
        try {
            $metadata = $this->getFileMetadata($path);
            return FileInfo::fromApiResponse($metadata, $path);
        } catch (CTFileException $e) {
            throw UnableToRetrieveMetadata::lastModified($path, $e->getMessage(), $e);
        }
    }

    /**
     * 获取文件大小
     *
     * @param string $path 文件路径
     * @return FileAttributes 文件属性
     * @throws \League\Flysystem\FilesystemException
     */
    public function fileSize(string $path): FileAttributes
    {
        try {
            $metadata = $this->getFileMetadata($path);
            return FileInfo::fromApiResponse($metadata, $path);
        } catch (CTFileException $e) {
            throw UnableToRetrieveMetadata::fileSize($path, $e->getMessage(), $e);
        }
    }

    /**
     * 列出目录内容
     *
     * @param string $path 目录路径
     * @param bool $deep 是否深度遍历
     * @return iterable<StorageAttributes> 存储属性迭代器
     * @throws \League\Flysystem\FilesystemException
     */
    public function listContents(string $path, bool $deep): iterable
    {
        try {
            return $this->listDirectory($path, $deep);
        } catch (CTFileException $e) {
            throw new UnableToListContents($e->getMessage(), $e);
        }
    }

    /**
     * 移动文件或目录
     *
     * @param string $source 源路径
     * @param string $destination 目标路径
     * @param Config $config 配置
     * @throws \League\Flysystem\FilesystemException
     */
    public function move(string $source, string $destination, Config $config): void
    {
        try {
            // 检查源文件/目录是否存在
            $isFile = $this->fileExists($source);
            $isDirectory = !$isFile && $this->directoryExists($source);

            if (!$isFile && !$isDirectory) {
                throw new CTFileException("Source path does not exist: {$source}");
            }

            if ($isFile) {
                $this->moveFile($source, $destination);
            } else {
                $this->moveDirectory($source, $destination);
            }

        } catch (CTFileException $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    /**
     * 复制文件
     *
     * @param string $source 源路径
     * @param string $destination 目标路径
     * @param Config $config 配置
     * @throws \League\Flysystem\FilesystemException
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            // 检查源文件是否存在
            if (!$this->fileExists($source)) {
                throw new CTFileException("Source file does not exist: {$source}");
            }

            // 1. 获取源文件ID
            $sourceFileId = $this->pathMapper->getFileId($source);

            // 2. 解析目标路径
            $destinationInfo = $this->parseFilePath($destination);
            $targetFolderId = $destinationInfo['folder_id'];
            $newFilename = $destinationInfo['filename'];

            // 3. 调用复制API
            $copyResult = $this->client->copyFile($sourceFileId, $targetFolderId, $newFilename);

            if (!isset($copyResult['data']['id'])) {
                throw new CTFileException("Failed to copy file from {$source} to {$destination}");
            }

            // 4. 更新路径映射
            $this->pathMapper->cachePath($destination, $copyResult['data']['id']);

        } catch (CTFileException $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    /**
     * 检查文件或目录是否存在
     *
     * @param string $path 路径
     * @param string $type 类型：'file' 或 'directory'
     * @return bool 是否存在
     * @throws CTFileException 当检查失败时
     */
    private function checkExistence(string $path, string $type): bool
    {
        try {
            if ($type === 'file') {
                $this->pathMapper->getFileId($path);
            } else {
                $this->pathMapper->getDirectoryId($path);
            }
            return true;
        } catch (CTFileException $e) {
            // 如果是"不存在"的错误，返回 false
            if (str_contains($e->getMessage(), 'not found')) {
                return false;
            }
            // 其他错误重新抛出
            throw $e;
        }
    }

    /**
     * 获取配置实例
     *
     * @return CTFileConfig 配置实例
     */
    public function getConfig(): CTFileConfig
    {
        return $this->config;
    }

    /**
     * 获取客户端实例
     *
     * @return CTFileClient 客户端实例
     */
    public function getClient(): CTFileClient
    {
        return $this->client;
    }

    /**
     * 获取路径映射器实例
     *
     * @return PathMapper 路径映射器实例
     */
    public function getPathMapper(): PathMapper
    {
        return $this->pathMapper;
    }

    /**
     * 获取上传辅助器实例
     *
     * @return UploadHelper 上传辅助器实例
     */
    public function getUploadHelper(): UploadHelper
    {
        return $this->uploadHelper;
    }

    /**
     * 执行文件写入操作
     *
     * @param string $path 文件路径
     * @param string $contents 文件内容
     * @param Config $config 配置
     * @throws CTFileException 当写入失败时
     */
    private function performWrite(string $path, string $contents, Config $config): void
    {
        // 1. 解析路径，获取目标文件夹ID和文件名
        $pathInfo = $this->parseFilePath($path);
        $folderId = $pathInfo['folder_id'];
        $filename = $pathInfo['filename'];

        // 2. 准备上传配置
        $uploadConfig = [
            'folder_id' => $folderId,
            'filename' => $filename,
        ];

        // 3. 执行上传
        $uploadResult = $this->uploadHelper->upload($path, $contents, $uploadConfig);

        // 4. 更新路径映射缓存
        if (isset($uploadResult['data']['id'])) {
            $this->pathMapper->cachePath($path, $uploadResult['data']['id']);
        }
    }

    /**
     * 下载文件内容
     *
     * @param string $downloadUrl 下载URL
     * @return string 文件内容
     * @throws CTFileException 当下载失败时
     */
    private function downloadFileContent(string $downloadUrl): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => $this->config->getTimeout(),
                'user_agent' => 'CTFile-Flysystem-Adapter/1.0',
                'follow_location' => true,
                'max_redirects' => 5,
            ],
        ]);

        $content = file_get_contents($downloadUrl, false, $context);

        if ($content === false) {
            throw new CTFileException("Failed to download file from URL: {$downloadUrl}");
        }

        return $content;
    }

    /**
     * 解析文件路径，获取文件夹ID和文件名
     *
     * @param string $path 文件路径
     * @return array 包含 folder_id 和 filename 的数组
     * @throws CTFileException 当路径解析失败时
     */
    private function parseFilePath(string $path): array
    {
        $path = trim($path, '/');

        if (empty($path)) {
            throw new CTFileException("Invalid file path: cannot write to root directory");
        }

        $pathParts = explode('/', $path);
        $filename = array_pop($pathParts);

        if (empty($filename)) {
            throw new CTFileException("Invalid file path: missing filename");
        }

        // 验证文件名
        if (!FileInfo::isValidFilename($filename)) {
            throw new CTFileException("Invalid filename: {$filename}");
        }

        // 获取父目录ID
        if (empty($pathParts)) {
            // 文件在根目录
            $folderId = 'd0';
        } else {
            // 文件在子目录
            $folderPath = '/' . implode('/', $pathParts);
            try {
                $folderId = $this->pathMapper->getDirectoryId($folderPath);
            } catch (CTFileException $e) {
                // 如果目录不存在，尝试创建
                $folderId = $this->createDirectoryPath($folderPath);
            }
        }

        return [
            'folder_id' => $folderId,
            'filename' => $filename,
        ];
    }

    /**
     * 创建目录路径
     *
     * @param string $path 目录路径
     * @return string 创建的目录ID
     * @throws CTFileException 当创建失败时
     */
    private function createDirectoryPath(string $path): string
    {
        $path = trim($path, '/');
        $pathParts = explode('/', $path);
        $currentPath = '';
        $currentId = 'd0';

        foreach ($pathParts as $part) {
            if (empty($part)) {
                continue;
            }

            $currentPath = $currentPath === '' ? $part : $currentPath . '/' . $part;
            $fullPath = '/' . $currentPath;

            try {
                // 尝试获取现有目录ID
                $currentId = $this->pathMapper->getDirectoryId($fullPath);
            } catch (CTFileException $e) {
                // 目录不存在，创建新目录
                $createResult = $this->client->createFolder($part, $currentId);

                if (!isset($createResult['data']['id'])) {
                    throw new CTFileException("Failed to create directory: {$fullPath}");
                }

                $currentId = $createResult['data']['id'];
                $this->pathMapper->cachePath($fullPath, $currentId);
            }
        }

        return $currentId;
    }

    /**
     * 解析目录路径，获取父目录ID和目录名
     *
     * @param string $path 目录路径
     * @return array 包含 parent_id 和 directory_name 的数组
     * @throws CTFileException 当路径解析失败时
     */
    private function parseDirectoryPath(string $path): array
    {
        $path = trim($path, '/');

        if (empty($path)) {
            throw new CTFileException("Invalid directory path: cannot parse root directory");
        }

        $pathParts = explode('/', $path);
        $directoryName = array_pop($pathParts);

        if (empty($directoryName)) {
            throw new CTFileException("Invalid directory path: missing directory name");
        }

        // 验证目录名
        if (!FileInfo::isValidFilename($directoryName)) {
            throw new CTFileException("Invalid directory name: {$directoryName}");
        }

        // 获取父目录ID
        if (empty($pathParts)) {
            // 目录在根目录
            $parentId = 'd0';
        } else {
            // 目录在子目录
            $parentPath = '/' . implode('/', $pathParts);
            $parentId = $this->pathMapper->getDirectoryId($parentPath);
        }

        return [
            'parent_id' => $parentId,
            'directory_name' => $directoryName,
        ];
    }

    /**
     * 移动文件
     *
     * @param string $source 源文件路径
     * @param string $destination 目标文件路径
     * @throws CTFileException 当移动失败时
     */
    private function moveFile(string $source, string $destination): void
    {
        // 1. 获取源文件ID
        $sourceFileId = $this->pathMapper->getFileId($source);

        // 2. 解析目标路径
        $destinationInfo = $this->parseFilePath($destination);
        $targetFolderId = $destinationInfo['folder_id'];
        $newFilename = $destinationInfo['filename'];

        // 3. 检查是否需要重命名
        $sourceFilename = basename($source);
        if ($sourceFilename !== $newFilename) {
            // 需要重命名，先移动再重命名
            $this->client->moveFile($sourceFileId, $targetFolderId);
            $this->client->rename($sourceFileId, $newFilename, 'file');
        } else {
            // 只需要移动
            $this->client->moveFile($sourceFileId, $targetFolderId);
        }

        // 4. 更新路径映射
        $this->pathMapper->invalidateCache($source);
        $this->pathMapper->cachePath($destination, $sourceFileId);
    }

    /**
     * 移动目录
     *
     * @param string $source 源目录路径
     * @param string $destination 目标目录路径
     * @throws CTFileException 当移动失败时
     */
    private function moveDirectory(string $source, string $destination): void
    {
        // 1. 获取源目录ID
        $sourceDirectoryId = $this->pathMapper->getDirectoryId($source);

        // 2. 解析目标路径
        $destinationInfo = $this->parseDirectoryPath($destination);
        $targetParentId = $destinationInfo['parent_id'];
        $newDirectoryName = $destinationInfo['directory_name'];

        // 3. 检查是否需要重命名
        $sourceDirectoryName = basename($source);
        if ($sourceDirectoryName !== $newDirectoryName) {
            // 需要重命名，先移动再重命名
            $this->client->moveFile($sourceDirectoryId, $targetParentId);
            $this->client->rename($sourceDirectoryId, $newDirectoryName, 'folder');
        } else {
            // 只需要移动
            $this->client->moveFile($sourceDirectoryId, $targetParentId);
        }

        // 4. 更新路径映射
        $this->pathMapper->invalidateCache($source);
        $this->pathMapper->cachePath($destination, $sourceDirectoryId);
    }

    /**
     * 获取文件元数据
     *
     * @param string $path 文件路径
     * @return array 文件元数据
     * @throws CTFileException 当获取失败时
     */
    private function getFileMetadata(string $path): array
    {
        // 1. 获取文件ID
        $fileId = $this->pathMapper->getFileId($path);

        // 2. 调用API获取文件信息
        $response = $this->client->getFileInfo($fileId);

        if (!isset($response['data'])) {
            throw new CTFileException("Failed to get file metadata for: {$path}");
        }

        return $response['data'];
    }

    /**
     * 列出目录内容
     *
     * @param string $path 目录路径
     * @param bool $deep 是否深度遍历
     * @return \Generator<StorageAttributes> 存储属性生成器
     * @throws CTFileException 当列出失败时
     */
    private function listDirectory(string $path, bool $deep): \Generator
    {
        // 1. 获取目录ID
        $directoryId = $this->pathMapper->getDirectoryId($path);

        // 2. 调用文件列表API
        $page = 1;
        $pageSize = 100;

        do {
            $response = $this->client->getFileList($directoryId, $page, $pageSize);

            if (!isset($response['data']) || !is_array($response['data'])) {
                break;
            }

            // 3. 处理当前页的数据
            foreach ($response['data'] as $item) {
                if (!isset($item['id']) || !isset($item['name'])) {
                    continue;
                }

                $itemPath = $path === '/' ? '/' . $item['name'] : $path . '/' . $item['name'];

                // 4. 转换为 StorageAttributes
                $attributes = $this->convertToAttributes($item, $itemPath);

                if ($attributes !== null) {
                    // 5. 更新路径映射缓存
                    $this->pathMapper->cachePath($itemPath, $item['id']);

                    yield $attributes;

                    // 6. 处理深度遍历
                    if ($deep && $attributes instanceof DirectoryAttributes) {
                        yield from $this->listDirectory($itemPath, true);
                    }
                }
            }

            $page++;

            // 检查是否还有更多页
            $hasMore = isset($response['pagination']['has_more']) ? $response['pagination']['has_more'] : false;

        } while ($hasMore && count($response['data']) === $pageSize);
    }

    /**
     * 转换API响应为存储属性
     *
     * @param array $data API响应数据
     * @param string $path 文件路径
     * @return StorageAttributes|null 存储属性对象
     */
    private function convertToAttributes(array $data, string $path): ?StorageAttributes
    {
        if (!isset($data['id']) || !isset($data['name'])) {
            return null;
        }

        // 根据ID前缀判断类型
        $id = $data['id'];

        if (str_starts_with($id, 'f')) {
            // 文件
            return FileInfo::fromApiResponse($data, $path);
        } elseif (str_starts_with($id, 'd')) {
            // 目录
            return FileInfo::fromDirectoryResponse($data, $path);
        }

        return null;
    }

    /**
     * 处理异常并转换为适当的 Flysystem 异常
     *
     * @param \Throwable $exception 原始异常
     * @param string $operation 操作名称
     * @param string $path 文件路径
     * @throws \League\Flysystem\FilesystemException
     */
    private function handleException(\Throwable $exception, string $operation, string $path): void
    {
        // 如果已经是 Flysystem 异常，直接重新抛出
        if ($exception instanceof \League\Flysystem\FilesystemException) {
            throw $exception;
        }

        // 根据异常类型和操作类型进行转换
        if ($exception instanceof AuthenticationException) {
            $this->handleAuthenticationException($exception, $operation, $path);
        } elseif ($exception instanceof NetworkException) {
            $this->handleNetworkException($exception, $operation, $path);
        } elseif ($exception instanceof RateLimitException) {
            $this->handleRateLimitException($exception, $operation, $path);
        } elseif ($exception instanceof CTFileException) {
            $this->handleCTFileException($exception, $operation, $path);
        } else {
            // 未知异常，包装为通用异常
            throw new \League\Flysystem\UnableToWriteFile($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * 处理认证异常
     *
     * @param AuthenticationException $exception 认证异常
     * @param string $operation 操作名称
     * @param string $path 文件路径
     * @throws \League\Flysystem\FilesystemException
     */
    private function handleAuthenticationException(AuthenticationException $exception, string $operation, string $path): void
    {
        switch ($operation) {
            case 'read':
                throw UnableToReadFile::fromLocation($path, "Authentication failed: " . $exception->getMessage(), $exception);
            case 'write':
                throw UnableToWriteFile::atLocation($path, "Authentication failed: " . $exception->getMessage(), $exception);
            case 'delete':
                throw UnableToDeleteFile::atLocation($path, "Authentication failed: " . $exception->getMessage(), $exception);
            case 'list':
                throw new UnableToListContents("Authentication failed: " . $exception->getMessage(), $exception);
            case 'metadata':
                throw UnableToRetrieveMetadata::create($path, 'metadata', "Authentication failed: " . $exception->getMessage(), $exception);
            default:
                throw new \League\Flysystem\UnableToWriteFile($path, "Authentication failed: " . $exception->getMessage(), $exception);
        }
    }

    /**
     * 处理网络异常
     *
     * @param NetworkException $exception 网络异常
     * @param string $operation 操作名称
     * @param string $path 文件路径
     * @throws \League\Flysystem\FilesystemException
     */
    private function handleNetworkException(NetworkException $exception, string $operation, string $path): void
    {
        switch ($operation) {
            case 'read':
                throw UnableToReadFile::fromLocation($path, "Network error: " . $exception->getMessage(), $exception);
            case 'write':
                throw UnableToWriteFile::atLocation($path, "Network error: " . $exception->getMessage(), $exception);
            case 'delete':
                throw UnableToDeleteFile::atLocation($path, "Network error: " . $exception->getMessage(), $exception);
            case 'list':
                throw new UnableToListContents("Network error: " . $exception->getMessage(), $exception);
            case 'metadata':
                throw UnableToRetrieveMetadata::create($path, 'metadata', "Network error: " . $exception->getMessage(), $exception);
            default:
                throw new \League\Flysystem\UnableToWriteFile($path, "Network error: " . $exception->getMessage(), $exception);
        }
    }

    /**
     * 处理频率限制异常
     *
     * @param RateLimitException $exception 频率限制异常
     * @param string $operation 操作名称
     * @param string $path 文件路径
     * @throws \League\Flysystem\FilesystemException
     */
    private function handleRateLimitException(RateLimitException $exception, string $operation, string $path): void
    {
        $message = $exception->getMessage() . " (retry after {$exception->getRetryAfter()} seconds)";

        switch ($operation) {
            case 'read':
                throw UnableToReadFile::fromLocation($path, $message, $exception);
            case 'write':
                throw UnableToWriteFile::atLocation($path, $message, $exception);
            case 'delete':
                throw UnableToDeleteFile::atLocation($path, $message, $exception);
            case 'list':
                throw new UnableToListContents($message, $exception);
            case 'metadata':
                throw UnableToRetrieveMetadata::create($path, 'metadata', $message, $exception);
            default:
                throw new \League\Flysystem\UnableToWriteFile($path, $message, $exception);
        }
    }

    /**
     * 处理 CTFile 异常
     *
     * @param CTFileException $exception CTFile 异常
     * @param string $operation 操作名称
     * @param string $path 文件路径
     * @throws \League\Flysystem\FilesystemException
     */
    private function handleCTFileException(CTFileException $exception, string $operation, string $path): void
    {
        switch ($operation) {
            case 'read':
                throw UnableToReadFile::fromLocation($path, $exception->getMessage(), $exception);
            case 'write':
                throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
            case 'delete':
                throw UnableToDeleteFile::atLocation($path, $exception->getMessage(), $exception);
            case 'list':
                throw new UnableToListContents($exception->getMessage(), $exception);
            case 'metadata':
                throw UnableToRetrieveMetadata::create($path, 'metadata', $exception->getMessage(), $exception);
            default:
                throw new \League\Flysystem\UnableToWriteFile($path, $exception->getMessage(), $exception);
        }
    }
}
