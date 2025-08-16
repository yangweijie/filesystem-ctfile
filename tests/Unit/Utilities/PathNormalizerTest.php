<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Tests\Unit\Utilities;

use YangWeijie\FilesystemCtfile\Tests\TestCase;
use YangWeijie\FilesystemCtfile\Utilities\PathNormalizer;

class PathNormalizerTest extends TestCase
{
    public function testNormalizeEmptyPath(): void
    {
        $this->assertSame('', PathNormalizer::normalize(''));
    }

    public function testNormalizeSimplePath(): void
    {
        $this->assertSame('file.txt', PathNormalizer::normalize('file.txt'));
        $this->assertSame('folder/file.txt', PathNormalizer::normalize('folder/file.txt'));
    }

    public function testNormalizeAbsolutePath(): void
    {
        $this->assertSame('/file.txt', PathNormalizer::normalize('/file.txt'));
        $this->assertSame('/folder/file.txt', PathNormalizer::normalize('/folder/file.txt'));
    }

    public function testNormalizeBackslashes(): void
    {
        $this->assertSame('folder/file.txt', PathNormalizer::normalize('folder\\file.txt'));
        $this->assertSame('/folder/file.txt', PathNormalizer::normalize('\\folder\\file.txt'));
    }

    public function testNormalizeDuplicateSeparators(): void
    {
        $this->assertSame('folder/file.txt', PathNormalizer::normalize('folder//file.txt'));
        $this->assertSame('/folder/file.txt', PathNormalizer::normalize('//folder///file.txt'));
        $this->assertSame('folder/subfolder/file.txt', PathNormalizer::normalize('folder////subfolder//file.txt'));
    }

    public function testNormalizeCurrentDirectoryReferences(): void
    {
        $this->assertSame('file.txt', PathNormalizer::normalize('./file.txt'));
        $this->assertSame('folder/file.txt', PathNormalizer::normalize('folder/./file.txt'));
        $this->assertSame('/folder/file.txt', PathNormalizer::normalize('/./folder/./file.txt'));
    }

    public function testNormalizeParentDirectoryReferences(): void
    {
        $this->assertSame('file.txt', PathNormalizer::normalize('folder/../file.txt'));
        $this->assertSame('/file.txt', PathNormalizer::normalize('/folder/../file.txt'));
        $this->assertSame('file.txt', PathNormalizer::normalize('folder/subfolder/../../file.txt'));
        $this->assertSame('/file.txt', PathNormalizer::normalize('/folder/subfolder/../../file.txt'));
    }

    public function testNormalizeRelativeParentDirectoryReferences(): void
    {
        $this->assertSame('../file.txt', PathNormalizer::normalize('../file.txt'));
        $this->assertSame('../../file.txt', PathNormalizer::normalize('../../file.txt'));
        $this->assertSame('../folder/file.txt', PathNormalizer::normalize('../folder/file.txt'));
    }

    public function testNormalizeComplexPaths(): void
    {
        $this->assertSame('folder/file.txt', PathNormalizer::normalize('./folder/../folder/./file.txt'));
        $this->assertSame('/folder/file.txt', PathNormalizer::normalize('/./folder/../folder/./file.txt'));
        $this->assertSame('../file.txt', PathNormalizer::normalize('folder/subfolder/../../../folder/../file.txt'));
    }

    public function testValidateEmptyPath(): void
    {
        $this->assertFalse(PathNormalizer::validate(''));
    }

    public function testValidateValidPaths(): void
    {
        $this->assertTrue(PathNormalizer::validate('file.txt'));
        $this->assertTrue(PathNormalizer::validate('folder/file.txt'));
        $this->assertTrue(PathNormalizer::validate('/folder/file.txt'));
        $this->assertTrue(PathNormalizer::validate('folder/subfolder/file.txt'));
    }

    public function testValidateNullBytes(): void
    {
        $this->assertFalse(PathNormalizer::validate("file\0.txt"));
        $this->assertFalse(PathNormalizer::validate("folder/file\0.txt"));
    }

    public function testValidateControlCharacters(): void
    {
        $this->assertFalse(PathNormalizer::validate("file\x01.txt"));
        $this->assertFalse(PathNormalizer::validate("file\x1F.txt"));
        $this->assertFalse(PathNormalizer::validate("file\x7F.txt"));
    }

    public function testValidatePathTraversalAttempts(): void
    {
        $this->assertFalse(PathNormalizer::validate('../../etc/passwd'));
        $this->assertFalse(PathNormalizer::validate('../../../file.txt'));
        $this->assertFalse(PathNormalizer::validate('folder/../../../file.txt'));
    }

    public function testValidateReservedWindowsNames(): void
    {
        $this->assertFalse(PathNormalizer::validate('CON'));
        $this->assertFalse(PathNormalizer::validate('PRN'));
        $this->assertFalse(PathNormalizer::validate('AUX'));
        $this->assertFalse(PathNormalizer::validate('NUL'));
        $this->assertFalse(PathNormalizer::validate('COM1'));
        $this->assertFalse(PathNormalizer::validate('LPT1'));
        $this->assertFalse(PathNormalizer::validate('folder/CON'));
        $this->assertFalse(PathNormalizer::validate('folder/con')); // Case insensitive
    }

    public function testIsAbsoluteEmptyPath(): void
    {
        $this->assertFalse(PathNormalizer::isAbsolute(''));
    }

    public function testIsAbsoluteUnixPaths(): void
    {
        $this->assertTrue(PathNormalizer::isAbsolute('/'));
        $this->assertTrue(PathNormalizer::isAbsolute('/file.txt'));
        $this->assertTrue(PathNormalizer::isAbsolute('/folder/file.txt'));
    }

    public function testIsAbsoluteWindowsPaths(): void
    {
        $this->assertTrue(PathNormalizer::isAbsolute('C:\\'));
        $this->assertTrue(PathNormalizer::isAbsolute('C:/'));
        $this->assertTrue(PathNormalizer::isAbsolute('D:\\folder\\file.txt'));
        $this->assertTrue(PathNormalizer::isAbsolute('Z:/folder/file.txt'));
    }

    public function testIsAbsoluteRelativePaths(): void
    {
        $this->assertFalse(PathNormalizer::isAbsolute('file.txt'));
        $this->assertFalse(PathNormalizer::isAbsolute('folder/file.txt'));
        $this->assertFalse(PathNormalizer::isAbsolute('./file.txt'));
        $this->assertFalse(PathNormalizer::isAbsolute('../file.txt'));
    }

    public function testJoinEmptyParts(): void
    {
        $this->assertSame('', PathNormalizer::join());
    }

    public function testJoinSinglePart(): void
    {
        $this->assertSame('file.txt', PathNormalizer::join('file.txt'));
    }

    public function testJoinMultipleParts(): void
    {
        $this->assertSame('folder/file.txt', PathNormalizer::join('folder', 'file.txt'));
        $this->assertSame('folder/subfolder/file.txt', PathNormalizer::join('folder', 'subfolder', 'file.txt'));
        $this->assertSame('/folder/file.txt', PathNormalizer::join('/', 'folder', 'file.txt'));
    }

    public function testJoinWithNormalization(): void
    {
        $this->assertSame('folder/file.txt', PathNormalizer::join('folder/', '/file.txt'));
        $this->assertSame('folder/file.txt', PathNormalizer::join('folder//', '//file.txt'));
        $this->assertSame('file.txt', PathNormalizer::join('folder', '..', 'file.txt'));
    }

    public function testDirnameEmptyPath(): void
    {
        $this->assertSame('', PathNormalizer::dirname(''));
    }

    public function testDirnameRootPath(): void
    {
        $this->assertSame('', PathNormalizer::dirname('/'));
    }

    public function testDirnameSimplePaths(): void
    {
        $this->assertSame('', PathNormalizer::dirname('file.txt'));
        $this->assertSame('folder', PathNormalizer::dirname('folder/file.txt'));
        $this->assertSame('/folder', PathNormalizer::dirname('/folder/file.txt'));
        $this->assertSame('folder/subfolder', PathNormalizer::dirname('folder/subfolder/file.txt'));
    }

    public function testDirnameRootFile(): void
    {
        $this->assertSame('/', PathNormalizer::dirname('/file.txt'));
    }

    public function testBasenameEmptyPath(): void
    {
        $this->assertSame('', PathNormalizer::basename(''));
    }

    public function testBasenameRootPath(): void
    {
        $this->assertSame('', PathNormalizer::basename('/'));
    }

    public function testBasenameSimplePaths(): void
    {
        $this->assertSame('file.txt', PathNormalizer::basename('file.txt'));
        $this->assertSame('file.txt', PathNormalizer::basename('folder/file.txt'));
        $this->assertSame('file.txt', PathNormalizer::basename('/folder/file.txt'));
        $this->assertSame('file.txt', PathNormalizer::basename('folder/subfolder/file.txt'));
    }
}
