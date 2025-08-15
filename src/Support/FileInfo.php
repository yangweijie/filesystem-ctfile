<?php

declare(strict_types=1);

namespace Yangweijie\FilesystemCtlife\Support;

use League\Flysystem\FileAttributes;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;

/**
 * 文件信息处理辅助类
 * 
 * 提供文件元数据处理、格式转换、checksum 计算等功能
 */
class FileInfo
{
    /**
     * MIME 类型映射
     */
    private const MIME_TYPES = [
        'txt' => 'text/plain',
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'pdf' => 'application/pdf',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        '7z' => 'application/x-7z-compressed',
        'tar' => 'application/x-tar',
        'gz' => 'application/gzip',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'mp4' => 'video/mp4',
        'avi' => 'video/x-msvideo',
        'mov' => 'video/quicktime',
        'wmv' => 'video/x-ms-wmv',
        'flv' => 'video/x-flv',
        'webm' => 'video/webm',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    /**
     * 从 API 响应创建文件属性
     *
     * @param array $data API 响应数据
     * @param string $path 文件路径
     * @return FileAttributes 文件属性对象
     */
    public static function fromApiResponse(array $data, string $path): FileAttributes
    {
        $size = isset($data['size']) ? (int) $data['size'] : null;
        $lastModified = isset($data['updated_at']) ? strtotime($data['updated_at']) : null;
        $mimeType = isset($data['mime_type']) ? $data['mime_type'] : self::getMimeType($path);
        $visibility = self::determineVisibility($data);
        
        $extraMetadata = [];
        if (isset($data['id'])) {
            $extraMetadata['ctfile_id'] = $data['id'];
        }
        if (isset($data['checksum'])) {
            $extraMetadata['checksum'] = $data['checksum'];
        }
        if (isset($data['download_count'])) {
            $extraMetadata['download_count'] = (int) $data['download_count'];
        }

        return new FileAttributes(
            $path,
            $size,
            $visibility,
            $lastModified,
            $mimeType,
            $extraMetadata
        );
    }

    /**
     * 从 API 响应创建目录属性
     *
     * @param array $data API 响应数据
     * @param string $path 目录路径
     * @return DirectoryAttributes 目录属性对象
     */
    public static function fromDirectoryResponse(array $data, string $path): DirectoryAttributes
    {
        $lastModified = isset($data['updated_at']) ? strtotime($data['updated_at']) : null;
        $visibility = self::determineVisibility($data);
        
        $extraMetadata = [];
        if (isset($data['id'])) {
            $extraMetadata['ctfile_id'] = $data['id'];
        }
        if (isset($data['file_count'])) {
            $extraMetadata['file_count'] = (int) $data['file_count'];
        }
        if (isset($data['folder_count'])) {
            $extraMetadata['folder_count'] = (int) $data['folder_count'];
        }

        return new DirectoryAttributes(
            $path,
            $visibility,
            $lastModified,
            $extraMetadata
        );
    }

    /**
     * 计算内容的 checksum
     *
     * @param string $content 文件内容
     * @param string $algorithm 算法名称
     * @return string checksum 值
     */
    public static function calculateChecksum(string $content, string $algorithm = 'md5'): string
    {
        return hash($algorithm, $content);
    }

    /**
     * 计算文件的 checksum
     *
     * @param string $filePath 文件路径
     * @param string $algorithm 算法名称
     * @return string checksum 值
     */
    public static function calculateFileChecksum(string $filePath, string $algorithm = 'md5'): string
    {
        if (!is_file($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        return hash_file($algorithm, $filePath);
    }

    /**
     * 根据文件路径获取 MIME 类型
     *
     * @param string $path 文件路径
     * @return string MIME 类型
     */
    public static function getMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        return self::MIME_TYPES[$extension] ?? 'application/octet-stream';
    }

    /**
     * 获取文件扩展名
     *
     * @param string $path 文件路径
     * @return string 扩展名
     */
    public static function getExtension(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    /**
     * 获取文件名（不含扩展名）
     *
     * @param string $path 文件路径
     * @return string 文件名
     */
    public static function getBasename(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * 获取文件名（含扩展名）
     *
     * @param string $path 文件路径
     * @return string 完整文件名
     */
    public static function getFilename(string $path): string
    {
        return pathinfo($path, PATHINFO_BASENAME);
    }

    /**
     * 检查是否为图片文件
     *
     * @param string $path 文件路径
     * @return bool 是否为图片
     */
    public static function isImage(string $path): bool
    {
        $mimeType = self::getMimeType($path);
        return str_starts_with($mimeType, 'image/');
    }

    /**
     * 检查是否为视频文件
     *
     * @param string $path 文件路径
     * @return bool 是否为视频
     */
    public static function isVideo(string $path): bool
    {
        $mimeType = self::getMimeType($path);
        return str_starts_with($mimeType, 'video/');
    }

    /**
     * 检查是否为音频文件
     *
     * @param string $path 文件路径
     * @return bool 是否为音频
     */
    public static function isAudio(string $path): bool
    {
        $mimeType = self::getMimeType($path);
        return str_starts_with($mimeType, 'audio/');
    }

    /**
     * 格式化文件大小
     *
     * @param int $bytes 字节数
     * @param int $precision 精度
     * @return string 格式化后的大小
     */
    public static function formatFileSize(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $size = (float) $bytes;
        $i = 0;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        // 对于字节，不显示小数
        if ($i === 0) {
            return (int) $size . ' ' . $units[$i];
        }

        return round($size, $precision) . ' ' . $units[$i];
    }

    /**
     * 验证文件名是否合法
     *
     * @param string $filename 文件名
     * @return bool 是否合法
     */
    public static function isValidFilename(string $filename): bool
    {
        // 检查是否包含非法字符
        $invalidChars = ['/', '\\', ':', '*', '?', '"', '<', '>', '|'];
        
        foreach ($invalidChars as $char) {
            if (str_contains($filename, $char)) {
                return false;
            }
        }
        
        // 检查长度
        if (strlen($filename) > 255 || strlen($filename) === 0) {
            return false;
        }
        
        // 检查是否为保留名称
        $reservedNames = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'];
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        
        return !in_array(strtoupper($nameWithoutExt), $reservedNames, true);
    }

    /**
     * 确定文件可见性
     *
     * @param array $data API 响应数据
     * @return string 可见性
     */
    private static function determineVisibility(array $data): string
    {
        // CTFile 中的可见性判断逻辑
        if (isset($data['is_public'])) {
            return $data['is_public'] ? Visibility::PUBLIC : Visibility::PRIVATE;
        }
        
        if (isset($data['visibility'])) {
            return $data['visibility'] === 'public' ? Visibility::PUBLIC : Visibility::PRIVATE;
        }
        
        // 默认为私有
        return Visibility::PRIVATE;
    }
}
