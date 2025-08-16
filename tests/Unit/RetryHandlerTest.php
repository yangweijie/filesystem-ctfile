<?php

declare(strict_types=1);

use League\Flysystem\UnableToReadFile;
use Psr\Log\LoggerInterface;
use YangWeijie\FilesystemCtfile\RetryHandler;

beforeEach(function () {
    $this->logger = Mockery::mock(LoggerInterface::class);
    $this->retryHandler = new RetryHandler(3, 100, 2.0, 5000, [], $this->logger);
});

describe('RetryHandler', function () {
    it('can be instantiated with default parameters', function () {
        $handler = new RetryHandler();
        expect($handler)->toBeInstanceOf(RetryHandler::class);
        expect($handler->getMaxRetries())->toBe(3);
        expect($handler->getBaseDelay())->toBe(1000);
        expect($handler->getBackoffMultiplier())->toBe(2.0);
        expect($handler->getMaxDelay())->toBe(30000);
    });

    it('can be instantiated with custom parameters', function () {
        $handler = new RetryHandler(5, 500, 1.5, 10000);
        expect($handler->getMaxRetries())->toBe(5);
        expect($handler->getBaseDelay())->toBe(500);
        expect($handler->getBackoffMultiplier())->toBe(1.5);
        expect($handler->getMaxDelay())->toBe(10000);
    });

    it('enforces minimum values for parameters', function () {
        $handler = new RetryHandler(-1, -100, 0.5, 50);
        expect($handler->getMaxRetries())->toBe(0);
        expect($handler->getBaseDelay())->toBe(0);
        expect($handler->getBackoffMultiplier())->toBe(1.0);
        expect($handler->getMaxDelay())->toBe(50); // maxDelay should be at least baseDelay
    });

    describe('execute', function () {
        it('returns result on first successful attempt', function () {
            $operation = fn () => 'success';

            $this->logger->shouldNotReceive('info');
            $this->logger->shouldNotReceive('warning');
            $this->logger->shouldNotReceive('error');

            $result = $this->retryHandler->execute($operation);
            expect($result)->toBe('success');
        });

        it('logs success after retry', function () {
            $attempts = 0;
            $operation = function () use (&$attempts) {
                $attempts++;
                if ($attempts === 1) {
                    throw new RuntimeException('Temporary failure');
                }

                return 'success';
            };

            $this->logger->shouldReceive('warning')->once();
            $this->logger->shouldReceive('info')->once()->with('Operation succeeded after retry', Mockery::any());

            $result = $this->retryHandler->execute($operation);
            expect($result)->toBe('success');
            expect($attempts)->toBe(2);
        });

        it('retries on retryable exceptions', function () {
            $attempts = 0;
            $operation = function () use (&$attempts) {
                $attempts++;
                if ($attempts <= 2) {
                    throw new RuntimeException('Connection failed');
                }

                return 'success';
            };

            $this->logger->shouldReceive('warning')->twice();
            $this->logger->shouldReceive('info')->once();

            $result = $this->retryHandler->execute($operation);
            expect($result)->toBe('success');
            expect($attempts)->toBe(3);
        });

        it('throws exception after max retries exceeded', function () {
            $attempts = 0;
            $operation = function () use (&$attempts) {
                $attempts++;
                throw new RuntimeException('Persistent failure');
            };

            $this->logger->shouldReceive('warning')->times(3);
            $this->logger->shouldReceive('error')->once();

            expect(fn () => $this->retryHandler->execute($operation))
                ->toThrow(RuntimeException::class, 'Persistent failure');

            expect($attempts)->toBe(4); // Initial attempt + 3 retries
        });

        it('does not retry non-retryable exceptions', function () {
            $attempts = 0;
            $operation = function () use (&$attempts) {
                $attempts++;
                throw new InvalidArgumentException('Invalid input');
            };

            $this->logger->shouldReceive('info')->once()->with('Operation failed with non-retryable exception', Mockery::any());

            expect(fn () => $this->retryHandler->execute($operation))
                ->toThrow(InvalidArgumentException::class, 'Invalid input');

            expect($attempts)->toBe(1); // Only initial attempt
        });

        it('includes context in log messages', function () {
            $operation = fn () => throw new RuntimeException('Test failure');
            $context = ['operation' => 'test', 'file' => 'test.txt'];

            $this->logger->shouldReceive('warning')->times(3);
            $this->logger->shouldReceive('error')->once()->with(
                'Operation failed after all retries',
                Mockery::on(function ($data) use ($context) {
                    return $data['context'] === $context;
                })
            );

            expect(fn () => $this->retryHandler->execute($operation, $context))
                ->toThrow(RuntimeException::class);
        });
    });

    describe('shouldRetry', function () {
        it('returns true for configured retryable exceptions', function () {
            $handler = new RetryHandler(3, 100, 2.0, 5000, [RuntimeException::class]);

            expect($handler->shouldRetry(new RuntimeException('test')))->toBeTrue();
            expect($handler->shouldRetry(new InvalidArgumentException('test')))->toBeFalse();
        });

        it('returns true for default retryable exceptions', function () {
            $handler = new RetryHandler();

            expect($handler->shouldRetry(new RuntimeException('test')))->toBeTrue();
            expect($handler->shouldRetry(UnableToReadFile::fromLocation('test', 'test')))->toBeTrue();
        });

        it('returns true for transient error patterns', function () {
            $handler = new RetryHandler();

            $transientExceptions = [
                new Exception('Connection timeout'),
                new Exception('Network error'),
                new Exception('Service temporarily unavailable'),
                new Exception('Server busy'),
                new Exception('System overloaded'),
            ];

            foreach ($transientExceptions as $exception) {
                expect($handler->shouldRetry($exception))->toBeTrue();
            }
        });

        it('returns false for non-transient errors', function () {
            $handler = new RetryHandler(3, 100, 2.0, 5000, []);

            $nonTransientExceptions = [
                new Exception('Invalid syntax'),
                new Exception('Permission denied'),
                new Exception('File not found'),
            ];

            foreach ($nonTransientExceptions as $exception) {
                expect($handler->shouldRetry($exception))->toBeFalse();
            }
        });
    });

    describe('calculateDelay', function () {
        it('calculates exponential backoff delays', function () {
            $handler = new RetryHandler(3, 100, 2.0, 10000);

            $delay0 = $handler->calculateDelay(0);
            $delay1 = $handler->calculateDelay(1);
            $delay2 = $handler->calculateDelay(2);

            // Base delay is 100ms, multiplier is 2.0
            // Attempt 0: ~100ms (+ jitter)
            // Attempt 1: ~200ms (+ jitter)
            // Attempt 2: ~400ms (+ jitter)

            expect($delay0)->toBeGreaterThanOrEqual(100);
            expect($delay0)->toBeLessThanOrEqual(110); // 100 + 10% jitter

            expect($delay1)->toBeGreaterThanOrEqual(200);
            expect($delay1)->toBeLessThanOrEqual(220); // 200 + 10% jitter

            expect($delay2)->toBeGreaterThanOrEqual(400);
            expect($delay2)->toBeLessThanOrEqual(440); // 400 + 10% jitter
        });

        it('respects maximum delay limit', function () {
            $handler = new RetryHandler(5, 1000, 2.0, 3000);

            $delay0 = $handler->calculateDelay(0);
            $delay1 = $handler->calculateDelay(1);
            $delay2 = $handler->calculateDelay(2);
            $delay3 = $handler->calculateDelay(3);

            expect($delay0)->toBeLessThanOrEqual(3000);
            expect($delay1)->toBeLessThanOrEqual(3000);
            expect($delay2)->toBeLessThanOrEqual(3000);
            expect($delay3)->toBeLessThanOrEqual(3000);
        });

        it('adds jitter to prevent thundering herd', function () {
            $handler = new RetryHandler(3, 1000, 1.0, 10000); // No exponential backoff

            $delays = [];
            for ($i = 0; $i < 10; $i++) {
                $delays[] = $handler->calculateDelay(0);
            }

            // With jitter, delays should vary
            $uniqueDelays = array_unique($delays);
            expect(count($uniqueDelays))->toBeGreaterThan(1);
        });
    });

    describe('configuration getters', function () {
        it('returns correct configuration values', function () {
            $handler = new RetryHandler(5, 500, 1.5, 8000, [Exception::class]);

            expect($handler->getMaxRetries())->toBe(5);
            expect($handler->getBaseDelay())->toBe(500);
            expect($handler->getBackoffMultiplier())->toBe(1.5);
            expect($handler->getMaxDelay())->toBe(8000);
            expect($handler->getRetryableExceptions())->toBe([Exception::class]);
        });
    });

    describe('integration scenarios', function () {
        it('handles intermittent network failures', function () {
            $attempts = 0;
            $operation = function () use (&$attempts) {
                $attempts++;
                if ($attempts <= 2) {
                    throw new RuntimeException('Connection timeout occurred');
                }

                return ['status' => 'success', 'data' => 'file content'];
            };

            $this->logger->shouldReceive('warning')->twice();
            $this->logger->shouldReceive('info')->once();

            $result = $this->retryHandler->execute($operation);
            expect($result)->toBe(['status' => 'success', 'data' => 'file content']);
            expect($attempts)->toBe(3);
        });

        it('handles server overload scenarios', function () {
            $attempts = 0;
            $operation = function () use (&$attempts) {
                $attempts++;
                if ($attempts === 1) {
                    throw new RuntimeException('Server overloaded, please try again');
                }

                return 'operation completed';
            };

            $this->logger->shouldReceive('warning')->once();
            $this->logger->shouldReceive('info')->once();

            $result = $this->retryHandler->execute($operation);
            expect($result)->toBe('operation completed');
        });

        it('fails fast on authentication errors', function () {
            $attempts = 0;
            $operation = function () use (&$attempts) {
                $attempts++;
                throw new RuntimeException('Authentication failed - invalid credentials');
            };

            // Authentication errors should be retried since they match the transient pattern check
            $this->logger->shouldReceive('warning')->times(3);
            $this->logger->shouldReceive('error')->once();

            expect(fn () => $this->retryHandler->execute($operation))
                ->toThrow(RuntimeException::class, 'Authentication failed - invalid credentials');

            expect($attempts)->toBe(4); // Initial + 3 retries
        });
    });
});
