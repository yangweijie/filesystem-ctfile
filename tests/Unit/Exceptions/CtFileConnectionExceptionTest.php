<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Tests\Unit\Exceptions;

use YangWeijie\FilesystemCtfile\Exceptions\CtFileConnectionException;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileException;
use YangWeijie\FilesystemCtfile\Tests\TestCase;

class CtFileConnectionExceptionTest extends TestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new CtFileConnectionException('Test message');

        $this->assertInstanceOf(CtFileException::class, $exception);
        $this->assertInstanceOf(CtFileConnectionException::class, $exception);
    }

    public function testConnectionFailed(): void
    {
        $previous = new \Exception('Network error');
        $exception = CtFileConnectionException::connectionFailed('example.com', 21, $previous);

        $this->assertStringContainsString('Failed to establish connection to ctFile server', $exception->getMessage());
        $this->assertSame('connect', $exception->getOperation());
        $this->assertSame('example.com:21', $exception->getPath());
        $this->assertSame(['host' => 'example.com', 'port' => 21], $exception->getContext());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testAuthenticationFailed(): void
    {
        $previous = new \Exception('Auth error');
        $exception = CtFileConnectionException::authenticationFailed('testuser', $previous);

        $this->assertStringContainsString('Authentication failed for ctFile server', $exception->getMessage());
        $this->assertSame('authenticate', $exception->getOperation());
        $this->assertSame(['username' => 'testuser'], $exception->getContext());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConnectionTimeout(): void
    {
        $exception = CtFileConnectionException::connectionTimeout('example.com', 21, 30);

        $this->assertStringContainsString('Connection to ctFile server timed out after 30 seconds', $exception->getMessage());
        $this->assertSame('connect', $exception->getOperation());
        $this->assertSame('example.com:21', $exception->getPath());
        $this->assertSame(['host' => 'example.com', 'port' => 21, 'timeout' => 30], $exception->getContext());
    }

    public function testConnectionLost(): void
    {
        $previous = new \Exception('Connection lost');
        $exception = CtFileConnectionException::connectionLost($previous);

        $this->assertStringContainsString('Connection to ctFile server was lost', $exception->getMessage());
        $this->assertSame('maintain_connection', $exception->getOperation());
        $this->assertSame('', $exception->getPath());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testSslError(): void
    {
        $exception = CtFileConnectionException::sslError('secure.example.com', 990);

        $this->assertStringContainsString('SSL/TLS connection error with ctFile server', $exception->getMessage());
        $this->assertSame('ssl_connect', $exception->getOperation());
        $this->assertSame('secure.example.com:990', $exception->getPath());
        $this->assertSame(['host' => 'secure.example.com', 'port' => 990], $exception->getContext());
    }

    public function testNetworkError(): void
    {
        $previous = new \Exception('Socket error');
        $exception = CtFileConnectionException::networkError('Socket timeout', $previous);

        $this->assertStringContainsString('Network error: Socket timeout', $exception->getMessage());
        $this->assertSame('network_operation', $exception->getOperation());
        $this->assertSame('', $exception->getPath());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
