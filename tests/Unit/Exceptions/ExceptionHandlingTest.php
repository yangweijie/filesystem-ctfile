<?php

use Yangweijie\FilesystemCtlife\Exceptions\AuthenticationException;
use Yangweijie\FilesystemCtlife\Exceptions\NetworkException;
use Yangweijie\FilesystemCtlife\Exceptions\RateLimitException;
use Yangweijie\FilesystemCtlife\Exceptions\CTFileException;

describe('Exception Handling', function () {
    describe('AuthenticationException', function () {
        it('can be instantiated with default message', function () {
            $exception = new AuthenticationException();
            
            expect($exception->getMessage())->toBe('Authentication failed');
            expect($exception->getCode())->toBe(401);
        });

        it('can create invalid session exception', function () {
            $session = 'invalid_session_123';
            $exception = AuthenticationException::invalidSession($session);
            
            expect($exception->getMessage())->toContain($session);
            expect($exception->getCode())->toBe(401);
        });

        it('can create invalid app ID exception', function () {
            $appId = 'invalid_app_id';
            $exception = AuthenticationException::invalidAppId($appId);
            
            expect($exception->getMessage())->toContain($appId);
            expect($exception->getCode())->toBe(401);
        });

        it('can create session expired exception', function () {
            $exception = AuthenticationException::sessionExpired();
            
            expect($exception->getMessage())->toContain('expired');
            expect($exception->getCode())->toBe(401);
        });

        it('can create insufficient permissions exception', function () {
            $operation = 'file upload';
            $exception = AuthenticationException::insufficientPermissions($operation);
            
            expect($exception->getMessage())->toContain($operation);
            expect($exception->getCode())->toBe(403);
        });

        it('can create account disabled exception', function () {
            $exception = AuthenticationException::accountDisabled();
            
            expect($exception->getMessage())->toContain('disabled');
            expect($exception->getCode())->toBe(403);
        });
    });

    describe('NetworkException', function () {
        it('can be instantiated with default message', function () {
            $exception = new NetworkException();
            
            expect($exception->getMessage())->toBe('Network error occurred');
            expect($exception->getCode())->toBe(0);
        });

        it('can create connection timeout exception', function () {
            $url = 'https://api.ctfile.com/test';
            $timeout = 30;
            $exception = NetworkException::connectionTimeout($url, $timeout);
            
            expect($exception->getMessage())->toContain($url);
            expect($exception->getMessage())->toContain((string) $timeout);
            expect($exception->getCode())->toBe(408);
        });

        it('can create connection failed exception', function () {
            $url = 'https://api.ctfile.com/test';
            $reason = 'Connection refused';
            $exception = NetworkException::connectionFailed($url, $reason);
            
            expect($exception->getMessage())->toContain($url);
            expect($exception->getMessage())->toContain($reason);
        });

        it('can create DNS resolution failed exception', function () {
            $hostname = 'invalid.ctfile.com';
            $exception = NetworkException::dnsResolutionFailed($hostname);
            
            expect($exception->getMessage())->toContain($hostname);
        });

        it('can create SSL verification failed exception', function () {
            $url = 'https://api.ctfile.com/test';
            $exception = NetworkException::sslVerificationFailed($url);
            
            expect($exception->getMessage())->toContain($url);
            expect($exception->getMessage())->toContain('SSL');
        });

        it('can create network unreachable exception', function () {
            $url = 'https://api.ctfile.com/test';
            $exception = NetworkException::networkUnreachable($url);
            
            expect($exception->getMessage())->toContain($url);
            expect($exception->getMessage())->toContain('unreachable');
        });

        it('can create response read failed exception', function () {
            $reason = 'Connection reset by peer';
            $exception = NetworkException::responseReadFailed($reason);
            
            expect($exception->getMessage())->toContain('read response');
            expect($exception->getMessage())->toContain($reason);
        });
    });

    describe('RateLimitException', function () {
        it('can be instantiated with default values', function () {
            $exception = new RateLimitException();
            
            expect($exception->getMessage())->toBe('Rate limit exceeded');
            expect($exception->getRetryAfter())->toBe(60);
            expect($exception->getCode())->toBe(429);
        });

        it('can create request rate limit exception', function () {
            $retryAfter = 120;
            $exception = RateLimitException::requestRateLimit($retryAfter);
            
            expect($exception->getMessage())->toContain('Request rate limit');
            expect($exception->getRetryAfter())->toBe($retryAfter);
            expect($exception->getCode())->toBe(429);
        });

        it('can create upload rate limit exception', function () {
            $retryAfter = 300;
            $exception = RateLimitException::uploadRateLimit($retryAfter);
            
            expect($exception->getMessage())->toContain('Upload rate limit');
            expect($exception->getRetryAfter())->toBe($retryAfter);
        });

        it('can create download rate limit exception', function () {
            $retryAfter = 60;
            $exception = RateLimitException::downloadRateLimit($retryAfter);
            
            expect($exception->getMessage())->toContain('Download rate limit');
            expect($exception->getRetryAfter())->toBe($retryAfter);
        });

        it('can create storage quota exceeded exception', function () {
            $usedSpace = 1024 * 1024 * 1024; // 1GB
            $totalSpace = 2 * 1024 * 1024 * 1024; // 2GB
            $exception = RateLimitException::storageQuotaExceeded($usedSpace, $totalSpace);
            
            expect($exception->getMessage())->toContain('Storage quota exceeded');
            expect($exception->getMessage())->toContain('1024MB');
            expect($exception->getMessage())->toContain('2048MB');
            expect($exception->getCode())->toBe(507);
        });

        it('can create concurrent connection limit exception', function () {
            $maxConnections = 5;
            $exception = RateLimitException::concurrentConnectionLimit($maxConnections);
            
            expect($exception->getMessage())->toContain('Concurrent connection limit');
            expect($exception->getMessage())->toContain((string) $maxConnections);
            expect($exception->getRetryAfter())->toBe(60);
        });
    });

    describe('Exception Inheritance', function () {
        it('all custom exceptions extend CTFileException', function () {
            expect(new AuthenticationException())->toBeInstanceOf(CTFileException::class);
            expect(new NetworkException())->toBeInstanceOf(CTFileException::class);
            expect(new RateLimitException())->toBeInstanceOf(CTFileException::class);
        });

        it('CTFileException extends base Exception', function () {
            expect(new CTFileException())->toBeInstanceOf(\Exception::class);
            expect(new AuthenticationException())->toBeInstanceOf(\Exception::class);
            expect(new NetworkException())->toBeInstanceOf(\Exception::class);
            expect(new RateLimitException())->toBeInstanceOf(\Exception::class);
        });
    });
});
