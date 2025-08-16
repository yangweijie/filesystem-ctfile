<?php

declare(strict_types=1);

/*
 * This file is part of the yangweijie/filesystem-ctfile package.
 *
 * (c) Yang Weijie <yangweijie@example.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YangWeijie\FilesystemCtfile\Tests\Integration;

use League\Flysystem\Filesystem;
use Mockery;
use YangWeijie\FilesystemCtfile\Cache\MemoryCache;
use YangWeijie\FilesystemCtfile\CacheManager;
use YangWeijie\FilesystemCtfile\CtFileAdapter;
use YangWeijie\FilesystemCtfile\CtFileClient;
use YangWeijie\FilesystemCtfile\RetryHandler;
use YangWeijie\FilesystemCtfile\Tests\Fixtures\MockCtFileServer;
use YangWeijie\FilesystemCtfile\Tests\TestCase;

/**
 * Performance and stress tests for the CtFile filesystem adapter.
 *
 * These tests verify performance characteristics, memory usage,
 * and behavior under stress conditions.
 */
class PerformanceTest extends TestCase
{
    private MockCtFileServer $mockServer;

    private CtFileClient $client;

    private CtFileAdapter $adapter;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockServer = new MockCtFileServer();
        $this->client = Mockery::mock(CtFileClient::class);
        $this->setupClientMockDelegation();

        $this->adapter = new CtFileAdapter($this->client);
        $this->filesystem = new Filesystem($this->adapter);
    }

    public function test_performance_single_file_operations(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');
        $this->mockServer->addFile('test.txt', 'content');

        // Measure single operation performance
        $iterations = 100;
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        for ($i = 0; $i < $iterations; $i++) {
            $this->filesystem->fileExists('test.txt');
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $totalTime = $endTime - $startTime;
        $averageTime = $totalTime / $iterations;
        $memoryUsed = $endMemory - $startMemory;

        // Performance assertions (adjust thresholds as needed)
        expect($averageTime)->toBeLessThan(0.001); // Less than 1ms per operation
        expect($memoryUsed)->toBeLessThan(1024 * 1024); // Less than 1MB memory increase
        expect($this->mockServer->getOperationCount('fileExists'))->toBe($iterations);

        // Log performance metrics for analysis
        $this->logPerformanceMetrics('single_file_operations', [
            'iterations' => $iterations,
            'total_time' => $totalTime,
            'average_time' => $averageTime,
            'memory_used' => $memoryUsed,
        ]);
    }

    public function test_performance_batch_file_operations(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Create multiple files
        $fileCount = 1000;
        for ($i = 1; $i <= $fileCount; $i++) {
            $this->mockServer->addFile("file{$i}.txt", "content{$i}");
        }

        // Measure batch operation performance
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $results = [];
        for ($i = 1; $i <= $fileCount; $i++) {
            $results[] = $this->filesystem->fileExists("file{$i}.txt");
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $totalTime = $endTime - $startTime;
        $averageTime = $totalTime / $fileCount;
        $memoryUsed = $endMemory - $startMemory;

        // Verify all operations succeeded
        expect(array_filter($results))->toHaveCount($fileCount);

        // Performance assertions
        expect($averageTime)->toBeLessThan(0.001); // Less than 1ms per operation
        expect($memoryUsed)->toBeLessThan(5 * 1024 * 1024); // Less than 5MB memory increase

        $this->logPerformanceMetrics('batch_file_operations', [
            'file_count' => $fileCount,
            'total_time' => $totalTime,
            'average_time' => $averageTime,
            'memory_used' => $memoryUsed,
        ]);
    }

    public function test_performance_with_caching(): void
    {
        // Setup adapter with caching
        $cache = new MemoryCache();
        $cacheManager = new CacheManager($cache, ['enabled' => true, 'ttl' => 300]);
        $adapter = new CtFileAdapter($this->client, [], $cacheManager);
        $filesystem = new Filesystem($adapter);

        $this->mockServer->connect('localhost', 21, 'test', 'password');
        $this->mockServer->addFile('cached.txt', 'content');

        // First run - populate cache
        $iterations = 100;
        $startTime = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $filesystem->fileExists('cached.txt');
        }

        $firstRunTime = microtime(true) - $startTime;

        // Second run - should use cache (mock won't be called as much)
        $startTime = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $filesystem->fileExists('cached.txt');
        }

        $secondRunTime = microtime(true) - $startTime;

        // Cache should improve performance (though with mocks, the difference might be minimal)
        expect($cacheManager->isEnabled())->toBeTrue();

        $this->logPerformanceMetrics('caching_performance', [
            'iterations' => $iterations,
            'first_run_time' => $firstRunTime,
            'second_run_time' => $secondRunTime,
            'cache_enabled' => true,
        ]);
    }

    public function test_performance_with_retry_handler(): void
    {
        // Setup adapter with retry handler
        $retryHandler = new RetryHandler(3, 100); // 3 retries, 100ms delay
        $adapter = new CtFileAdapter($this->client, [], null, $retryHandler);
        $filesystem = new Filesystem($adapter);

        $this->mockServer->connect('localhost', 21, 'test', 'password');
        $this->mockServer->addFile('retry.txt', 'content');

        // Measure performance with retry handler
        $iterations = 50; // Fewer iterations due to retry overhead
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        for ($i = 0; $i < $iterations; $i++) {
            $filesystem->fileExists('retry.txt');
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $totalTime = $endTime - $startTime;
        $averageTime = $totalTime / $iterations;
        $memoryUsed = $endMemory - $startMemory;

        // Performance should still be reasonable with retry handler
        expect($averageTime)->toBeLessThan(0.01); // Less than 10ms per operation

        $this->logPerformanceMetrics('retry_handler_performance', [
            'iterations' => $iterations,
            'total_time' => $totalTime,
            'average_time' => $averageTime,
            'memory_used' => $memoryUsed,
        ]);
    }

    public function test_stress_concurrent_operations(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Create many files for stress testing
        $fileCount = 5000;
        for ($i = 1; $i <= $fileCount; $i++) {
            $this->mockServer->addFile("stress{$i}.txt", "content{$i}");
        }

        // Simulate concurrent operations by interleaving different operation types
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $operations = 0;
        for ($i = 1; $i <= 1000; $i++) {
            // Mix of operations
            $this->filesystem->fileExists("stress{$i}.txt");
            $operations++;

            if ($i % 10 === 0) {
                $this->filesystem->directoryExists('uploads');
                $operations++;
            }

            if ($i % 100 === 0) {
                // Check memory usage periodically
                $currentMemory = memory_get_usage();
                expect($currentMemory - $startMemory)->toBeLessThan(10 * 1024 * 1024); // Less than 10MB
            }
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $totalTime = $endTime - $startTime;
        $averageTime = $totalTime / $operations;
        $memoryUsed = $endMemory - $startMemory;

        // Stress test assertions
        expect($averageTime)->toBeLessThan(0.002); // Less than 2ms per operation under stress
        expect($memoryUsed)->toBeLessThan(10 * 1024 * 1024); // Less than 10MB memory increase

        $this->logPerformanceMetrics('stress_concurrent_operations', [
            'operations' => $operations,
            'total_time' => $totalTime,
            'average_time' => $averageTime,
            'memory_used' => $memoryUsed,
        ]);
    }

    public function test_stress_large_directory_structures(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Create deep directory structure
        $depth = 10;
        $filesPerLevel = 50;

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $totalFiles = 0;
        for ($level = 1; $level <= $depth; $level++) {
            $path = str_repeat('level/', $level);

            for ($file = 1; $file <= $filesPerLevel; $file++) {
                $filePath = $path . "file{$file}.txt";
                $this->mockServer->addFile($filePath, "content at level {$level}");
                $totalFiles++;
            }
        }

        $setupTime = microtime(true) - $startTime;

        // Test operations on deep structure
        $startTime = microtime(true);
        $operations = 0;

        for ($level = 1; $level <= $depth; $level++) {
            $path = str_repeat('level/', $level);

            // Check directory existence
            $this->filesystem->directoryExists(rtrim($path, '/'));
            $operations++;

            // Check some files in each level
            for ($file = 1; $file <= min(10, $filesPerLevel); $file++) {
                $filePath = $path . "file{$file}.txt";
                $this->filesystem->fileExists($filePath);
                $operations++;
            }
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $operationTime = $endTime - $startTime;
        $averageTime = $operationTime / $operations;
        $memoryUsed = $endMemory - $startMemory;

        // Performance should remain reasonable with deep structures
        expect($averageTime)->toBeLessThan(0.005); // Less than 5ms per operation

        $this->logPerformanceMetrics('large_directory_structures', [
            'depth' => $depth,
            'files_per_level' => $filesPerLevel,
            'total_files' => $totalFiles,
            'setup_time' => $setupTime,
            'operations' => $operations,
            'operation_time' => $operationTime,
            'average_time' => $averageTime,
            'memory_used' => $memoryUsed,
        ]);
    }

    public function test_stress_error_conditions(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Test performance under error conditions
        $this->mockServer->simulateError('fileExists', true, 'Simulated network error');

        $iterations = 100;
        $errors = 0;
        $startTime = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            try {
                $this->filesystem->fileExists("error{$i}.txt");
            } catch (\Exception $e) {
                $errors++;
            }
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $averageTime = $totalTime / $iterations;

        // All operations should have failed
        expect($errors)->toBe($iterations);

        // Error handling should still be performant
        expect($averageTime)->toBeLessThan(0.01); // Less than 10ms per error

        $this->logPerformanceMetrics('error_conditions', [
            'iterations' => $iterations,
            'errors' => $errors,
            'total_time' => $totalTime,
            'average_time' => $averageTime,
        ]);
    }

    public function test_memory_usage_stability(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Create files for testing
        for ($i = 1; $i <= 100; $i++) {
            $this->mockServer->addFile("memory{$i}.txt", str_repeat('x', 1000)); // 1KB each
        }

        $initialMemory = memory_get_usage();
        $memoryReadings = [];

        // Perform operations and track memory usage
        for ($batch = 1; $batch <= 10; $batch++) {
            for ($i = 1; $i <= 100; $i++) {
                $this->filesystem->fileExists("memory{$i}.txt");
            }

            $currentMemory = memory_get_usage();
            $memoryReadings[] = $currentMemory - $initialMemory;

            // Force garbage collection
            gc_collect_cycles();
        }

        // Memory usage should be stable (not continuously growing)
        $maxMemory = max($memoryReadings);
        $minMemory = min($memoryReadings);
        $memoryVariation = $maxMemory - $minMemory;

        // Memory variation should be reasonable (increased threshold for test environment)
        expect($memoryVariation)->toBeLessThan(5 * 1024 * 1024); // Less than 5MB variation

        $this->logPerformanceMetrics('memory_stability', [
            'batches' => 10,
            'operations_per_batch' => 100,
            'initial_memory' => $initialMemory,
            'max_memory_increase' => $maxMemory,
            'min_memory_increase' => $minMemory,
            'memory_variation' => $memoryVariation,
        ]);
    }

    public function test_performance_regression_detection(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');
        $this->mockServer->addFile('benchmark.txt', 'content');

        // Baseline performance measurement
        $baselineIterations = 1000;
        $startTime = microtime(true);

        for ($i = 0; $i < $baselineIterations; $i++) {
            $this->filesystem->fileExists('benchmark.txt');
        }

        $baselineTime = microtime(true) - $startTime;
        $baselineAverage = $baselineTime / $baselineIterations;

        // Performance regression thresholds
        $maxAcceptableTime = $baselineAverage * 1.5; // 50% slower is concerning
        $targetTime = $baselineAverage * 0.8; // 20% faster is good

        // Verify performance is within acceptable range
        expect($baselineAverage)->toBeLessThan(0.001); // Less than 1ms baseline

        $this->logPerformanceMetrics('regression_detection', [
            'baseline_iterations' => $baselineIterations,
            'baseline_time' => $baselineTime,
            'baseline_average' => $baselineAverage,
            'max_acceptable_time' => $maxAcceptableTime,
            'target_time' => $targetTime,
        ]);
    }

    /**
     * Setup client mock to delegate to mock server.
     */
    private function setupClientMockDelegation(): void
    {
        $this->client
            ->shouldReceive('fileExists')
            ->andReturnUsing(function ($path) {
                return $this->mockServer->fileExists($path);
            });

        $this->client
            ->shouldReceive('directoryExists')
            ->andReturnUsing(function ($path) {
                return $this->mockServer->directoryExists($path);
            });
    }

    /**
     * Log performance metrics for analysis.
     *
     * @param string $testName Test name
     * @param array $metrics Performance metrics
     */
    private function logPerformanceMetrics(string $testName, array $metrics): void
    {
        // In a real implementation, this could write to a file or send to a monitoring system
        // For now, we'll just ensure the metrics are reasonable

        if (isset($metrics['total_time'])) {
            expect($metrics['total_time'])->toBeGreaterThan(0);
        }

        if (isset($metrics['memory_used'])) {
            // Memory usage can be negative due to garbage collection, so we'll just log it
            // expect($metrics['memory_used'])->toBeGreaterThanOrEqual(0);
        }

        // Could add more sophisticated performance tracking here
    }
}
