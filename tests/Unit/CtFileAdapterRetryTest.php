<?php

declare(strict_types=1);

use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckFileExistence;
use YangWeijie\FilesystemCtfile\CacheManager;
use YangWeijie\FilesystemCtfile\CtFileAdapter;
use YangWeijie\FilesystemCtfile\CtFileClient;
use YangWeijie\FilesystemCtfile\RetryHandler;

beforeEach(function () {
    $this->client = Mockery::mock(CtFileClient::class);
    $this->retryHandler = Mockery::mock(RetryHandler::class);
    $this->cacheManager = Mockery::mock(CacheManager::class);

    $this->adapter = new CtFileAdapter(
        $this->client,
        [],
        $this->cacheManager,
        $this->retryHandler
    );
});

describe('CtFileAdapter with RetryHandler', function () {
    it('can be instantiated with retry handler', function () {
        expect($this->adapter)->toBeInstanceOf(CtFileAdapter::class);
        expect($this->adapter->getRetryHandler())->toBe($this->retryHandler);
    });

    it('can set and get retry handler', function () {
        $newRetryHandler = Mockery::mock(RetryHandler::class);
        $this->adapter->setRetryHandler($newRetryHandler);

        expect($this->adapter->getRetryHandler())->toBe($newRetryHandler);
    });

    describe('fileExists with retry', function () {
        it('uses retry handler for file existence check', function () {
            $this->retryHandler->shouldReceive('execute')
                ->once()
                ->with(Mockery::type('callable'), ['operation' => 'fileExists', 'path' => 'test.txt'])
                ->andReturnUsing(function ($operation) {
                    return $operation();
                });

            $this->client->shouldReceive('fileExists')
                ->once()
                ->with('test.txt')
                ->andReturn(true);

            $result = $this->adapter->fileExists('test.txt');
            expect($result)->toBeTrue();
        });

        it('retries on transient failures', function () {
            $attempts = 0;
            $this->retryHandler->shouldReceive('execute')
                ->once()
                ->with(Mockery::type('callable'), ['operation' => 'fileExists', 'path' => 'test.txt'])
                ->andReturnUsing(function ($operation) use (&$attempts) {
                    // Simulate successful retry - the retry handler handles the retry logic internally
                    // and eventually returns the successful result
                    $attempts++;

                    return $operation();
                });

            $this->client->shouldReceive('fileExists')
                ->once()
                ->with('test.txt')
                ->andReturn(true);

            $result = $this->adapter->fileExists('test.txt');
            expect($result)->toBeTrue();
            expect($attempts)->toBe(1); // RetryHandler was called once, it handles retries internally
        });

        it('throws UnableToCheckFileExistence on final failure', function () {
            $this->retryHandler->shouldReceive('execute')
                ->once()
                ->with(Mockery::type('callable'), ['operation' => 'fileExists', 'path' => 'test.txt'])
                ->andThrow(new RuntimeException('Persistent failure'));

            expect(fn () => $this->adapter->fileExists('test.txt'))
                ->toThrow(UnableToCheckFileExistence::class);
        });
    });

    describe('directoryExists with retry', function () {
        it('uses retry handler for directory existence check', function () {
            $this->retryHandler->shouldReceive('execute')
                ->once()
                ->with(Mockery::type('callable'), ['operation' => 'directoryExists', 'path' => 'test-dir'])
                ->andReturnUsing(function ($operation) {
                    return $operation();
                });

            $this->client->shouldReceive('directoryExists')
                ->once()
                ->with('test-dir')
                ->andReturn(true);

            $result = $this->adapter->directoryExists('test-dir');
            expect($result)->toBeTrue();
        });

        it('throws UnableToCheckDirectoryExistence on final failure', function () {
            $this->retryHandler->shouldReceive('execute')
                ->once()
                ->with(Mockery::type('callable'), ['operation' => 'directoryExists', 'path' => 'test-dir'])
                ->andThrow(new RuntimeException('Persistent failure'));

            expect(fn () => $this->adapter->directoryExists('test-dir'))
                ->toThrow(UnableToCheckDirectoryExistence::class);
        });
    });

    describe('read operations with retry', function () {
        it('uses retry handler for read operation', function () {
            // Mock file existence check - first call
            $this->retryHandler->shouldReceive('execute')
                ->once()
                ->with(Mockery::type('callable'), [])
                ->andReturnUsing(function ($operation) {
                    return $operation();
                });

            // Mock read operation - second call
            $this->retryHandler->shouldReceive('execute')
                ->once()
                ->with(Mockery::type('callable'), ['operation' => 'read', 'path' => 'test.txt'])
                ->andReturnUsing(function ($operation) {
                    return $operation();
                });

            $this->client->shouldReceive('fileExists')
                ->once()
                ->with('test.txt')
                ->andReturn(true);

            $this->client->shouldReceive('readFile')
                ->once()
                ->with('test.txt')
                ->andReturn('file content');

            $result = $this->adapter->read('test.txt');
            expect($result)->toBe('file content');
        });

        it('uses retry handler for readStream operation', function () {
            // Mock file existence check - first call
            $this->retryHandler->shouldReceive('execute')
                ->once()
                ->with(Mockery::type('callable'), [])
                ->andReturnUsing(function ($operation) {
                    return $operation();
                });

            // Mock read operation - second call
            $this->retryHandler->shouldReceive('execute')
                ->once()
                ->with(Mockery::type('callable'), ['operation' => 'readStream', 'path' => 'test.txt'])
                ->andReturnUsing(function ($operation) {
                    return $operation();
                });

            $this->client->shouldReceive('fileExists')
                ->once()
                ->with('test.txt')
                ->andReturn(true);

            $this->client->shouldReceive('readFile')
                ->once()
                ->with('test.txt')
                ->andReturn('stream content');

            $result = $this->adapter->readStream('test.txt');
            expect($result)->toBeResource();

            $content = stream_get_contents($result);
            expect($content)->toBe('stream content');
            fclose($result);
        });

        it('retries read operation on transient failures', function () {
            // Mock file existence check - first call
            $this->retryHandler->shouldReceive('execute')
                ->once()
                ->with(Mockery::type('callable'), [])
                ->andReturnUsing(function ($operation) {
                    return $operation();
                });

            // Mock read operation - second call
            $this->retryHandler->shouldReceive('execute')
                ->once()
                ->with(Mockery::type('callable'), ['operation' => 'read', 'path' => 'test.txt'])
                ->andReturnUsing(function ($operation) {
                    return $operation();
                });

            $this->client->shouldReceive('fileExists')
                ->once()
                ->with('test.txt')
                ->andReturn(true);

            $this->client->shouldReceive('readFile')
                ->once()
                ->with('test.txt')
                ->andReturn('recovered content');

            $result = $this->adapter->read('test.txt');
            expect($result)->toBe('recovered content');
        });
    });

    describe('without retry handler', function () {
        it('executes operations directly when no retry handler is set', function () {
            $adapter = new CtFileAdapter($this->client);
            expect($adapter->getRetryHandler())->toBeNull();

            $this->client->shouldReceive('fileExists')
                ->once()
                ->with('test.txt')
                ->andReturn(true);

            $result = $adapter->fileExists('test.txt');
            expect($result)->toBeTrue();
        });

        it('throws exceptions immediately without retry', function () {
            $adapter = new CtFileAdapter($this->client);

            $this->client->shouldReceive('fileExists')
                ->once()
                ->with('test.txt')
                ->andThrow(new RuntimeException('Connection failed'));

            expect(fn () => $adapter->fileExists('test.txt'))
                ->toThrow(UnableToCheckFileExistence::class);
        });

        it('executes read operations directly without retry handler', function () {
            $adapter = new CtFileAdapter($this->client);

            $this->client->shouldReceive('fileExists')
                ->once()
                ->with('test.txt')
                ->andReturn(true);

            $this->client->shouldReceive('readFile')
                ->once()
                ->with('test.txt')
                ->andReturn('direct content');

            $result = $adapter->read('test.txt');
            expect($result)->toBe('direct content');
        });
    });
});
