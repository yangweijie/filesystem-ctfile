# Installation Guide

## System Requirements

Before installing the CTFile Flysystem Adapter, ensure your system meets the following requirements:

### PHP Requirements
- **PHP Version**: 8.0 or higher
- **Extensions**:
  - `ext-curl`: Required for HTTP requests to CTFile API
  - `ext-json`: Required for JSON data processing
  - `ext-mbstring`: Recommended for proper string handling

### Dependencies
- **League Flysystem**: Version 2.5.0 or 3.0.0+
- **Composer**: For package management

## Installation Methods

### Method 1: Composer (Recommended)

Install the package via Composer:

```bash
composer require yangweijie/filesystem-ctlife
```

### Method 2: Manual Installation

1. Download the package from GitHub:
```bash
git clone https://github.com/yangweijie/filesystem-ctlife.git
```

2. Install dependencies:
```bash
cd filesystem-ctlife
composer install
```

3. Include the autoloader in your project:
```php
require_once 'vendor/autoload.php';
```

## Verification

After installation, verify that the package is working correctly:

```php
<?php
require_once 'vendor/autoload.php';

use Yangweijie\FilesystemCtlife\CTFileConfig;
use Yangweijie\FilesystemCtlife\CTFileAdapter;

// Test basic instantiation
try {
    $config = new CTFileConfig([
        'session' => 'test-session',
        'app_id' => 'test-app-id',
    ]);
    
    $adapter = new CTFileAdapter($config);
    echo "✅ CTFile Adapter installed successfully!\n";
} catch (Exception $e) {
    echo "❌ Installation error: " . $e->getMessage() . "\n";
}
```

## CTFile Account Setup

To use this adapter, you need a CTFile account and API credentials:

### 1. Create CTFile Account
1. Visit [CTFile.com](https://www.ctfile.com/)
2. Register for an account
3. Verify your email address

### 2. Obtain API Credentials
1. Log in to your CTFile account
2. Navigate to API settings or developer section
3. Generate or obtain your:
   - **Session Token**: Your authentication token
   - **App ID**: Your application identifier

### 3. Test API Access
```bash
# Test your credentials with a simple curl request
curl -H "Authorization: Bearer YOUR_SESSION_TOKEN" \
     -H "X-App-ID: YOUR_APP_ID" \
     https://webapi.ctfile.com/v1/user/info
```

## Development Environment Setup

For development and testing:

### 1. Clone the Repository
```bash
git clone https://github.com/yangweijie/filesystem-ctlife.git
cd filesystem-ctlife
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Set Up Environment Variables
Create a `.env` file or set environment variables:
```bash
export CTFILE_SESSION="your-session-token"
export CTFILE_APP_ID="your-app-id"
export CTFILE_API_URL="https://webapi.ctfile.com"
```

### 4. Run Tests
```bash
# Run unit tests
composer test

# Run integration tests (requires credentials)
composer test-integration
```

## Troubleshooting

### Common Issues

**Issue**: `Class 'Yangweijie\FilesystemCtlife\CTFileAdapter' not found`
**Solution**: Ensure Composer autoloader is included:
```php
require_once 'vendor/autoload.php';
```

**Issue**: `cURL error: SSL certificate problem`
**Solution**: Update your CA certificates or disable SSL verification (not recommended for production):
```php
$config = new CTFileConfig([
    'session' => 'your-session',
    'app_id' => 'your-app-id',
    'verify_ssl' => false, // Only for development
]);
```

**Issue**: `Authentication failed`
**Solution**: Verify your CTFile credentials:
1. Check session token is valid and not expired
2. Ensure app ID is correct
3. Test credentials with CTFile API directly

### Getting Help

If you encounter issues:

1. **Check the documentation**: Review all documentation files
2. **Search existing issues**: Check [GitHub Issues](https://github.com/yangweijie/filesystem-ctlife/issues)
3. **Create a new issue**: Provide detailed information about your problem
4. **Contact support**: Email the maintainer at 917647288@qq.com

## Next Steps

After successful installation:

1. Read the [Configuration Guide](configuration.md)
2. Review [Usage Examples](usage.md)
3. Check the [API Reference](api.md)
4. Explore the [examples directory](../examples/)

## Performance Considerations

For production environments:

1. **Enable OPcache**: Improves PHP performance
2. **Use Redis/Memcached**: For path mapping cache
3. **Configure timeouts**: Adjust based on your network conditions
4. **Monitor API limits**: Be aware of CTFile rate limits

```php
$config = new CTFileConfig([
    'session' => 'your-session',
    'app_id' => 'your-app-id',
    'timeout' => 60,           // Increase for slow networks
    'retry_attempts' => 5,     // More retries for reliability
    'cache_ttl' => 7200,      // Longer cache for better performance
]);
```
