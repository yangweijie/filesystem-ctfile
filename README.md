# Filesystem ctFile Extension

A PHP filesystem extension package that integrates ctFile functionality with the Flysystem filesystem abstraction library.

## Features

- Full Flysystem adapter implementation for ctFile
- Enhanced file management capabilities through ctFile integration
- Comprehensive error handling and logging
- PSR-3 logging support
- Configurable caching and retry mechanisms
- Extensive test coverage with Pest

## Requirements

- PHP 8.1 or higher
- Composer
- ctFile library/API access

## Installation

### Via Composer

Install the package via Composer:

```bash
composer require yangweijie/filesystem-ctfile
```

### Requirements Check

Ensure your system meets the requirements:

```bash
# Check PHP version
php --version

# Verify required extensions are installed
php -m | grep -E "(json|mbstring|openssl)"
```

### Verify Installation

```php
<?php
require_once 'vendor/autoload.php';

use YangWeijie\FilesystemCtfile\CtFileAdapter;

// Verify the adapter class is available
if (class_exists(CtFileAdapter::class)) {
    echo "Installation successful!" . PHP_EOL;
} else {
    echo "Installation failed!" . PHP_EOL;
}
```

## Basic Usage

### Quick Start

```php
<?php

use League\Flysystem\Filesystem;
use YangWeijie\FilesystemCtfile\CtFileAdapter;

// Configure the ctFile adapter
$config = [
    'ctfile' => [
        'host' => 'your-ctfile-host.com',
        'port' => 21,
        'username' => 'your-username',
        'password' => 'your-password',
        'ssl' => false,
        'passive' => true,
    ],
];

// Create the adapter and filesystem
$adapter = new CtFileAdapter($config);
$filesystem = new Filesystem($adapter);

// Use standard Flysystem operations
$filesystem->write('path/to/file.txt', 'Hello, World!');
$content = $filesystem->read('path/to/file.txt');
$exists = $filesystem->fileExists('path/to/file.txt');
$filesystem->delete('path/to/file.txt');
```

### Configuration Options

```php
$config = [
    'ctfile' => [
        'host' => 'your-ctfile-host.com',      // Required: ctFile server host
        'port' => 21,                          // Optional: Server port (default: 21)
        'username' => 'your-username',         // Required: Authentication username
        'password' => 'your-password',         // Required: Authentication password
        'timeout' => 30,                       // Optional: Connection timeout (default: 30)
        'ssl' => false,                        // Optional: Use SSL connection (default: false)
        'passive' => true,                     // Optional: Use passive mode (default: true)
    ],
    'adapter' => [
        'root_path' => '/',                    // Optional: Root path prefix (default: /)
        'path_separator' => '/',               // Optional: Path separator (default: /)
        'case_sensitive' => true,              // Optional: Case sensitive paths (default: true)
        'create_directories' => true,          // Optional: Auto-create directories (default: true)
    ],
    'logging' => [
        'enabled' => false,                    // Optional: Enable logging (default: false)
        'level' => 'info',                     // Optional: Log level (default: info)
        'channel' => 'filesystem-ctfile',      // Optional: Log channel (default: filesystem-ctfile)
    ],
    'cache' => [
        'enabled' => false,                    // Optional: Enable caching (default: false)
        'ttl' => 300,                          // Optional: Cache TTL in seconds (default: 300)
        'driver' => 'memory',                  // Optional: Cache driver (default: memory)
    ],
];
```

### Directory Operations

```php
// Create directories
$filesystem->createDirectory('new/directory');

// List directory contents
$listing = $filesystem->listContents('path/to/directory');
foreach ($listing as $item) {
    echo $item->path() . ' - ' . $item->type() . PHP_EOL;
}

// Check if directory exists
if ($filesystem->directoryExists('path/to/directory')) {
    // Directory exists
}

// Delete directory
$filesystem->deleteDirectory('path/to/directory');
```

### File Operations

```php
// Write files
$filesystem->write('file.txt', 'content');
$filesystem->writeStream('large-file.txt', $stream);

// Read files
$content = $filesystem->read('file.txt');
$stream = $filesystem->readStream('large-file.txt');

// File metadata
$size = $filesystem->fileSize('file.txt');
$mimeType = $filesystem->mimeType('file.txt');
$lastModified = $filesystem->lastModified('file.txt');

// Copy and move files
$filesystem->copy('source.txt', 'destination.txt');
$filesystem->move('old-name.txt', 'new-name.txt');
```

## Advanced Usage

### Error Handling

```php
use League\Flysystem\UnableToWriteFile;
use YangWeijie\FilesystemCtfile\Exceptions\CtFileException;

try {
    $filesystem->write('protected/file.txt', 'content');
} catch (UnableToWriteFile $e) {
    // Handle Flysystem exceptions
    echo "Failed to write file: " . $e->getMessage();
} catch (CtFileException $e) {
    // Handle ctFile-specific exceptions
    echo "ctFile error: " . $e->getMessage();
}
```

### Custom Configuration

```php
use YangWeijie\FilesystemCtfile\CtFileClient;
use YangWeijie\FilesystemCtfile\CtFileAdapter;
use YangWeijie\FilesystemCtfile\ConfigurationManager;

// Advanced configuration with custom client
$config = new ConfigurationManager([
    'ctfile' => [
        'host' => 'ctfile.example.com',
        'username' => 'user',
        'password' => 'pass',
        'timeout' => 60,
    ],
    'adapter' => [
        'root_path' => '/app/files',
        'create_directories' => true,
    ],
]);

$client = new CtFileClient($config->get('ctfile'));
$adapter = new CtFileAdapter($client, $config->get('adapter'));
```

### Logging Integration

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create a logger
$logger = new Logger('ctfile');
$logger->pushHandler(new StreamHandler('ctfile.log', Logger::INFO));

// Configure adapter with logging
$config['logging'] = [
    'enabled' => true,
    'level' => 'info',
];

$adapter = new CtFileAdapter($config);
$adapter->setLogger($logger);
```

## Troubleshooting

### Common Issues

1. **Connection Timeout**
   ```php
   // Increase timeout in configuration
   $config['ctfile']['timeout'] = 120;
   ```

2. **Permission Denied**
   ```php
   // Check credentials and permissions
   $config['ctfile']['username'] = 'correct-username';
   $config['ctfile']['password'] = 'correct-password';
   ```

3. **Path Not Found**
   ```php
   // Ensure directories exist or enable auto-creation
   $config['adapter']['create_directories'] = true;
   ```

### Debug Mode

```php
// Enable debug logging
$config['logging'] = [
    'enabled' => true,
    'level' => 'debug',
];
```

For more troubleshooting information, see [docs/troubleshooting-guide.md](docs/troubleshooting-guide.md).

## Documentation

- [API Documentation](docs/api-documentation.md)
- [Usage Examples](docs/usage-examples.md)
- [Troubleshooting Guide](docs/troubleshooting-guide.md)

## Development

### Setting Up Development Environment

```bash
# Clone the repository
git clone https://github.com/yangweijie/filesystem-ctfile.git
cd filesystem-ctfile

# Install dependencies
composer install

# Run tests to verify setup
composer test
```

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test suite
./vendor/bin/pest tests/Unit
./vendor/bin/pest tests/Integration

# Run specific test file
./vendor/bin/pest tests/Unit/CtFileAdapterTest.php
```

### Code Quality

```bash
# Run static analysis
composer analyse

# Check code style
composer cs-check

# Fix code style
composer cs-fix

# Run all quality checks
composer quality
```

### Building Documentation

```bash
# Generate API documentation (if phpDocumentor is installed)
phpdoc -d src -t docs/api
```

## Contributing

We welcome contributions! Please follow these steps:

### Development Workflow

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Add tests for new functionality
5. Run the test suite (`composer test`)
6. Run code quality checks (`composer quality`)
7. Commit your changes (`git commit -m 'Add some amazing feature'`)
8. Push to the branch (`git push origin feature/amazing-feature`)
9. Open a Pull Request

### Coding Standards

- Follow PSR-12 coding standards
- Add PHPDoc comments for all public methods
- Write tests for new functionality
- Ensure all tests pass
- Maintain backward compatibility

### Pull Request Guidelines

- Provide a clear description of the changes
- Reference any related issues
- Include tests for new features
- Update documentation as needed
- Ensure CI checks pass

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

If you encounter any issues or have questions, please [open an issue](https://github.com/yangweijie/filesystem-ctfile/issues) on GitHub.