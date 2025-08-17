<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Handles retry logic for failed operations with configurable attempts and delays.
 */
class RetryHandler
{
    private int $maxRetries;

    private int $baseDelay;

    private float $backoffMultiplier;

    private int $maxDelay;

    private array $retryableExceptions;

    private LoggerInterface $logger;

    public function __construct(
        int $maxRetries = 3,
        int $baseDelay = 1000,
        float $backoffMultiplier = 2.0,
        int $maxDelay = 30000,
        array $retryableExceptions = [],
        ?LoggerInterface $logger = null
    ) {
        $this->maxRetries = max(0, $maxRetries);
        $this->baseDelay = max(0, $baseDelay);
        $this->backoffMultiplier = max(1.0, $backoffMultiplier);
        $this->maxDelay = max($baseDelay, $maxDelay);
        $this->retryableExceptions = $retryableExceptions ?: $this->getDefaultRetryableExceptions();
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * Execute an operation with retry logic.
     *
     * @param callable $operation The operation to execute
     * @param array $context Additional context for logging
     * @return mixed The result of the operation
     * @throws \Throwable The last exception if all retries fail
     */
    public function execute(callable $operation, array $context = []): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            try {
                $result = $operation();

                if ($attempt > 0) {
                    $this->logger->info('Operation succeeded after retry', [
                        'attempt' => $attempt,
                        'context' => $context,
                    ]);
                }

                return $result;
            } catch (\Throwable $exception) {
                $lastException = $exception;

                if ($attempt >= $this->maxRetries) {
                    $this->logger->error('Operation failed after all retries', [
                        'attempts' => $attempt + 1,
                        'exception' => $exception->getMessage(),
                        'context' => $context,
                    ]);
                    break;
                }

                if (!$this->shouldRetry($exception)) {
                    $this->logger->info('Operation failed with non-retryable exception', [
                        'attempt' => $attempt + 1,
                        'exception' => $exception->getMessage(),
                        'context' => $context,
                    ]);
                    break;
                }

                $delay = $this->calculateDelay($attempt);

                $this->logger->warning('Operation failed, retrying', [
                    'attempt' => $attempt + 1,
                    'next_attempt_in_ms' => $delay,
                    'exception' => $exception->getMessage(),
                    'context' => $context,
                ]);

                $this->sleep($delay);
                $attempt++;
            }
        }

        throw $lastException;
    }

    /**
     * Check if an exception should trigger a retry.
     *
     * @param \Throwable $exception The exception to check
     * @return bool True if the operation should be retried
     */
    public function shouldRetry(\Throwable $exception): bool
    {
        foreach ($this->retryableExceptions as $retryableException) {
            if ($exception instanceof $retryableException) {
                return true;
            }
        }

        // Check for specific error patterns that indicate transient failures
        $message = strtolower($exception->getMessage());
        $transientPatterns = [
            'connection',
            'timeout',
            'network',
            'temporary',
            'unavailable',
            'busy',
            'overloaded',
        ];

        foreach ($transientPatterns as $pattern) {
            if (strpos($message, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate delay for the next retry attempt using exponential backoff.
     *
     * @param int $attempt The current attempt number (0-based)
     * @return int Delay in milliseconds
     */
    public function calculateDelay(int $attempt): int
    {
        $delay = $this->baseDelay * pow($this->backoffMultiplier, $attempt);

        // Add jitter to prevent thundering herd
        $jitter = mt_rand(0, (int) ($delay * 0.1));
        $delay += $jitter;

        return min((int) $delay, $this->maxDelay);
    }

    /**
     * Get the maximum number of retries.
     *
     * @return int
     */
    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * Get the base delay in milliseconds.
     *
     * @return int
     */
    public function getBaseDelay(): int
    {
        return $this->baseDelay;
    }

    /**
     * Get the backoff multiplier.
     *
     * @return float
     */
    public function getBackoffMultiplier(): float
    {
        return $this->backoffMultiplier;
    }

    /**
     * Get the maximum delay in milliseconds.
     *
     * @return int
     */
    public function getMaxDelay(): int
    {
        return $this->maxDelay;
    }

    /**
     * Get the list of retryable exception classes.
     *
     * @return array
     */
    public function getRetryableExceptions(): array
    {
        return $this->retryableExceptions;
    }

    /**
     * Sleep for the specified number of milliseconds.
     *
     * @param int $milliseconds
     * @return void
     */
    protected function sleep(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }

    /**
     * Get default retryable exception classes.
     *
     * @return array
     */
    private function getDefaultRetryableExceptions(): array
    {
        return [
            \RuntimeException::class,
            \League\Flysystem\UnableToReadFile::class,
            \League\Flysystem\UnableToWriteFile::class,
            \League\Flysystem\UnableToDeleteFile::class,
            \League\Flysystem\UnableToCreateDirectory::class,
            \League\Flysystem\UnableToDeleteDirectory::class,
            \League\Flysystem\UnableToRetrieveMetadata::class,
        ];
    }
}
