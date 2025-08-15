<?php

declare(strict_types=1);

namespace Yangweijie\FilesystemCtlife\Support;

use Yangweijie\FilesystemCtlife\CTFileClient;
use Yangweijie\FilesystemCtlife\Exceptions\CTFileException;

/**
 * 文件上传辅助类
 * 
 * 提供文件上传流程管理、上传URL获取、文件上传等功能
 */
class UploadHelper
{
    /**
     * CTFile API 客户端
     */
    private CTFileClient $client;

    /**
     * 构造函数
     *
     * @param CTFileClient $client API 客户端
     */
    public function __construct(CTFileClient $client)
    {
        $this->client = $client;
    }

    /**
     * 上传文件
     *
     * @param string $path 目标路径
     * @param mixed $content 文件内容（字符串或资源）
     * @param array $config 上传配置
     * @return array 上传结果
     * @throws CTFileException 当上传失败时
     */
    public function upload(string $path, $content, array $config = []): array
    {
        $uploadData = $this->prepareUploadData($content);
        $filename = $config['filename'] ?? basename($path);
        $folderId = $config['folder_id'] ?? 'd0';
        
        // 第一步：获取上传URL
        $uploadUrl = $this->getUploadUrl(
            $folderId,
            $filename,
            $uploadData['size'],
            $uploadData['checksum']
        );
        
        // 第二步：上传文件
        return $this->uploadFile($uploadUrl, $filename, $uploadData['content']);
    }

    /**
     * 获取上传URL
     *
     * @param string $folderId 目标文件夹ID
     * @param string $filename 文件名
     * @param int $size 文件大小
     * @param string $checksum 文件校验和
     * @return string 上传URL
     * @throws CTFileException 当获取失败时
     */
    public function getUploadUrl(string $folderId, string $filename, int $size, string $checksum): string
    {
        $response = $this->client->getUploadUrl($folderId, $filename, $size, $checksum);
        
        if (!isset($response['data']['upload_url'])) {
            throw new CTFileException('Failed to get upload URL: ' . json_encode($response));
        }
        
        return $response['data']['upload_url'];
    }

    /**
     * 上传文件到指定URL
     *
     * @param string $uploadUrl 上传URL
     * @param string $filename 文件名
     * @param mixed $content 文件内容
     * @return array 上传结果
     * @throws CTFileException 当上传失败时
     */
    public function uploadFile(string $uploadUrl, string $filename, $content): array
    {
        // 准备上传参数
        $params = [
            'filename' => $filename,
        ];
        
        // 如果内容是资源，转换为字符串
        if (is_resource($content)) {
            rewind($content);
            $content = stream_get_contents($content);
        }
        
        return $this->client->upload($uploadUrl, $content, $params);
    }

    /**
     * 分块上传大文件
     *
     * @param string $path 目标路径
     * @param mixed $content 文件内容
     * @param array $config 上传配置
     * @return array 上传结果
     */
    public function uploadLargeFile(string $path, $content, array $config = []): array
    {
        $chunkSize = $config['chunk_size'] ?? 5 * 1024 * 1024; // 默认5MB
        $uploadData = $this->prepareUploadData($content);
        
        // 如果文件小于分块大小，使用普通上传
        if ($uploadData['size'] <= $chunkSize) {
            return $this->upload($path, $content, $config);
        }
        
        // TODO: 实现分块上传逻辑
        // 目前先使用普通上传
        return $this->upload($path, $content, $config);
    }

    /**
     * 从URL上传文件
     *
     * @param string $path 目标路径
     * @param string $sourceUrl 源URL
     * @param array $config 上传配置
     * @return array 上传结果
     */
    public function uploadFromUrl(string $path, string $sourceUrl, array $config = []): array
    {
        // 下载文件内容
        $content = $this->downloadFromUrl($sourceUrl);
        
        return $this->upload($path, $content, $config);
    }

    /**
     * 验证上传参数
     *
     * @param string $filename 文件名
     * @param int $size 文件大小
     * @param array $config 配置
     * @return bool 是否有效
     */
    public function validateUploadParams(string $filename, int $size, array $config = []): bool
    {
        // 检查文件名
        if (!FileInfo::isValidFilename($filename)) {
            return false;
        }
        
        // 检查文件大小
        $maxSize = $config['max_file_size'] ?? 100 * 1024 * 1024; // 默认100MB
        if ($size > $maxSize) {
            return false;
        }
        
        // 检查文件类型
        if (isset($config['allowed_extensions'])) {
            $extension = FileInfo::getExtension($filename);
            if (!in_array($extension, $config['allowed_extensions'], true)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * 准备上传数据
     *
     * @param mixed $content 文件内容
     * @return array 包含内容、大小和校验和的数组
     */
    private function prepareUploadData($content): array
    {
        // 处理不同类型的内容
        if (is_resource($content)) {
            rewind($content);
            $stringContent = stream_get_contents($content);
            rewind($content);
        } elseif (is_string($content)) {
            $stringContent = $content;
        } else {
            throw new \InvalidArgumentException('Content must be a string or resource');
        }
        
        $size = strlen($stringContent);
        $checksum = FileInfo::calculateChecksum($stringContent);
        
        return [
            'content' => $content,
            'size' => $size,
            'checksum' => $checksum,
        ];
    }

    /**
     * 从URL下载内容
     *
     * @param string $url URL地址
     * @return string 下载的内容
     * @throws CTFileException 当下载失败时
     */
    private function downloadFromUrl(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'CTFile-Flysystem-Adapter/1.0',
            ],
        ]);
        
        $content = file_get_contents($url, false, $context);
        
        if ($content === false) {
            throw new CTFileException("Failed to download from URL: {$url}");
        }
        
        return $content;
    }

    /**
     * 生成唯一文件名
     *
     * @param string $originalName 原始文件名
     * @param string $prefix 前缀
     * @return string 唯一文件名
     */
    public function generateUniqueFilename(string $originalName, string $prefix = ''): string
    {
        $extension = FileInfo::getExtension($originalName);
        $basename = FileInfo::getBasename($originalName);
        $timestamp = time();
        $random = substr(md5(uniqid()), 0, 8);
        
        $uniqueName = $prefix . $basename . '_' . $timestamp . '_' . $random;
        
        return $extension ? $uniqueName . '.' . $extension : $uniqueName;
    }

    /**
     * 检查文件是否已存在
     *
     * @param string $folderId 文件夹ID
     * @param string $filename 文件名
     * @return bool 是否存在
     */
    public function fileExists(string $folderId, string $filename): bool
    {
        try {
            $listing = $this->client->getFileList($folderId, 1, 100);
            
            if (!isset($listing['data'])) {
                return false;
            }
            
            foreach ($listing['data'] as $item) {
                if (isset($item['name']) && $item['name'] === $filename) {
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取上传进度回调
     *
     * @param callable $callback 进度回调函数
     * @return callable cURL 进度回调
     */
    public function getProgressCallback(callable $callback): callable
    {
        return function ($downloadTotal, $downloadNow, $uploadTotal, $uploadNow) use ($callback) {
            if ($uploadTotal > 0) {
                $progress = ($uploadNow / $uploadTotal) * 100;
                $callback($progress, $uploadNow, $uploadTotal);
            }
        };
    }

    /**
     * 清理临时文件
     *
     * @param array $tempFiles 临时文件列表
     */
    public function cleanupTempFiles(array $tempFiles): void
    {
        foreach ($tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
