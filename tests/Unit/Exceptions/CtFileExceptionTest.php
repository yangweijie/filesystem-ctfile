<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Tests\Unit\Exceptions;

use League\Flysystem\FilesystemException;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileException;
use YangWeijie\FilesystemCtfile\Tests\TestCase;

class CtFileExceptionTest extends TestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new CtFileException('Test message');

        $this->assertInstanceOf(FilesystemException::class, $exception);
        $this->assertInstanceOf(CtFileException::class, $exception);
    }

    public function testBasicExceptionCreation(): void
    {
        $exception = new CtFileException('Test message');

        $this->assertSame('Test message', $exception->getMessage());
        $this->assertSame('', $exception->getOperation());
        $this->assertSame('', $exception->getPath());
        $this->assertSame([], $exception->getContext());
    }

    public function testExceptionWithAllParameters(): void
    {
        $context = ['key' => 'value'];
        $previous = new \Exception('Previous exception');

        $exception = new CtFileException(
            'Test message',
            'test_operation',
            '/test/path',
            $context,
            123,
            $previous
        );

        $this->assertSame('Test message | Operation: test_operation | Path: /test/path', $exception->getMessage());
        $this->assertSame('test_operation', $exception->getOperation());
        $this->assertSame('/test/path', $exception->getPath());
        $this->assertSame($context, $exception->getContext());
        $this->assertSame(123, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testEnhancedMessageBuilding(): void
    {
        $exception = new CtFileException('Error occurred', 'upload', '/file.txt');
        $this->assertSame('Error occurred | Operation: upload | Path: /file.txt', $exception->getMessage());

        $exception = new CtFileException('Error occurred', 'upload', '');
        $this->assertSame('Error occurred | Operation: upload', $exception->getMessage());

        $exception = new CtFileException('Error occurred', '', '/file.txt');
        $this->assertSame('Error occurred | Path: /file.txt', $exception->getMessage());
    }

    public function testSetContext(): void
    {
        $exception = new CtFileException('Test message');
        $context = ['key1' => 'value1', 'key2' => 'value2'];

        $result = $exception->setContext($context);

        $this->assertSame($exception, $result);
        $this->assertSame($context, $exception->getContext());
    }

    public function testAddContext(): void
    {
        $exception = new CtFileException('Test message');

        $result = $exception->addContext('key1', 'value1');
        $this->assertSame($exception, $result);
        $this->assertSame(['key1' => 'value1'], $exception->getContext());

        $exception->addContext('key2', 'value2');
        $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $exception->getContext());
    }

    public function testForOperationFactory(): void
    {
        $previous = new \Exception('Previous');
        $exception = CtFileException::forOperation('Test message', 'upload', '/file.txt', $previous);

        $this->assertInstanceOf(CtFileException::class, $exception);
        $this->assertSame('Test message | Operation: upload | Path: /file.txt', $exception->getMessage());
        $this->assertSame('upload', $exception->getOperation());
        $this->assertSame('/file.txt', $exception->getPath());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testForPathFactory(): void
    {
        $previous = new \Exception('Previous');
        $exception = CtFileException::forPath('Test message', '/file.txt', $previous);

        $this->assertInstanceOf(CtFileException::class, $exception);
        $this->assertSame('Test message | Path: /file.txt', $exception->getMessage());
        $this->assertSame('', $exception->getOperation());
        $this->assertSame('/file.txt', $exception->getPath());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
