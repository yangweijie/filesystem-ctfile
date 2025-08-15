# CTFile Adapter for League Flysystem

[![Latest Version on Packagist](https://img.shields.io/packagist/v/yangweijie/filesystem-ctlife.svg?style=flat-square)](https://packagist.org/packages/yangweijie/filesystem-ctlife)
[![Total Downloads](https://img.shields.io/packagist/dt/yangweijie/filesystem-ctlife.svg?style=flat-square)](https://packagist.org/packages/yangweijie/filesystem-ctlife)
[![License](https://img.shields.io/packagist/l/yangweijie/filesystem-ctlife.svg?style=flat-square)](https://packagist.org/packages/yangweijie/filesystem-ctlife)

A complete filesystem abstraction for CTFile cloud storage, built on top of League Flysystem.

## Features

- **Complete Flysystem Integration**: Implements all standard filesystem operations
- **CTFile API Support**: Full integration with CTFile's REST API
- **Two-Step Upload Process**: Handles CTFile's unique upload workflow
- **Dynamic Download Links**: Manages temporary download URLs
- **Path Mapping**: Seamless conversion between Flysystem paths and CTFile IDs
- **Error Handling**: Comprehensive exception mapping and retry mechanisms
- **Public & Private Cloud**: Supports both CTFile storage modes
- **Stream Support**: Full support for PHP streams and large file handling
- **Caching**: Intelligent path mapping cache for improved performance
- **Rate Limiting**: Built-in rate limit handling with retry mechanisms
- **Extensive Testing**: Comprehensive test suite with unit, integration, and performance tests

## Installation

You can install the package via composer:

```bash
composer require yangweijie/filesystem-ctlife
```

## Requirements

- PHP 8.0 or higher
- League Flysystem 2.5+ or 3.0+
- cURL extension
- JSON extension

## Basic Usage

```php
use League\Flysystem\Filesystem;
use Yangweijie\FilesystemCtlife\CTFileAdapter;
use Yangweijie\FilesystemCtlife\CTFileConfig;

// Configure the adapter
$config = new CTFileConfig([
    'session' => 'your_session_token',
    'app_id' => 'your_app_identifier',
    'storage_type' => 'public', // or 'private'
]);

// Create the adapter and filesystem
$adapter = new CTFileAdapter($config);
$filesystem = new Filesystem($adapter);

// Use standard Flysystem operations
$filesystem->write('path/to/file.txt', 'Hello World!');
$content = $filesystem->read('path/to/file.txt');
$filesystem->createDirectory('new-folder');
$listing = $filesystem->listContents('/', true);
```

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `session` | string | required | CTFile session token |
| `app_id` | string | required | Application identifier |
| `api_base_url` | string | `https://rest.ctfile.com/v1` | CTFile API base URL |
| `upload_base_url` | string | `https://upload.ctfile.com` | Upload service URL |
| `storage_type` | string | `public` | Storage type: `public` or `private` |
| `cache_ttl` | int | `3600` | Path mapping cache TTL in seconds |
| `retry_attempts` | int | `3` | Number of retry attempts for failed requests |
| `timeout` | int | `30` | Request timeout in seconds |
| `connect_timeout` | int | `10` | Connection timeout in seconds |

## Documentation

- [Installation Guide](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Usage Examples](docs/usage.md)
- [API Reference](docs/api.md)

## Testing

```bash
# Run unit tests
composer test

# Run tests with coverage
composer test-coverage

# Run integration tests (requires CTFile credentials)
CTFILE_SESSION=your-session CTFILE_APP_ID=your-app-id composer test-integration

# Run performance tests
composer test-performance

# Run all tests
composer test-all
```

### Setting up Integration Tests

To run integration tests, you need to provide CTFile credentials:

```bash
export CTFILE_SESSION="your-session-token"
export CTFILE_APP_ID="your-app-id"
export CTFILE_API_URL="https://webapi.ctfile.com"  # Optional
```

## Error Handling

The adapter provides comprehensive error handling with specific exception types:

```php
use Yangweijie\FilesystemCtlife\Exceptions\AuthenticationException;
use Yangweijie\FilesystemCtlife\Exceptions\NetworkException;
use Yangweijie\FilesystemCtlife\Exceptions\RateLimitException;

try {
    $filesystem->write('test.txt', 'content');
} catch (AuthenticationException $e) {
    echo "Authentication failed: " . $e->getMessage();
} catch (NetworkException $e) {
    echo "Network error: " . $e->getMessage();
} catch (RateLimitException $e) {
    echo "Rate limited. Retry after: " . $e->getRetryAfter() . " seconds";
}
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email 917647288@qq.com instead of using the issue tracker.

## Credits

- [yangweijie](https://github.com/yangweijie)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
