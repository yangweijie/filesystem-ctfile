<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Tests\Unit;

use PHPUnit\Framework\TestCase;
use YangWeijie\FilesystemCtfile\CtFileClient;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileOperationException;

/**
 * Unit tests for CtFileClient file and directory manipulation methods.
 */
class CtFileClientManipulationTest extends TestCase
{
    private CtFileClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new CtFileClient([
            'host' => 'test-host',
            'username' => 'testuser',
            'password' => 'testpass',
        ]);
    }

    // Move file tests

    public function test_move_file_successfully(): void
    {
        $source = 'test/source.txt';
        $destination = 'test/destination.txt';

        $result = $this->client->moveFile($source, $destination);

        $this->assertTrue($result);
    }

    public function test_move_file_fails_when_source_does_not_exist(): void
    {
        $source = 'test/nonexistent.txt';
        $destination = 'test/destination.txt';

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Source file does not exist');

        $this->client->moveFile($source, $destination);
    }

    public function test_move_file_fails_when_operation_fails(): void
    {
        $source = 'test/fail-move.txt';
        $destination = 'test/destination.txt';

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Failed to move file');

        $this->client->moveFile($source, $destination);
    }

    public function test_move_file_fails_when_destination_causes_failure(): void
    {
        $source = 'test/source.txt';
        $destination = 'test/fail-move.txt';

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Failed to move file');

        $this->client->moveFile($source, $destination);
    }

    // Copy file tests

    public function test_copy_file_successfully(): void
    {
        $source = 'test/source.txt';
        $destination = 'test/destination.txt';

        $result = $this->client->copyFile($source, $destination);

        $this->assertTrue($result);
    }

    public function test_copy_file_fails_when_source_does_not_exist(): void
    {
        $source = 'test/nonexistent.txt';
        $destination = 'test/destination.txt';

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Source file does not exist');

        $this->client->copyFile($source, $destination);
    }

    public function test_copy_file_fails_when_operation_fails(): void
    {
        $source = 'test/fail-copy.txt';
        $destination = 'test/destination.txt';

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Failed to copy file');

        $this->client->copyFile($source, $destination);
    }

    public function test_copy_file_fails_when_destination_causes_failure(): void
    {
        $source = 'test/source.txt';
        $destination = 'test/fail-copy.txt';

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Failed to copy file');

        $this->client->copyFile($source, $destination);
    }

    // Directory manipulation tests

    public function test_create_directory_successfully(): void
    {
        $path = 'test/new-directory';

        $result = $this->client->createDirectory($path);

        $this->assertTrue($result);
    }

    public function test_create_directory_with_existing_directory(): void
    {
        $path = 'test/existing-directory';

        // Mock that directory already exists by using a path that doesn't contain fail patterns
        $result = $this->client->createDirectory($path);

        $this->assertTrue($result);
    }

    public function test_create_directory_fails_when_operation_fails(): void
    {
        $path = 'test/fail-create';

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Failed to create directory');

        $this->client->createDirectory($path);
    }

    public function test_remove_directory_successfully(): void
    {
        $path = 'test/directory';

        $result = $this->client->removeDirectory($path);

        $this->assertTrue($result);
    }

    public function test_remove_directory_with_nonexistent_directory(): void
    {
        $path = 'test/nonexistent';

        // Should succeed even if directory doesn't exist
        $result = $this->client->removeDirectory($path);

        $this->assertTrue($result);
    }

    public function test_remove_directory_fails_when_operation_fails(): void
    {
        $path = 'test/fail-remove';

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Failed to remove directory');

        $this->client->removeDirectory($path);
    }

    public function test_move_directory_successfully(): void
    {
        $source = 'test/source-dir';
        $destination = 'test/dest-dir';

        $result = $this->client->moveDirectory($source, $destination);

        $this->assertTrue($result);
    }

    public function test_move_directory_fails_when_source_does_not_exist(): void
    {
        $source = 'test/nonexistent';
        $destination = 'test/dest-dir';

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Source directory does not exist');

        $this->client->moveDirectory($source, $destination);
    }

    public function test_move_directory_fails_when_destination_exists(): void
    {
        $source = 'test/source-dir';
        $destination = 'test/existing-directory';

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Destination directory already exists');

        $this->client->moveDirectory($source, $destination);
    }

    public function test_copy_directory_successfully(): void
    {
        $source = 'test/source-dir';
        $destination = 'test/dest-dir';

        $result = $this->client->copyDirectory($source, $destination);

        $this->assertTrue($result);
    }

    public function test_copy_directory_fails_when_source_does_not_exist(): void
    {
        $source = 'test/nonexistent';
        $destination = 'test/dest-dir';

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Source directory does not exist');

        $this->client->copyDirectory($source, $destination);
    }

    public function test_copy_directory_fails_when_operation_fails(): void
    {
        $source = 'test/fail-copy';
        $destination = 'test/dest-dir';

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Failed to copy directory');

        $this->client->copyDirectory($source, $destination);
    }
}