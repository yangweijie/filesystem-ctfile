<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Tests\Unit\Exceptions;

use YangWeijie\FilesystemCtfile\Exceptions\CtFileAuthenticationException;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileException;
use YangWeijie\FilesystemCtfile\Tests\TestCase;

class CtFileAuthenticationExceptionTest extends TestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new CtFileAuthenticationException('Test message');

        $this->assertInstanceOf(CtFileException::class, $exception);
        $this->assertInstanceOf(CtFileAuthenticationException::class, $exception);
    }

    public function testInvalidCredentials(): void
    {
        $previous = new \Exception('Auth failed');
        $exception = CtFileAuthenticationException::invalidCredentials('testuser', $previous);

        $this->assertStringContainsString('Invalid credentials provided for ctFile authentication', $exception->getMessage());
        $this->assertSame('authenticate', $exception->getOperation());
        $this->assertSame(['username' => 'testuser'], $exception->getContext());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testCredentialsExpired(): void
    {
        $exception = CtFileAuthenticationException::credentialsExpired('testuser');

        $this->assertStringContainsString('Credentials have expired for ctFile authentication', $exception->getMessage());
        $this->assertSame('authenticate', $exception->getOperation());
        $this->assertSame(['username' => 'testuser'], $exception->getContext());
    }

    public function testAccountLocked(): void
    {
        $exception = CtFileAuthenticationException::accountLocked('testuser');

        $this->assertStringContainsString('Account is locked for ctFile authentication', $exception->getMessage());
        $this->assertSame('authenticate', $exception->getOperation());
        $this->assertSame(['username' => 'testuser'], $exception->getContext());
    }

    public function testPermissionDenied(): void
    {
        $exception = CtFileAuthenticationException::permissionDenied('delete', '/protected/file.txt');

        $this->assertStringContainsString('Permission denied for ctFile operation', $exception->getMessage());
        $this->assertSame('delete', $exception->getOperation());
        $this->assertSame('/protected/file.txt', $exception->getPath());
        $this->assertSame(['required_permission' => 'delete'], $exception->getContext());
    }

    public function testSessionExpired(): void
    {
        $previous = new \Exception('Session timeout');
        $exception = CtFileAuthenticationException::sessionExpired($previous);

        $this->assertStringContainsString('ctFile session has expired', $exception->getMessage());
        $this->assertSame('session_check', $exception->getOperation());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testAuthenticationRequired(): void
    {
        $exception = CtFileAuthenticationException::authenticationRequired('upload');

        $this->assertStringContainsString('Authentication required for ctFile operation', $exception->getMessage());
        $this->assertSame('upload', $exception->getOperation());
    }

    public function testTwoFactorRequired(): void
    {
        $exception = CtFileAuthenticationException::twoFactorRequired('testuser');

        $this->assertStringContainsString('Two-factor authentication required for ctFile access', $exception->getMessage());
        $this->assertSame('authenticate', $exception->getOperation());
        $this->assertSame(['username' => 'testuser'], $exception->getContext());
    }
}
