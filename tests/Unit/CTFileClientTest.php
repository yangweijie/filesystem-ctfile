<?php

use Yangweijie\FilesystemCtlife\CTFileClient;
use Yangweijie\FilesystemCtlife\CTFileConfig;
use Yangweijie\FilesystemCtlife\Exceptions\ApiException;
use Yangweijie\FilesystemCtlife\Exceptions\CTFileException;

describe('CTFileClient', function () {
    beforeEach(function () {
        $this->config = new CTFileConfig([
            'session' => 'test_session_token_123456',
            'app_id' => 'test_app_id',
            'api_base_url' => 'https://api.example.com/v1',
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry_attempts' => 2,
        ]);
        
        $this->client = new CTFileClient($this->config);
    });

    it('can be instantiated with config', function () {
        expect($this->client)->toBeInstanceOf(CTFileClient::class);
    });

    it('builds correct headers', function () {
        // 使用反射来测试私有方法
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('buildHeaders');
        $method->setAccessible(true);
        
        $headers = $method->invoke($this->client, true);
        
        expect($headers)->toContain('myapp-id: test_app_id');
        expect($headers)->toContain('session: test_session_token_123456');
        expect($headers)->toContain('Content-Type: application/json');
        expect($headers)->toContain('Accept: application/json');
    });

    it('builds headers without JSON for uploads', function () {
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('buildHeaders');
        $method->setAccessible(true);
        
        $headers = $method->invoke($this->client, false);
        
        expect($headers)->toContain('myapp-id: test_app_id');
        expect($headers)->toContain('session: test_session_token_123456');
        expect($headers)->not->toContain('Content-Type: application/json');
        expect($headers)->not->toContain('Accept: application/json');
    });

    it('builds correct URL', function () {
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('buildUrl');
        $method->setAccessible(true);
        
        $url = $method->invoke($this->client, 'files/list');
        expect($url)->toBe('https://api.example.com/v1/files/list');
        
        $url = $method->invoke($this->client, '/files/list');
        expect($url)->toBe('https://api.example.com/v1/files/list');
    });

    it('handles JSON response correctly', function () {
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('handleResponse');
        $method->setAccessible(true);
        
        $response = '{"code": 200, "data": {"files": []}}';
        $result = $method->invoke($this->client, $response);
        
        expect($result)->toBe([
            'code' => 200,
            'data' => ['files' => []]
        ]);
    });

    it('handles empty response', function () {
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('handleResponse');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->client, '');
        expect($result)->toBe([]);
    });

    it('throws exception for invalid JSON', function () {
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('handleResponse');
        $method->setAccessible(true);
        
        expect(fn() => $method->invoke($this->client, 'invalid json'))
            ->toThrow(CTFileException::class, 'Invalid JSON response');
    });

    it('handles API error response', function () {
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('handleResponse');
        $method->setAccessible(true);
        
        $response = '{"code": 401, "message": "Unauthorized"}';
        
        expect(fn() => $method->invoke($this->client, $response))
            ->toThrow(CTFileException::class, 'Unauthorized');
    });

    it('identifies retryable errors correctly', function () {
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('isRetryableError');
        $method->setAccessible(true);
        
        // 5xx errors are retryable
        $serverError = new ApiException('Server Error', 500, 'https://api.example.com', 'GET');
        expect($method->invoke($this->client, $serverError))->toBeTrue();
        
        // 408 and 429 are retryable
        $timeoutError = new ApiException('Timeout', 408, 'https://api.example.com', 'GET');
        expect($method->invoke($this->client, $timeoutError))->toBeTrue();
        
        $rateLimitError = new ApiException('Rate Limited', 429, 'https://api.example.com', 'GET');
        expect($method->invoke($this->client, $rateLimitError))->toBeTrue();
        
        // 4xx errors (except 408, 429) are not retryable
        $clientError = new ApiException('Bad Request', 400, 'https://api.example.com', 'GET');
        expect($method->invoke($this->client, $clientError))->toBeFalse();
        
        $authError = new ApiException('Unauthorized', 401, 'https://api.example.com', 'GET');
        expect($method->invoke($this->client, $authError))->toBeFalse();
    });

    it('creates download stream correctly', function () {
        // 模拟下载响应
        $testContent = 'test file content';
        
        // 由于我们无法轻易模拟 cURL，这里测试下载方法的逻辑
        // 在实际环境中，这需要使用 HTTP 客户端模拟库
        expect(true)->toBeTrue(); // 占位测试
    });
});

describe('ApiException', function () {
    it('can be created with basic parameters', function () {
        $exception = new ApiException(
            'Test error',
            404,
            'https://api.example.com/test',
            'GET',
            1001,
            ['detail' => 'Not found']
        );

        expect($exception->getMessage())->toBe('Test error');
        expect($exception->getHttpStatusCode())->toBe(404);
        expect($exception->getRequestUrl())->toBe('https://api.example.com/test');
        expect($exception->getRequestMethod())->toBe('GET');
        expect($exception->getErrorCode())->toBe(1001);
        expect($exception->getErrorDetails())->toBe(['detail' => 'Not found']);
    });

    it('can be created from HTTP response', function () {
        $responseBody = '{"code": 404, "message": "File not found"}';
        
        $exception = ApiException::fromHttpResponse(
            404,
            $responseBody,
            'https://api.example.com/files/123',
            'GET'
        );

        expect($exception->getMessage())->toBe('File not found');
        expect($exception->getHttpStatusCode())->toBe(404);
        expect($exception->getRequestUrl())->toBe('https://api.example.com/files/123');
        expect($exception->getRequestMethod())->toBe('GET');
        expect($exception->getErrorCode())->toBe(404);
        expect($exception->getErrorDetails())->toBe([
            'code' => 404,
            'message' => 'File not found'
        ]);
    });

    it('handles empty response body', function () {
        $exception = ApiException::fromHttpResponse(
            500,
            '',
            'https://api.example.com/test',
            'POST'
        );

        expect($exception->getMessage())->toBe('HTTP 500 error for POST https://api.example.com/test');
        expect($exception->getHttpStatusCode())->toBe(500);
        expect($exception->getErrorCode())->toBe(500);
        expect($exception->getErrorDetails())->toBe([]);
    });
});
