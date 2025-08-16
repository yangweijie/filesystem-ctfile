<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Tests\Unit;

use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use Mockery;
use Psr\Log\LoggerInterface;
use YangWeijie\FilesystemCtfile\ErrorHandler;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileException;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileOperationException;
use YangWeijie\FilesystemCtfile\Tests\TestCase;

class ErrorHandlerTest extends TestCase
{
    private ErrorHandler $errorHandler;

    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->errorHandler = new ErrorHandler($this->logger);
    }

    public function testConstructorUsesNullLoggerWhenNoLoggerProvided(): void
    {
        $handler = new ErrorHandler();
        $this->assertInstanceOf(ErrorHandler::class, $handler);
    }

    public function testCreateFlysystemExceptionCreatesUnableToReadFileForReadOperations(): void
    {
        $exception = $this->errorHandler->createFlysystemException(
            'read',
            'Test message',
            '/test/path'
        );

        $this->assertInstanceOf(UnableToReadFile::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testCreateFlysystemExceptionCreatesUnableToWriteFileForWriteOperations(): void
    {
        $exception = $this->errorHandler->createFlysystemException(
            'write',
            'Test message',
            '/test/path'
        );

        $this->assertInstanceOf(UnableToWriteFile::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testCreateFlysystemExceptionCreatesCtFileExceptionForUnknownOperations(): void
    {
        $exception = $this->errorHandler->createFlysystemException(
            'unknown',
            'Test message',
            '/test/path'
        );

        $this->assertInstanceOf(CtFileException::class, $exception);
        $this->assertStringContainsString('Test message', $exception->getMessage());
    }

    public function testLogErrorLogsErrorWithAppropriateContext(): void
    {
        $error = new \RuntimeException('Test error');
        $context = ['operation' => 'read', 'path' => '/test/path'];

        $this->logger->shouldReceive('log')
            ->once()
            ->with(
                'error',
                'ctFile operation failed: Test error',
                Mockery::on(function ($logContext) {
                    return isset($logContext['exception_class']) &&
                           $logContext['exception_class'] === \RuntimeException::class;
                })
            );

        $this->errorHandler->logError($error, $context);
    }

    public function testLogErrorUsesWarningLevelForCtFileOperationException(): void
    {
        $error = CtFileOperationException::uploadFailed('/local', '/remote');

        $this->logger->shouldReceive('log')
            ->once()
            ->with('warning', Mockery::any(), Mockery::any());

        $this->errorHandler->logError($error);
    }

    public function testHandleCtFileErrorLogsErrorAndThrowsConvertedFlysystemException(): void
    {
        $originalError = new CtFileOperationException('Upload failed', 'upload', '/test/file.txt');

        $this->logger->shouldReceive('log')
            ->once()
            ->with('warning', Mockery::any(), Mockery::any());

        $this->expectException(UnableToWriteFile::class);
        $this->errorHandler->handleCtFileError($originalError, 'write', '/test/file.txt');
    }

    public function testHandleCtFileErrorConvertsGenericExceptionsToAppropriateFlysystemException(): void
    {
        $originalError = new \RuntimeException('Generic error');

        $this->logger->shouldReceive('log')->once();

        $this->expectException(UnableToDeleteFile::class);
        $this->errorHandler->handleCtFileError($originalError, 'delete', '/test/file.txt');
    }

    public function testHandleCtFileErrorIncludesOriginalErrorAsPreviousException(): void
    {
        $originalError = new CtFileOperationException('Test error', 'read', '/test/file.txt');

        $this->logger->shouldReceive('log')->once();

        try {
            $this->errorHandler->handleCtFileError($originalError, 'read', '/test/file.txt');
        } catch (\Throwable $e) {
            $this->assertSame($originalError, $e->getPrevious());
        }
    }
}
