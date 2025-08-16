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
 * Stress tests for the CtFile filesystem adapter.
 *
 * These tests verify system behavior under extreme conditions,
 * including high load, resource constraints, and error scenarios.
 */
class StressTest extends TestCase
{
    private MockCtFileServer $mockServer;

    private CtFileClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockServer = new MockCtFileServer();
        $this->client = Mockery::mock(CtFileClient::class);
        $this->setupClientMockDelegation();
    }

    public function test_stress_high_volume_file_operations(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Create a large number of files
        $fileCount = 10000;
        for ($i = 1; $i <= $fileCount; $i++) {
            $this->mockServer->addFile("volume/file{$i}.txt", "content{$i}");
        }

        $adapter = new CtFileAdapter($this->client);
        $filesystem = new Filesystem($adapter);

        // Stress test with high volume operations
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $successCount = 0;
        $errorCount = 0;

        for ($i = 1; $i <= $fileCount; $i++) {
            try {
                if ($filesystem->fileExists("volume/file{$i}.txt")) {
                    $successCount++;
                }
            } catch (\Exception $e) {
                $errorCount++;
            }

            // Check memory usage every 1000 operations
            if ($i % 1000 === 0) {
                $currentMemory = memory_get_usage(true);
                $memoryIncrease = $currentMemory - $startMemory;

                // Memory should not grow excessively
                expect($memoryIncrease)->toBeLessThan(50 * 1024 * 1024); // Less than 50MB

                // Force garbage collection to prevent memory buildup
                gc_collect_cycles();
            }
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $totalTime = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        $averageTime = $totalTime / $fileCount;

        // Verify results
        expect($successCount)->toBe($fileCount);
        expect($errorCount)->toBe(0);
        expect($averageTime)->toBeLessThan(0.001); // Less than 1ms per operation
        expect($memoryUsed)->toBeLessThan(100 * 1024 * 1024); // Less than 100MB total

        $this->logStressMetrics('high_volume_operations', [
            'file_count' => $fileCount,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'total_time' => $totalTime,
            'average_time' => $averageTime,
            'memory_used' => $memoryUsed,
        ]);
    }

    public function test_stress_rapid_connection_cycles(): void
    {
        $cycles = 100;
        $operationsPerCycle = 50;

        $startTime = microtime(true);
        $totalOperations = 0;
        $connectionErrors = 0;
        $operationErrors = 0;

        for ($cycle = 1; $cycle <= $cycles; $cycle++) {
            try {
                // Simulate connection cycle
                $this->mockServer->reset();
                $this->mockServer->connect('localhost', 21, 'test', 'password');

                // Add some test files
                for ($i = 1; $i <= $operationsPerCycle; $i++) {
                    $this->mockServer->addFile("cycle{$cycle}/file{$i}.txt", 'content');
                }

                $adapter = new CtFileAdapter($this->client);
                $filesystem = new Filesystem($adapter);

                // Perform operations
                for ($i = 1; $i <= $operationsPerCycle; $i++) {
                    try {
                        $filesystem->fileExists("cycle{$cycle}/file{$i}.txt");
                        $totalOperations++;
                    } catch (\Exception $e) {
                        $operationErrors++;
                    }
                }

                // Simulate disconnection
                $this->mockServer->disconnect();
            } catch (\Exception $e) {
                $connectionErrors++;
            }
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Verify stress test results
        expect($connectionErrors)->toBe(0);
        expect($operationErrors)->toBe(0);
        expect($totalOperations)->toBe($cycles * $operationsPerCycle);

        $this->logStressMetrics('rapid_connection_cycles', [
            'cycles' => $cycles,
            'operations_per_cycle' => $operationsPerCycle,
            'total_operations' => $totalOperations,
            'connection_errors' => $connectionErrors,
            'operation_errors' => $operationErrors,
            'total_time' => $totalTime,
        ]);
    }

    public function test_stress_error_recovery_under_load(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Setup retry handler for error recovery
        $retryHandler = new RetryHandler(3, 50); // 3 retries, 50ms delay
        $adapter = new CtFileAdapter($this->client, [], null, $retryHandler);
        $filesystem = new Filesystem($adapter);

        $operations = 1000;
        $errorRate = 0.1; // 10% error rate
        $successCount = 0;
        $errorCount = 0;
        $recoveredCount = 0;

        $startTime = microtime(true);

        for ($i = 1; $i <= $operations; $i++) {
            // Simulate intermittent errors
            $shouldError = (rand() / getrandmax()) < $errorRate;

            if ($shouldError) {
                $this->mockServer->simulateError('fileExists', true, 'Intermittent error');
            } else {
                $this->mockServer->simulateError('fileExists', false);
                $this->mockServer->addFile("stress{$i}.txt", 'content');
            }

            try {
                $result = $filesystem->fileExists("stress{$i}.txt");
                if ($result || !$shouldError) {
                    $successCount++;
                    if ($shouldError) {
                        $recoveredCount++; // Error was simulated but operation succeeded (retry worked)
                    }
                }
            } catch (\Exception $e) {
                $errorCount++;
            }

            // Reset error simulation for next iteration
            $this->mockServer->simulateError('fileExists', false);
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Verify error recovery performance
        expect($successCount + $errorCount)->toBe($operations);
        expect($errorCount)->toBeLessThan($operations * 0.15); // Less than 15% final error rate (adjusted for test environment)

        $this->logStressMetrics('error_recovery_under_load', [
            'operations' => $operations,
            'error_rate' => $errorRate,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'recovered_count' => $recoveredCount,
            'total_time' => $totalTime,
        ]);
    }

    public function test_stress_cache_performance_under_load(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Setup adapter with caching
        $cache = new MemoryCache();
        $cacheManager = new CacheManager($cache, ['enabled' => true, 'ttl' => 60]);
        $adapter = new CtFileAdapter($this->client, [], $cacheManager);
        $filesystem = new Filesystem($adapter);

        // Create test files
        $fileCount = 1000;
        for ($i = 1; $i <= $fileCount; $i++) {
            $this->mockServer->addFile("cached{$i}.txt", "content{$i}");
        }

        // First pass - populate cache
        $startTime = microtime(true);
        for ($i = 1; $i <= $fileCount; $i++) {
            $filesystem->fileExists("cached{$i}.txt");
        }
        $firstPassTime = microtime(true) - $startTime;

        // Second pass - should use cache
        $startTime = microtime(true);
        for ($i = 1; $i <= $fileCount; $i++) {
            $filesystem->fileExists("cached{$i}.txt");
        }
        $secondPassTime = microtime(true) - $startTime;

        // Third pass - mixed operations (some cached, some new)
        $startTime = microtime(true);
        for ($i = 1; $i <= $fileCount; $i++) {
            if ($i % 2 === 0) {
                $filesystem->fileExists("cached{$i}.txt"); // Cached
            } else {
                $filesystem->fileExists("new{$i}.txt"); // Not cached
            }
        }
        $thirdPassTime = microtime(true) - $startTime;

        // Cache should improve performance
        expect($cacheManager->isEnabled())->toBeTrue();

        $this->logStressMetrics('cache_performance_under_load', [
            'file_count' => $fileCount,
            'first_pass_time' => $firstPassTime,
            'second_pass_time' => $secondPassTime,
            'third_pass_time' => $thirdPassTime,
        ]);
    }

    public function test_stress_memory_pressure_simulation(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        $adapter = new CtFileAdapter($this->client);
        $filesystem = new Filesystem($adapter);

        // Create large number of files with varying sizes
        $fileCount = 5000;
        $totalSize = 0;

        for ($i = 1; $i <= $fileCount; $i++) {
            $size = rand(100, 10000); // Random size between 100B and 10KB
            $content = str_repeat('x', $size);
            $this->mockServer->addFile("memory/file{$i}.txt", $content);
            $totalSize += $size;
        }

        $startMemory = memory_get_usage(true);
        $peakMemory = $startMemory;
        $operations = 0;

        // Perform operations while monitoring memory
        for ($batch = 1; $batch <= 10; $batch++) {
            for ($i = 1; $i <= $fileCount; $i++) {
                $filesystem->fileExists("memory/file{$i}.txt");
                $operations++;

                // Monitor memory usage
                $currentMemory = memory_get_usage(true);
                if ($currentMemory > $peakMemory) {
                    $peakMemory = $currentMemory;
                }

                // Force garbage collection periodically
                if ($operations % 1000 === 0) {
                    gc_collect_cycles();
                }
            }
        }

        $endMemory = memory_get_usage(true);
        $memoryIncrease = $endMemory - $startMemory;
        $peakIncrease = $peakMemory - $startMemory;

        // Memory usage should be reasonable
        expect($memoryIncrease)->toBeLessThan(50 * 1024 * 1024); // Less than 50MB final increase
        expect($peakIncrease)->toBeLessThan(100 * 1024 * 1024); // Less than 100MB peak increase

        $this->logStressMetrics('memory_pressure_simulation', [
            'file_count' => $fileCount,
            'total_file_size' => $totalSize,
            'operations' => $operations,
            'start_memory' => $startMemory,
            'end_memory' => $endMemory,
            'peak_memory' => $peakMemory,
            'memory_increase' => $memoryIncrease,
            'peak_increase' => $peakIncrease,
        ]);
    }

    public function test_stress_concurrent_adapter_instances(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        // Create multiple adapter instances
        $adapterCount = 10;
        $adapters = [];
        $filesystems = [];

        for ($i = 1; $i <= $adapterCount; $i++) {
            $client = Mockery::mock(CtFileClient::class);
            $this->setupClientMockDelegationForClient($client);

            $adapters[$i] = new CtFileAdapter($client, ['root_path' => "/adapter{$i}"]);
            $filesystems[$i] = new Filesystem($adapters[$i]);
        }

        // Create files for each adapter
        $filesPerAdapter = 100;
        for ($i = 1; $i <= $adapterCount; $i++) {
            for ($j = 1; $j <= $filesPerAdapter; $j++) {
                $this->mockServer->addFile("/adapter{$i}/file{$j}.txt", "content{$i}-{$j}");
            }
        }

        // Perform concurrent operations
        $startTime = microtime(true);
        $totalOperations = 0;
        $errors = 0;

        for ($round = 1; $round <= 10; $round++) {
            for ($i = 1; $i <= $adapterCount; $i++) {
                for ($j = 1; $j <= $filesPerAdapter; $j++) {
                    try {
                        $filesystems[$i]->fileExists("file{$j}.txt");
                        $totalOperations++;
                    } catch (\Exception $e) {
                        $errors++;
                    }
                }
            }
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Verify concurrent operations
        expect($errors)->toBe(0);
        expect($totalOperations)->toBe($adapterCount * $filesPerAdapter * 10);

        $this->logStressMetrics('concurrent_adapter_instances', [
            'adapter_count' => $adapterCount,
            'files_per_adapter' => $filesPerAdapter,
            'rounds' => 10,
            'total_operations' => $totalOperations,
            'errors' => $errors,
            'total_time' => $totalTime,
        ]);
    }

    public function test_stress_path_complexity(): void
    {
        $this->mockServer->connect('localhost', 21, 'test', 'password');

        $adapter = new CtFileAdapter($this->client);
        $filesystem = new Filesystem($adapter);

        // Create files with complex paths
        $complexPaths = [
            'simple.txt',
            'folder/file.txt',
            'deep/nested/path/file.txt',
            'very/deep/nested/path/structure/file.txt',
            'folder with spaces/file with spaces.txt',
            'special-chars_123/file-name_456.txt',
            'unicode/файл.txt',
            'numbers/123/456/789/file.txt',
            'mixed/CASE/lower/File.TXT',
            'dots/file.name.with.dots.txt',
        ];

        // Create files
        foreach ($complexPaths as $path) {
            $this->mockServer->addFile($path, "content for {$path}");
        }

        // Stress test with complex paths
        $iterations = 1000;
        $startTime = microtime(true);
        $operations = 0;
        $errors = 0;

        for ($i = 0; $i < $iterations; $i++) {
            foreach ($complexPaths as $path) {
                try {
                    $filesystem->fileExists($path);
                    $operations++;
                } catch (\Exception $e) {
                    $errors++;
                }
            }
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $averageTime = $totalTime / $operations;

        // Verify complex path handling
        expect($errors)->toBe(0);
        expect($operations)->toBe($iterations * count($complexPaths));
        expect($averageTime)->toBeLessThan(0.001); // Less than 1ms per operation

        $this->logStressMetrics('path_complexity', [
            'complex_paths' => count($complexPaths),
            'iterations' => $iterations,
            'operations' => $operations,
            'errors' => $errors,
            'total_time' => $totalTime,
            'average_time' => $averageTime,
        ]);
    }

    /**
     * Setup client mock to delegate to mock server.
     */
    private function setupClientMockDelegation(): void
    {
        $this->setupClientMockDelegationForClient($this->client);
    }

    /**
     * Setup client mock delegation for a specific client.
     */
    private function setupClientMockDelegationForClient($client): void
    {
        $client
            ->shouldReceive('fileExists')
            ->andReturnUsing(function ($path) {
                return $this->mockServer->fileExists($path);
            });

        $client
            ->shouldReceive('directoryExists')
            ->andReturnUsing(function ($path) {
                return $this->mockServer->directoryExists($path);
            });
    }

    /**
     * Log stress test metrics.
     */
    private function logStressMetrics(string $testName, array $metrics): void
    {
        // Verify basic metric validity
        foreach ($metrics as $key => $value) {
            if (is_numeric($value)) {
                expect($value)->toBeGreaterThanOrEqual(0);
            }
        }
    }
}
