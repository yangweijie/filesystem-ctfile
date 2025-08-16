<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Tests\Unit;

use YangWeijie\FilesystemCtfile\CtFileClient;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileAuthenticationException;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileConnectionException;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileOperationException;
use YangWeijie\FilesystemCtfile\Tests\TestCase;

class CtFileClientTest extends TestCase
{
    private array $validConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validConfig = [
            'host' => 'test-server.example.com',
            'port' => 21,
            'username' => 'testuser',
            'password' => 'testpass',
            'timeout' => 30,
            'ssl' => false,
            'passive' => true,
        ];
    }

    public function test_constructor_with_valid_config(): void
    {
        $client = new CtFileClient($this->validConfig);

        $this->assertInstanceOf(CtFileClient::class, $client);
        $this->assertFalse($client->isConnected());
    }

    public function test_constructor_with_minimal_config(): void
    {
        $minimalConfig = [
            'host' => 'test-server.example.com',
            'username' => 'testuser',
            'password' => 'testpass',
        ];

        $client = new CtFileClient($minimalConfig);
        $config = $client->getConfig();

        $this->assertEquals('test-server.example.com', $config['host']);
        $this->assertEquals('testuser', $config['username']);
        $this->assertEquals('testpass', $config['password']);
        $this->assertEquals(21, $config['port']); // Default value
        $this->assertEquals(30, $config['timeout']); // Default value
        $this->assertFalse($config['ssl']); // Default value
        $this->assertTrue($config['passive']); // Default value
    }

    public function test_constructor_throws_exception_for_missing_host(): void
    {
        $this->expectException(CtFileConnectionException::class);
        $this->expectExceptionMessage('Missing required configuration: host');

        new CtFileClient([
            'username' => 'testuser',
            'password' => 'testpass',
        ]);
    }

    public function test_constructor_throws_exception_for_missing_username(): void
    {
        $this->expectException(CtFileConnectionException::class);
        $this->expectExceptionMessage('Missing required configuration: username');

        new CtFileClient([
            'host' => 'test-server.example.com',
            'password' => 'testpass',
        ]);
    }

    public function test_constructor_throws_exception_for_missing_password(): void
    {
        $this->expectException(CtFileConnectionException::class);
        $this->expectExceptionMessage('Missing required configuration: password');

        new CtFileClient([
            'host' => 'test-server.example.com',
            'username' => 'testuser',
        ]);
    }

    public function test_constructor_throws_exception_for_multiple_missing_fields(): void
    {
        $this->expectException(CtFileConnectionException::class);
        $this->expectExceptionMessage('Missing required configuration');

        new CtFileClient([]);
    }

    public function test_initial_connection_status(): void
    {
        $client = new CtFileClient($this->validConfig);

        $this->assertFalse($client->isConnected());

        $status = $client->getConnectionStatus();
        $this->assertFalse($status['connected']);
        $this->assertEquals('test-server.example.com', $status['host']);
        $this->assertEquals(21, $status['port']);
        $this->assertEquals('testuser', $status['username']);
        $this->assertNull($status['last_connection_attempt']);
        $this->assertEquals(0, $status['connection_retries']);
        $this->assertFalse($status['ssl_enabled']);
        $this->assertTrue($status['passive_mode']);
    }

    public function test_successful_connection(): void
    {
        $client = new CtFileClient($this->validConfig);

        $result = $client->connect();

        $this->assertTrue($result);
        $this->assertTrue($client->isConnected());

        $status = $client->getConnectionStatus();
        $this->assertTrue($status['connected']);
        $this->assertIsInt($status['last_connection_attempt']);
        $this->assertEquals(0, $status['connection_retries']); // Reset after successful connection
    }

    public function test_connection_failure_with_invalid_host(): void
    {
        $config = array_merge($this->validConfig, ['host' => 'invalid-host']);
        $client = new CtFileClient($config);

        $this->expectException(CtFileConnectionException::class);
        $this->expectExceptionMessage('Failed to establish connection to ctFile server');

        $client->connect();
    }

    public function test_authentication_failure(): void
    {
        $config = array_merge($this->validConfig, ['username' => 'invalid-user']);
        $client = new CtFileClient($config);

        $this->expectException(CtFileAuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials provided for ctFile authentication');

        $client->connect();
    }

    public function test_disconnect(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $this->assertTrue($client->isConnected());

        $client->disconnect();

        $this->assertFalse($client->isConnected());

        $status = $client->getConnectionStatus();
        $this->assertFalse($status['connected']);
        $this->assertEquals(0, $status['connection_retries']);
    }

    public function test_disconnect_when_not_connected(): void
    {
        $client = new CtFileClient($this->validConfig);

        // Should not throw exception
        $client->disconnect();

        $this->assertFalse($client->isConnected());
    }

    public function test_multiple_connect_calls(): void
    {
        $client = new CtFileClient($this->validConfig);

        $result1 = $client->connect();
        $result2 = $client->connect(); // Should return true without reconnecting

        $this->assertTrue($result1);
        $this->assertTrue($result2);
        $this->assertTrue($client->isConnected());
    }

    public function test_get_config_all(): void
    {
        $client = new CtFileClient($this->validConfig);
        $config = $client->getConfig();

        $this->assertEquals($this->validConfig, $config);
    }

    public function test_get_config_specific_key(): void
    {
        $client = new CtFileClient($this->validConfig);

        $this->assertEquals('test-server.example.com', $client->getConfig('host'));
        $this->assertEquals('testuser', $client->getConfig('username'));
        $this->assertEquals(21, $client->getConfig('port'));
    }

    public function test_get_config_with_default(): void
    {
        $client = new CtFileClient($this->validConfig);

        $this->assertEquals('default-value', $client->getConfig('nonexistent', 'default-value'));
        $this->assertNull($client->getConfig('nonexistent'));
    }

    public function test_connection_status_after_connection(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $status = $client->getConnectionStatus();

        $this->assertTrue($status['connected']);
        $this->assertEquals('test-server.example.com', $status['host']);
        $this->assertEquals(21, $status['port']);
        $this->assertEquals('testuser', $status['username']);
        $this->assertIsInt($status['last_connection_attempt']);
        $this->assertGreaterThan(0, $status['last_connection_attempt']);
        $this->assertEquals(0, $status['connection_retries']);
        $this->assertFalse($status['ssl_enabled']);
        $this->assertTrue($status['passive_mode']);
    }

    public function test_destructor_calls_disconnect(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $this->assertTrue($client->isConnected());

        // Trigger destructor
        unset($client);

        // We can't directly test the destructor, but we can ensure
        // the disconnect method works properly when called
        $this->assertTrue(true); // This test mainly ensures no exceptions are thrown
    }

    public function test_config_normalization_with_custom_values(): void
    {
        $customConfig = [
            'host' => 'custom-server.example.com',
            'port' => 2121,
            'username' => 'customuser',
            'password' => 'custompass',
            'timeout' => 60,
            'ssl' => true,
            'passive' => false,
        ];

        $client = new CtFileClient($customConfig);
        $config = $client->getConfig();

        $this->assertEquals('custom-server.example.com', $config['host']);
        $this->assertEquals(2121, $config['port']);
        $this->assertEquals('customuser', $config['username']);
        $this->assertEquals('custompass', $config['password']);
        $this->assertEquals(60, $config['timeout']);
        $this->assertTrue($config['ssl']);
        $this->assertFalse($config['passive']);
    }

    // File Operation Tests

    public function test_upload_file_success(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        // Create a temporary test file
        $localPath = tempnam(sys_get_temp_dir(), 'ctfile_test_');
        file_put_contents($localPath, 'Test file content');

        $result = $client->uploadFile($localPath, '/remote/test.txt');

        $this->assertTrue($result);

        // Cleanup
        unlink($localPath);
    }

    public function test_upload_file_local_not_exists(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Local file does not exist');

        $client->uploadFile('/nonexistent/file.txt', '/remote/test.txt');
    }

    public function test_upload_file_not_readable(): void
    {
        // Skip this test on Windows as chmod doesn't work the same way
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('chmod behavior is different on Windows');
        }

        $client = new CtFileClient($this->validConfig);
        $client->connect();

        // Create a temporary test file and make it unreadable
        $localPath = tempnam(sys_get_temp_dir(), 'ctfile_test_');
        file_put_contents($localPath, 'Test file content');
        chmod($localPath, 0o000);

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Local file is not readable');

        try {
            $client->uploadFile($localPath, '/remote/test.txt');
        } finally {
            // Cleanup
            chmod($localPath, 0o644);
            unlink($localPath);
        }
    }

    public function test_upload_file_remote_failure(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        // Create a temporary test file
        $localPath = tempnam(sys_get_temp_dir(), 'ctfile_test_');
        file_put_contents($localPath, 'Test file content');

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Failed to upload file to remote path');

        try {
            $client->uploadFile($localPath, '/remote/fail-upload.txt');
        } finally {
            // Cleanup
            unlink($localPath);
        }
    }

    public function test_download_file_success(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $localPath = tempnam(sys_get_temp_dir(), 'ctfile_download_');
        unlink($localPath); // Remove the temp file so we can test creation

        $result = $client->downloadFile('/remote/test.txt', $localPath);

        $this->assertTrue($result);
        $this->assertFileExists($localPath);
        $this->assertStringContainsString('Mock file content', file_get_contents($localPath));

        // Cleanup
        unlink($localPath);
    }

    public function test_download_file_remote_not_exists(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $localPath = tempnam(sys_get_temp_dir(), 'ctfile_download_');

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Remote file does not exist');

        try {
            $client->downloadFile('/remote/nonexistent.txt', $localPath);
        } finally {
            // Cleanup
            if (file_exists($localPath)) {
                unlink($localPath);
            }
        }
    }

    public function test_download_file_remote_failure(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $localPath = tempnam(sys_get_temp_dir(), 'ctfile_download_');

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Failed to download file from remote path');

        try {
            $client->downloadFile('/remote/fail-download.txt', $localPath);
        } finally {
            // Cleanup
            if (file_exists($localPath)) {
                unlink($localPath);
            }
        }
    }

    public function test_delete_file_success(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $result = $client->deleteFile('/remote/test.txt');

        $this->assertTrue($result);
    }

    public function test_delete_file_not_exists(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        // Should return true even if file doesn't exist (idempotent)
        $result = $client->deleteFile('/remote/nonexistent.txt');

        $this->assertTrue($result);
    }

    public function test_delete_file_failure(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Failed to delete file');

        $client->deleteFile('/remote/fail-delete.txt');
    }

    public function test_file_exists_true(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $result = $client->fileExists('/remote/test.txt');

        $this->assertTrue($result);
    }

    public function test_file_exists_false(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $result = $client->fileExists('/remote/nonexistent.txt');

        $this->assertFalse($result);
    }

    public function test_get_file_info_success(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $info = $client->getFileInfo('/remote/test.txt');

        $this->assertIsArray($info);
        $this->assertEquals('/remote/test.txt', $info['path']);
        $this->assertEquals(1024, $info['size']);
        $this->assertEquals('file', $info['type']);
        $this->assertEquals('644', $info['permissions']);
        $this->assertIsInt($info['last_modified']);
        $this->assertEquals('text/plain', $info['mime_type']);
        $this->assertEquals('testuser', $info['owner']);
        $this->assertEquals('testgroup', $info['group']);
    }

    public function test_get_file_info_not_exists(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('File does not exist');

        $client->getFileInfo('/remote/nonexistent.txt');
    }

    public function test_read_file_success(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $content = $client->readFile('/remote/test.txt');

        $this->assertIsString($content);
        $this->assertEquals('Mock file contents for /remote/test.txt', $content);
    }

    public function test_read_file_not_exists(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('File does not exist');

        $client->readFile('/remote/nonexistent.txt');
    }

    public function test_write_file_success(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $result = $client->writeFile('/remote/new-file.txt', 'Test content');

        $this->assertTrue($result);
    }

    public function test_write_file_failure(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Failed to write file');

        $client->writeFile('/remote/fail-write.txt', 'Test content');
    }

    public function test_file_operations_require_connection(): void
    {
        // Test with invalid config to prevent auto-connection
        $invalidConfig = array_merge($this->validConfig, ['host' => 'invalid-host']);
        $client = new CtFileClient($invalidConfig);

        $this->expectException(CtFileConnectionException::class);

        $client->fileExists('/remote/test.txt');
    }

    public function test_file_operations_auto_connect(): void
    {
        $client = new CtFileClient($this->validConfig);

        // Should automatically connect when needed
        $result = $client->fileExists('/remote/test.txt');

        $this->assertTrue($result);
        $this->assertTrue($client->isConnected());
    }

    // Directory Operation Tests

    public function test_create_directory_success(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $result = $client->createDirectory('/remote/new-dir');

        $this->assertTrue($result);
    }

    public function test_create_directory_already_exists(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        // Should return true even if directory already exists (idempotent)
        $result = $client->createDirectory('/remote/existing-dir');

        $this->assertTrue($result);
    }

    public function test_create_directory_failure(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Failed to create directory');

        $client->createDirectory('/remote/fail-create');
    }

    public function test_create_directory_recursive(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $result = $client->createDirectory('/remote/deep/nested/dir', true);

        $this->assertTrue($result);
    }

    public function test_remove_directory_success(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $result = $client->removeDirectory('/remote/test-dir');

        $this->assertTrue($result);
    }

    public function test_remove_directory_not_exists(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        // Should return true even if directory doesn't exist (idempotent)
        $result = $client->removeDirectory('/remote/nonexistent');

        $this->assertTrue($result);
    }

    public function test_remove_directory_failure(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Failed to remove directory');

        $client->removeDirectory('/remote/fail-remove');
    }

    public function test_remove_directory_recursive(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $result = $client->removeDirectory('/remote/non-empty', true);

        $this->assertTrue($result);
    }

    public function test_directory_exists_true(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $result = $client->directoryExists('/remote/test-dir');

        $this->assertTrue($result);
    }

    public function test_directory_exists_false(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $result = $client->directoryExists('/remote/nonexistent');

        $this->assertFalse($result);
    }

    public function test_list_files_success(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $files = $client->listFiles('/remote/test-dir');

        $this->assertIsArray($files);
        $this->assertCount(3, $files);

        // Check first file
        $this->assertEquals('file1.txt', $files[0]['name']);
        $this->assertEquals('/remote/test-dir/file1.txt', $files[0]['path']);
        $this->assertEquals('file', $files[0]['type']);
        $this->assertEquals(1024, $files[0]['size']);

        // Check directory
        $this->assertEquals('subdir', $files[2]['name']);
        $this->assertEquals('directory', $files[2]['type']);
    }

    public function test_list_files_recursive(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $files = $client->listFiles('/remote/test-dir', true);

        $this->assertIsArray($files);
        $this->assertCount(4, $files); // 3 base + 1 recursive

        // Check recursive file
        $recursiveFile = array_filter($files, fn ($file) => $file['name'] === 'nested.txt');
        $this->assertCount(1, $recursiveFile);
        $recursiveFile = array_values($recursiveFile)[0];
        $this->assertEquals('/remote/test-dir/subdir/nested.txt', $recursiveFile['path']);
    }

    public function test_list_files_directory_not_exists(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Directory does not exist');

        $client->listFiles('/remote/nonexistent');
    }

    public function test_get_directory_info_success(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $info = $client->getDirectoryInfo('/remote/test-dir');

        $this->assertIsArray($info);
        $this->assertEquals('/remote/test-dir', $info['path']);
        $this->assertEquals('directory', $info['type']);
        $this->assertEquals('755', $info['permissions']);
        $this->assertIsInt($info['last_modified']);
        $this->assertEquals('testuser', $info['owner']);
        $this->assertEquals('testgroup', $info['group']);
        $this->assertEquals(3, $info['file_count']);
        $this->assertEquals(1, $info['directory_count']);
        $this->assertEquals(3584, $info['total_size']);
    }

    public function test_get_directory_info_not_exists(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Directory does not exist');

        $client->getDirectoryInfo('/remote/nonexistent');
    }

    public function test_move_directory_success(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $result = $client->moveDirectory('/remote/source-dir', '/remote/dest-dir');

        $this->assertTrue($result);
    }

    public function test_move_directory_source_not_exists(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Source directory does not exist');

        $client->moveDirectory('/remote/nonexistent', '/remote/dest-dir');
    }

    public function test_move_directory_destination_exists(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Destination directory already exists');

        $client->moveDirectory('/remote/source-dir', '/remote/test-dir');
    }

    public function test_move_directory_failure(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Failed to move directory');

        $client->moveDirectory('/remote/fail-move', '/remote/new-dest-dir');
    }

    public function test_copy_directory_success(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $result = $client->copyDirectory('/remote/source-dir', '/remote/copy-dir');

        $this->assertTrue($result);
    }

    public function test_copy_directory_source_not_exists(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Source directory does not exist');

        $client->copyDirectory('/remote/nonexistent', '/remote/copy-dir');
    }

    public function test_copy_directory_failure(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $this->expectException(CtFileOperationException::class);
        $this->expectExceptionMessage('Failed to copy directory');

        $client->copyDirectory('/remote/fail-copy', '/remote/copy-dir');
    }

    public function test_copy_directory_recursive(): void
    {
        $client = new CtFileClient($this->validConfig);
        $client->connect();

        $result = $client->copyDirectory('/remote/source-dir', '/remote/copy-dir', true);

        $this->assertTrue($result);
    }

    public function test_directory_operations_require_connection(): void
    {
        // Test with invalid config to prevent auto-connection
        $invalidConfig = array_merge($this->validConfig, ['host' => 'invalid-host']);
        $client = new CtFileClient($invalidConfig);

        $this->expectException(CtFileConnectionException::class);

        $client->directoryExists('/remote/test-dir');
    }

    public function test_directory_operations_auto_connect(): void
    {
        $client = new CtFileClient($this->validConfig);

        // Should automatically connect when needed
        $result = $client->directoryExists('/remote/test-dir');

        $this->assertTrue($result);
        $this->assertTrue($client->isConnected());
    }
}
