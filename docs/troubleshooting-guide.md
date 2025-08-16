# Troubleshooting Guide

This guide helps you diagnose and resolve common issues when using the `yangweijie/filesystem-ctfile` package.

## Table of Contents

- [Connection Issues](#connection-issues)
- [Authentication Problems](#authentication-problems)
- [File Operation Failures](#file-operation-failures)
- [Performance Issues](#performance-issues)
- [Configuration Problems](#configuration-problems)
- [Memory and Resource Issues](#memory-and-resource-issues)
- [Error Messages Reference](#error-messages-reference)
- [Debugging Tools](#debugging-tools)
- [Getting Help](#getting-help)

## Connection Issues

### Problem: Cannot Connect to ctFile Server

**Symptoms:**
- `CtFileConnectionException: Failed to establish connection`
- Connection timeouts
- Network unreachable errors

**Diagnostic Steps:**

1. **Test Basic Connectivity**
   ```php
   <?php
   $host = 'your-ctfile-server.com';
   $port = 21;
   
   $socket = @fsockopen($host, $port, $errno, $errstr, 10);
   if (!$socket) {
       echo "Cannot connect: {$errstr} ({$errno})\n";
   } else {
       echo "Basic connectivity OK\n";
       fclose($socket);
   }
   ```

2. **Check DNS Resolution**
   ```bash
   nslookup your-ctfile-server.com
   ping your-ctfile-server.com
   ```

3. **Test Different Ports**
   ```php
   <?php
   $commonPorts = [21, 22, 990, 989];
   foreach ($commonPorts as $port) {
       $socket = @fsockopen($host, $port, $errno, $errstr, 5);
       if ($socket) {
           echo "Port {$port}: Open\n";
           fclose($socket);
       } else {
           echo "Port {$port}: Closed\n";
       }
   }
   ```

**Solutions:**

- **Firewall Issues**: Ensure ports 21 (FTP) or 990 (FTPS) are open
- **Network Configuration**: Check if you're behind a corporate firewall
- **Server Status**: Verify the ctFile server is running and accessible
- **DNS Issues**: Try using IP address instead of hostname
- **Timeout Settings**: Increase timeout value in configuration

```php
$config = [
    'host' => '192.168.1.100', // Use IP if DNS fails
    'port' => 21,
    'timeout' => 60, // Increase timeout
    // ... other config
];
```

### Problem: SSL/TLS Connection Failures

**Symptoms:**
- SSL handshake failures
- Certificate verification errors
- Secure connection timeouts

**Solutions:**

1. **Verify SSL Configuration**
   ```php
   $config = [
       'host' => 'secure-ftp.example.com',
       'port' => 990, // FTPS implicit SSL
       'ssl' => true,
       'timeout' => 60,
       // ... other config
   ];
   ```

2. **Test SSL Connectivity**
   ```bash
   openssl s_client -connect secure-ftp.example.com:990
   ```

3. **Handle Certificate Issues**
   ```php
   // For development/testing only - not recommended for production
   $config = [
       'ssl' => true,
       'ssl_verify_peer' => false,
       'ssl_verify_host' => false,
   ];
   ```

### Problem: Passive vs Active Mode Issues

**Symptoms:**
- Data connection failures
- Directory listing timeouts
- File transfer interruptions

**Solutions:**

1. **Try Different Connection Modes**
   ```php
   // Try passive mode (default)
   $config = ['passive' => true];
   
   // If passive fails, try active mode
   $config = ['passive' => false];
   ```

2. **Network Configuration for Passive Mode**
   - Ensure passive port range is open (usually 1024-65535)
   - Configure firewall to allow passive connections

3. **Network Configuration for Active Mode**
   - Ensure client can accept incoming connections
   - Configure NAT/firewall for active FTP

## Authentication Problems

### Problem: Invalid Credentials

**Symptoms:**
- `CtFileAuthenticationException: Invalid credentials`
- Login failures
- Access denied errors

**Diagnostic Steps:**

1. **Verify Credentials**
   ```php
   <?php
   // Test with minimal client
   try {
       $client = new CtFileClient([
           'host' => 'your-server.com',
           'username' => 'testuser',
           'password' => 'testpass',
       ]);
       $client->connect();
       echo "Authentication successful\n";
   } catch (CtFileAuthenticationException $e) {
       echo "Auth failed: " . $e->getMessage() . "\n";
   }
   ```

2. **Check Account Status**
   - Verify account is not locked or expired
   - Check if password needs to be changed
   - Confirm account has necessary permissions

**Solutions:**

- **Credential Verification**: Double-check username and password
- **Special Characters**: Ensure password doesn't contain problematic characters
- **Account Permissions**: Verify account has FTP/ctFile access
- **Password Policies**: Check if password meets server requirements

```php
$config = [
    'username' => trim($username), // Remove whitespace
    'password' => $password, // Ensure no encoding issues
];
```

### Problem: Permission Denied

**Symptoms:**
- Access denied to specific directories
- Cannot create/delete files
- Read-only access when write is needed

**Solutions:**

1. **Check Directory Permissions**
   ```php
   <?php
   try {
       $filesystem->createDirectory('test-permissions');
       echo "Write permissions OK\n";
       $filesystem->deleteDirectory('test-permissions');
   } catch (Exception $e) {
       echo "Permission issue: " . $e->getMessage() . "\n";
   }
   ```

2. **Verify User Permissions**
   - Check if user has read/write permissions
   - Verify user can access the target directory
   - Confirm user is not restricted to specific paths

3. **Use Appropriate Root Path**
   ```php
   $config = [
       'adapter' => [
           'root_path' => '/home/username/', // User's home directory
       ]
   ];
   ```

## File Operation Failures

### Problem: File Upload Failures

**Symptoms:**
- Files not uploading completely
- Upload timeouts
- Corrupted files after upload

**Diagnostic Steps:**

1. **Test with Small Files First**
   ```php
   <?php
   // Test with small file
   $filesystem->write('test-small.txt', 'Hello World');
   
   // Verify content
   $content = $filesystem->read('test-small.txt');
   echo "Content: {$content}\n";
   ```

2. **Check Available Space**
   ```php
   <?php
   try {
       $largeContent = str_repeat('x', 10 * 1024 * 1024); // 10MB
       $filesystem->write('test-large.txt', $largeContent);
       echo "Large file upload successful\n";
   } catch (Exception $e) {
       echo "Large file failed: " . $e->getMessage() . "\n";
   }
   ```

**Solutions:**

- **Increase Timeouts**: For large files, increase timeout settings
- **Use Streaming**: Use `writeStream()` for large files
- **Check Disk Space**: Ensure server has sufficient space
- **File Size Limits**: Check server file size restrictions

```php
// For large files
$stream = fopen('large-file.zip', 'r');
$filesystem->writeStream('uploads/large-file.zip', $stream);
fclose($stream);
```

### Problem: File Download Failures

**Symptoms:**
- Incomplete downloads
- Download timeouts
- Corrupted downloaded files

**Solutions:**

1. **Use Streaming for Large Files**
   ```php
   <?php
   $remoteStream = $filesystem->readStream('large-file.zip');
   $localFile = fopen('downloaded-file.zip', 'w');
   
   while (!feof($remoteStream)) {
       $chunk = fread($remoteStream, 8192);
       fwrite($localFile, $chunk);
   }
   
   fclose($remoteStream);
   fclose($localFile);
   ```

2. **Verify File Integrity**
   ```php
   <?php
   // Compare file sizes
   $remoteSize = $filesystem->fileSize('remote-file.txt');
   $localSize = filesize('local-file.txt');
   
   if ($remoteSize !== $localSize) {
       echo "File size mismatch: remote={$remoteSize}, local={$localSize}\n";
   }
   ```

### Problem: Directory Operations Failing

**Symptoms:**
- Cannot create directories
- Directory listing failures
- Cannot delete directories

**Solutions:**

1. **Check Parent Directory Permissions**
   ```php
   <?php
   // Ensure parent directory exists and is writable
   if (!$filesystem->directoryExists('parent')) {
       $filesystem->createDirectory('parent');
   }
   $filesystem->createDirectory('parent/child');
   ```

2. **Handle Non-Empty Directories**
   ```php
   <?php
   function deleteDirectoryRecursive($filesystem, $path) {
       $contents = $filesystem->listContents($path);
       
       foreach ($contents as $item) {
           if ($item->isFile()) {
               $filesystem->delete($item->path());
           } else {
               deleteDirectoryRecursive($filesystem, $item->path());
           }
       }
       
       $filesystem->deleteDirectory($path);
   }
   ```

## Performance Issues

### Problem: Slow File Operations

**Symptoms:**
- Long response times
- Timeouts on operations
- High CPU/memory usage

**Diagnostic Steps:**

1. **Benchmark Operations**
   ```php
   <?php
   function benchmarkOperation($filesystem, $operation, $path, $data = null) {
       $start = microtime(true);
       
       switch ($operation) {
           case 'write':
               $filesystem->write($path, $data);
               break;
           case 'read':
               $filesystem->read($path);
               break;
           case 'list':
               iterator_to_array($filesystem->listContents($path));
               break;
       }
       
       $duration = microtime(true) - $start;
       echo "{$operation} took " . round($duration * 1000, 2) . "ms\n";
       return $duration;
   }
   
   // Run benchmarks
   benchmarkOperation($filesystem, 'write', 'test.txt', 'test data');
   benchmarkOperation($filesystem, 'read', 'test.txt');
   benchmarkOperation($filesystem, 'list', '/');
   ```

**Solutions:**

1. **Enable Caching**
   ```php
   <?php
   use YangWeijie\FilesystemCtfile\CacheManager;
   use YangWeijie\FilesystemCtfile\Cache\MemoryCache;
   
   $cache = new MemoryCache();
   $cacheManager = new CacheManager($cache, [
       'enabled' => true,
       'ttl' => 600, // 10 minutes
   ]);
   
   $adapter->setCacheManager($cacheManager);
   ```

2. **Optimize Connection Settings**
   ```php
   $config = [
       'timeout' => 30, // Reasonable timeout
       'passive' => true, // Usually faster
   ];
   ```

3. **Use Batch Operations**
   ```php
   <?php
   // Instead of multiple individual operations
   $files = ['file1.txt', 'file2.txt', 'file3.txt'];
   foreach ($files as $file) {
       $filesystem->write($file, 'content'); // Slow
   }
   
   // Use batch processing
   foreach ($files as $file) {
       $operations[] = ['write', $file, 'content'];
   }
   // Process batch with connection reuse
   ```

### Problem: Memory Usage Issues

**Symptoms:**
- High memory consumption
- Out of memory errors
- Memory leaks

**Solutions:**

1. **Use Streaming for Large Files**
   ```php
   <?php
   // Instead of loading entire file into memory
   $content = $filesystem->read('huge-file.txt'); // Memory intensive
   
   // Use streaming
   $stream = $filesystem->readStream('huge-file.txt');
   while (!feof($stream)) {
       $chunk = fread($stream, 8192);
       // Process chunk
   }
   fclose($stream);
   ```

2. **Process Files in Chunks**
   ```php
   <?php
   function processLargeFileInChunks($filesystem, $path, $chunkSize = 8192) {
       $stream = $filesystem->readStream($path);
       $processedBytes = 0;
       
       while (!feof($stream)) {
           $chunk = fread($stream, $chunkSize);
           // Process chunk
           $processedBytes += strlen($chunk);
           
           // Optional: garbage collection for long-running processes
           if ($processedBytes % (1024 * 1024) === 0) { // Every MB
               gc_collect_cycles();
           }
       }
       
       fclose($stream);
   }
   ```

## Configuration Problems

### Problem: Invalid Configuration

**Symptoms:**
- `CtFileConfigurationException` errors
- Validation failures
- Unexpected behavior

**Diagnostic Steps:**

1. **Validate Configuration**
   ```php
   <?php
   use YangWeijie\FilesystemCtfile\ConfigurationManager;
   
   try {
       $configManager = new ConfigurationManager($config);
       $configManager->validate();
       echo "Configuration is valid\n";
   } catch (CtFileConfigurationException $e) {
       echo "Configuration error: " . $e->getMessage() . "\n";
   }
   ```

2. **Check Required Fields**
   ```php
   <?php
   $requiredFields = ['ctfile.host', 'ctfile.username', 'ctfile.password'];
   
   foreach ($requiredFields as $field) {
       $value = $configManager->get($field);
       if (empty($value)) {
           echo "Missing required field: {$field}\n";
       }
   }
   ```

**Solutions:**

1. **Use Configuration Validation**
   ```php
   <?php
   $config = [
       'ctfile' => [
           'host' => 'required-host.com',
           'port' => 21, // Must be integer
           'username' => 'required-username',
           'password' => 'required-password',
           'timeout' => 30, // Must be positive integer
           'ssl' => false, // Must be boolean
       ]
   ];
   ```

2. **Handle Environment Variables**
   ```php
   <?php
   $config = [
       'ctfile' => [
           'host' => $_ENV['CTFILE_HOST'] ?? throw new RuntimeException('CTFILE_HOST required'),
           'username' => $_ENV['CTFILE_USERNAME'] ?? throw new RuntimeException('CTFILE_USERNAME required'),
           'password' => $_ENV['CTFILE_PASSWORD'] ?? throw new RuntimeException('CTFILE_PASSWORD required'),
       ]
   ];
   ```

### Problem: Path Configuration Issues

**Symptoms:**
- Files created in wrong locations
- Path not found errors
- Permission denied on paths

**Solutions:**

1. **Verify Root Path Configuration**
   ```php
   <?php
   $config = [
       'adapter' => [
           'root_path' => '/correct/path/', // Ensure trailing slash
           'case_sensitive' => true, // Match server settings
       ]
   ];
   ```

2. **Test Path Resolution**
   ```php
   <?php
   use YangWeijie\FilesystemCtfile\Utilities\PathNormalizer;
   
   $testPaths = ['file.txt', 'dir/file.txt', '../file.txt'];
   
   foreach ($testPaths as $path) {
       $normalized = PathNormalizer::normalize($path);
       $isValid = PathNormalizer::validate($path);
       echo "Path: {$path} -> {$normalized} (valid: " . ($isValid ? 'yes' : 'no') . ")\n";
   }
   ```

## Memory and Resource Issues

### Problem: Connection Leaks

**Symptoms:**
- Too many open connections
- Connection pool exhaustion
- Resource limit errors

**Solutions:**

1. **Proper Connection Management**
   ```php
   <?php
   class ConnectionManager {
       private $client;
       
       public function __construct($config) {
           $this->client = new CtFileClient($config);
       }
       
       public function getFilesystem() {
           if (!$this->client->isConnected()) {
               $this->client->connect();
           }
           return new Filesystem(new CtFileAdapter($this->client));
       }
       
       public function __destruct() {
           if ($this->client && $this->client->isConnected()) {
               $this->client->disconnect();
           }
       }
   }
   ```

2. **Connection Pooling**
   ```php
   <?php
   class ConnectionPool {
       private $connections = [];
       private $config;
       private $maxConnections = 5;
       
       public function getConnection() {
           foreach ($this->connections as $connection) {
               if (!$connection->isInUse()) {
                   return $connection;
               }
           }
           
           if (count($this->connections) < $this->maxConnections) {
               $connection = new CtFileClient($this->config);
               $this->connections[] = $connection;
               return $connection;
           }
           
           throw new RuntimeException('Connection pool exhausted');
       }
   }
   ```

## Error Messages Reference

### Common Error Messages and Solutions

| Error Message | Cause | Solution |
|---------------|-------|----------|
| `Connection refused` | Server not running or firewall blocking | Check server status and firewall rules |
| `Authentication failed` | Wrong credentials | Verify username and password |
| `Permission denied` | Insufficient permissions | Check user permissions on server |
| `No such file or directory` | File/directory doesn't exist | Verify path and create if necessary |
| `Disk quota exceeded` | Server storage full | Free up space or contact administrator |
| `Connection timed out` | Network issues or slow server | Increase timeout or check network |
| `SSL handshake failed` | SSL/TLS configuration issue | Check SSL settings and certificates |
| `Passive mode failed` | Firewall blocking passive ports | Try active mode or configure firewall |

### Exception Hierarchy

```
FilesystemException (Flysystem)
├── UnableToReadFile
├── UnableToWriteFile
├── UnableToDeleteFile
├── UnableToCreateDirectory
└── ...

CtFileException (Package-specific)
├── CtFileConnectionException
├── CtFileAuthenticationException
├── CtFileOperationException
└── CtFileConfigurationException
```

## Debugging Tools

### Debug Mode

```php
<?php
class DebugAdapter extends CtFileAdapter {
    public function write(string $path, string $contents, Config $config): void {
        echo "[DEBUG] Writing {$path} (" . strlen($contents) . " bytes)\n";
        $start = microtime(true);
        
        try {
            parent::write($path, $contents, $config);
            $duration = microtime(true) - $start;
            echo "[DEBUG] Write successful in " . round($duration * 1000, 2) . "ms\n";
        } catch (Exception $e) {
            $duration = microtime(true) - $start;
            echo "[DEBUG] Write failed after " . round($duration * 1000, 2) . "ms: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}
```

### Connection Diagnostics

```php
<?php
function diagnoseConnection($config) {
    echo "=== Connection Diagnostics ===\n";
    
    // Test basic connectivity
    $host = $config['host'];
    $port = $config['port'];
    
    echo "Testing connectivity to {$host}:{$port}...\n";
    $socket = @fsockopen($host, $port, $errno, $errstr, 10);
    
    if (!$socket) {
        echo "❌ Connection failed: {$errstr} ({$errno})\n";
        return false;
    }
    
    echo "✅ Basic connectivity OK\n";
    fclose($socket);
    
    // Test ctFile client
    try {
        echo "Testing ctFile client connection...\n";
        $client = new CtFileClient($config);
        $client->connect();
        echo "✅ ctFile connection successful\n";
        
        // Test basic operations
        echo "Testing basic operations...\n";
        $client->listFiles('/');
        echo "✅ Basic operations working\n";
        
        $client->disconnect();
        return true;
        
    } catch (Exception $e) {
        echo "❌ ctFile test failed: " . $e->getMessage() . "\n";
        return false;
    }
}
```

### Performance Profiler

```php
<?php
class PerformanceProfiler {
    private $operations = [];
    
    public function start($operation) {
        $this->operations[$operation] = microtime(true);
    }
    
    public function end($operation) {
        if (isset($this->operations[$operation])) {
            $duration = microtime(true) - $this->operations[$operation];
            echo "[PROFILE] {$operation}: " . round($duration * 1000, 2) . "ms\n";
            unset($this->operations[$operation]);
            return $duration;
        }
        return null;
    }
    
    public function profile($operation, callable $callback) {
        $this->start($operation);
        try {
            $result = $callback();
            $this->end($operation);
            return $result;
        } catch (Exception $e) {
            $this->end($operation);
            throw $e;
        }
    }
}

// Usage
$profiler = new PerformanceProfiler();

$result = $profiler->profile('file_write', function() use ($filesystem) {
    return $filesystem->write('test.txt', 'content');
});
```

## Getting Help

### Before Seeking Help

1. **Check this troubleshooting guide**
2. **Review the [API Documentation](api-documentation.md)**
3. **Try the [Usage Examples](usage-examples.md)**
4. **Enable debug mode and collect logs**
5. **Test with minimal configuration**

### Information to Include

When reporting issues, please include:

- **PHP version** and operating system
- **Package version** (`composer show yangweijie/filesystem-ctfile`)
- **Complete error message** and stack trace
- **Configuration** (sanitized, without credentials)
- **Minimal code example** that reproduces the issue
- **Server information** (ctFile server type, version if known)
- **Network environment** (firewall, NAT, proxy settings)

### Diagnostic Script

```php
<?php
// diagnostic.php - Run this script to collect system information

echo "=== System Diagnostics ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "OS: " . PHP_OS . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "\n";

echo "\n=== Package Information ===\n";
if (class_exists('YangWeijie\FilesystemCtfile\CtFileAdapter')) {
    echo "✅ Package loaded successfully\n";
} else {
    echo "❌ Package not found\n";
}

echo "\n=== Network Test ===\n";
$testHosts = ['google.com', 'github.com'];
foreach ($testHosts as $host) {
    $socket = @fsockopen($host, 80, $errno, $errstr, 5);
    if ($socket) {
        echo "✅ {$host}: Reachable\n";
        fclose($socket);
    } else {
        echo "❌ {$host}: Not reachable ({$errstr})\n";
    }
}

echo "\n=== Extensions ===\n";
$requiredExtensions = ['openssl', 'ftp'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ {$ext}: Loaded\n";
    } else {
        echo "❌ {$ext}: Not loaded\n";
    }
}
```

### Support Channels

- **GitHub Issues**: For bug reports and feature requests
- **Documentation**: Check the official documentation
- **Community Forums**: Search for similar issues
- **Stack Overflow**: Tag questions with relevant tags

Remember to always sanitize configuration data and never share credentials when seeking help!