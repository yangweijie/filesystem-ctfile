# Configuration Guide

## Overview

The CTFile Flysystem Adapter uses the `CTFileConfig` class to manage all configuration options. This guide covers all available configuration parameters and their usage.

## Basic Configuration

### Minimal Configuration

```php
use Yangweijie\FilesystemCtlife\CTFileConfig;

$config = new CTFileConfig([
    'session' => 'your-ctfile-session-token',
    'app_id' => 'your-ctfile-app-id',
]);
```

### Complete Configuration

```php
$config = new CTFileConfig([
    // Required parameters
    'session' => 'your-ctfile-session-token',
    'app_id' => 'your-ctfile-app-id',
    
    // API endpoints
    'api_base_url' => 'https://webapi.ctfile.com',
    'upload_base_url' => 'https://upload.ctfile.com',
    
    // Performance settings
    'timeout' => 30,
    'retry_attempts' => 3,
    'cache_ttl' => 3600,
    
    // Storage settings
    'storage_type' => 'public',
    
    // Security settings
    'verify_ssl' => true,
]);
```

## Configuration Parameters

### Required Parameters

#### `session` (string)
Your CTFile session token for authentication.

```php
'session' => 'your-session-token-here'
```

**How to obtain:**
1. Log in to your CTFile account
2. Navigate to API settings
3. Generate or copy your session token

#### `app_id` (string)
Your CTFile application identifier.

```php
'app_id' => 'your-app-id-here'
```

### API Configuration

#### `api_base_url` (string)
Base URL for CTFile API endpoints.

- **Default**: `https://webapi.ctfile.com`
- **Alternative**: `https://rest.ctfile.com/v1`

```php
'api_base_url' => 'https://webapi.ctfile.com'
```

#### `upload_base_url` (string)
Base URL for file upload operations.

- **Default**: `https://upload.ctfile.com`

```php
'upload_base_url' => 'https://upload.ctfile.com'
```

### Performance Configuration

#### `timeout` (integer)
Request timeout in seconds.

- **Default**: `30`
- **Range**: `5` to `300`
- **Recommendation**: `60` for large files, `30` for regular operations

```php
'timeout' => 60
```

#### `retry_attempts` (integer)
Number of retry attempts for failed requests.

- **Default**: `3`
- **Range**: `0` to `10`
- **Recommendation**: `3-5` for production

```php
'retry_attempts' => 5
```

#### `cache_ttl` (integer)
Path mapping cache time-to-live in seconds.

- **Default**: `3600` (1 hour)
- **Range**: `60` to `86400` (1 day)
- **Recommendation**: `7200` (2 hours) for active usage

```php
'cache_ttl' => 7200
```

### Storage Configuration

#### `storage_type` (string)
CTFile storage type.

- **Default**: `public`
- **Options**: `public`, `private`

```php
'storage_type' => 'private'
```

**Differences:**
- **Public**: Files are publicly accessible via direct URLs
- **Private**: Files require authentication to access

### Security Configuration

#### `verify_ssl` (boolean)
Whether to verify SSL certificates.

- **Default**: `true`
- **Production**: Always `true`
- **Development**: Can be `false` for testing

```php
'verify_ssl' => true
```

## Environment-Based Configuration

### Using Environment Variables

```php
$config = new CTFileConfig([
    'session' => $_ENV['CTFILE_SESSION'] ?? getenv('CTFILE_SESSION'),
    'app_id' => $_ENV['CTFILE_APP_ID'] ?? getenv('CTFILE_APP_ID'),
    'api_base_url' => $_ENV['CTFILE_API_URL'] ?? 'https://webapi.ctfile.com',
    'timeout' => (int)($_ENV['CTFILE_TIMEOUT'] ?? 30),
    'retry_attempts' => (int)($_ENV['CTFILE_RETRY_ATTEMPTS'] ?? 3),
]);
```

### Environment File (.env)

```env
CTFILE_SESSION=your-session-token
CTFILE_APP_ID=your-app-id
CTFILE_API_URL=https://webapi.ctfile.com
CTFILE_TIMEOUT=60
CTFILE_RETRY_ATTEMPTS=5
CTFILE_CACHE_TTL=7200
```

## Configuration Validation

The `CTFileConfig` class automatically validates configuration parameters:

### Validation Rules

```php
try {
    $config = new CTFileConfig([
        'session' => 'invalid-session',  // Too short
        'app_id' => '',                  // Empty
        'timeout' => -1,                 // Invalid range
    ]);
} catch (InvalidArgumentException $e) {
    echo "Configuration error: " . $e->getMessage();
}
```

### Common Validation Errors

- **Empty session**: Session token cannot be empty
- **Invalid app_id**: App ID must be a non-empty string
- **Invalid timeout**: Timeout must be between 5 and 300 seconds
- **Invalid retry_attempts**: Must be between 0 and 10
- **Invalid storage_type**: Must be 'public' or 'private'

## Advanced Configuration

### Custom HTTP Headers

```php
$config = new CTFileConfig([
    'session' => 'your-session',
    'app_id' => 'your-app-id',
    'custom_headers' => [
        'User-Agent' => 'MyApp/1.0',
        'X-Custom-Header' => 'custom-value',
    ],
]);
```

### Proxy Configuration

```php
$config = new CTFileConfig([
    'session' => 'your-session',
    'app_id' => 'your-app-id',
    'proxy' => [
        'host' => 'proxy.example.com',
        'port' => 8080,
        'username' => 'proxy-user',
        'password' => 'proxy-pass',
    ],
]);
```

## Configuration Best Practices

### 1. Security
- Never hardcode credentials in source code
- Use environment variables or secure configuration files
- Rotate session tokens regularly
- Always verify SSL in production

### 2. Performance
- Set appropriate timeouts based on your use case
- Use longer cache TTL for frequently accessed paths
- Adjust retry attempts based on network reliability

### 3. Monitoring
- Log configuration validation errors
- Monitor API response times
- Track retry attempt usage

### 4. Environment-Specific Settings

#### Development
```php
$config = new CTFileConfig([
    'session' => 'dev-session',
    'app_id' => 'dev-app-id',
    'timeout' => 10,
    'retry_attempts' => 1,
    'cache_ttl' => 300,
    'verify_ssl' => false,
]);
```

#### Production
```php
$config = new CTFileConfig([
    'session' => $_ENV['CTFILE_SESSION'],
    'app_id' => $_ENV['CTFILE_APP_ID'],
    'timeout' => 60,
    'retry_attempts' => 5,
    'cache_ttl' => 7200,
    'verify_ssl' => true,
]);
```

## Configuration Methods

### Getting Configuration Values

```php
$config = new CTFileConfig([...]);

// Get individual values
$session = $config->getSession();
$appId = $config->getAppId();
$timeout = $config->getTimeout();

// Get all configuration as array
$allConfig = $config->toArray();

// Check storage type
if ($config->isPublicStorage()) {
    // Handle public storage
} else {
    // Handle private storage
}
```

### Dynamic Configuration Updates

```php
// Configuration is immutable after creation
// Create new instance for different settings
$prodConfig = new CTFileConfig([
    'session' => $_ENV['PROD_SESSION'],
    'app_id' => $_ENV['PROD_APP_ID'],
]);

$testConfig = new CTFileConfig([
    'session' => $_ENV['TEST_SESSION'],
    'app_id' => $_ENV['TEST_APP_ID'],
]);
```

## Troubleshooting Configuration

### Common Issues

1. **Invalid credentials**: Verify session token and app ID
2. **Network timeouts**: Increase timeout value
3. **SSL errors**: Check certificate validity or disable SSL verification for testing
4. **Cache issues**: Reduce cache TTL or clear cache

### Debug Configuration

```php
$config = new CTFileConfig([...]);

// Debug output
var_dump($config->toArray());

// Test configuration
try {
    $adapter = new CTFileAdapter($config);
    echo "Configuration is valid!";
} catch (Exception $e) {
    echo "Configuration error: " . $e->getMessage();
}
```
