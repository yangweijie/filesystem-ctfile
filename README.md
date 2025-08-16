# Filesystem ctFile Extension

[![Latest Stable Version](https://poser.pugx.org/yangweijie/filesystem-ctfile/v/stable)](https://packagist.org/packages/yangweijie/filesystem-ctfile)
[![Total Downloads](https://poser.pugx.org/yangweijie/filesystem-ctfile/downloads)](https://packagist.org/packages/yangweijie/filesystem-ctfile)
[![License](https://poser.pugx.org/yangweijie/filesystem-ctfile/license)](https://packagist.org/packages/yangweijie/filesystem-ctfile)
[![PHP Version Require](https://poser.pugx.org/yangweijie/filesystem-ctfile/require/php)](https://packagist.org/packages/yangweijie/filesystem-ctfile)

A PHP filesystem extension package that integrates ctFile functionality with the Flysystem filesystem abstraction library.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Basic Usage](#basic-usage)
- [Advanced Usage](#advanced-usage)
- [Troubleshooting](#troubleshooting)
- [Documentation](#documentation)
- [Development](#development)
- [Contributing](#contributing)
- [License](#license)
- [Support](#support)

## Features

- Full Flysystem adapter implementation for ctFile
- Enhanced file management capabilities through ctFile integration
- Comprehensive error handling and logging
- PSR-3 logging support
- Configurable caching and retry mechanisms
- Extensive test coverage with Pest

## Requirements

### System Requirements

- **PHP**: 8.1 or higher
- **Composer**: 2.0 or higher
- **Extensions**: json, mbstring, openssl (typically included in PHP)
- **ctFile**: Access to ctFile server/API

### Supported Versions

- **PHP**: 8.1, 8.2, 8.3+
- **Flysystem**: 3.x
- **Operating Systems**: Linux, macOS, Windows

## Installation

### Step 1: Install via Composer

Install the package using Composer:

```bash
composer require yangweijie/filesystem-ctfile
```

### Step 2: Verify System Requirements

Check that your system meets all requirements:

```bash
# Check PHP version (must be 8.1+)
php --version

# Check Composer version (must be 2.0+)
composer --version

# Verify required PHP extensions
php -m | grep -E "(json|mbstring|openssl)"

# Check if all extensions are loaded
php -r "
echo 'JSON: ' . (extension_loaded('json') ? 'OK' : 'MISSING') . PHP_EOL;
echo 'MBString: ' . (extension_loaded('mbstring') ? 'OK' : 'MISSING') . PHP_EOL;
echo 'OpenSSL: ' . (extension_loaded('openssl') ? 'OK' : 'MISSING') . PHP_EOL;
"
```

### Step 3: Verify Installation

Create a test script to verify the installation:

```php
<?php
// test-installation.php
require_once 'vendor/autoload.php';

use YangWeijie\FilesystemCtfile\CtFileAdapter;
use YangWeijie\FilesystemCtfile\CtFileClient;
use YangWeijie\FilesystemCtfile\ConfigurationManager;

echo "Testing Filesystem ctFile Extension Installation..." . PHP_EOL;

// Test 1: Check if main classes are available
$classes = [
    CtFileAdapter::class,
    CtFileClient::class,
    ConfigurationManager::class,
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "✓ {$class} - OK" . PHP_EOL;
    } else {
        echo "✗ {$class} - MISSING" . PHP_EOL;
        exit(1);
    }
}

// Test 2: Test configuration validation
try {
    $config = new ConfigurationManager([
        'ctfile' => [
            'host' => 'test.example.com',
            'username' => 'test',
            'password' => 'test',
        ]
    ]);
    echo "✓ Configuration validation - OK" . PHP_EOL;
} catch (Exception $e) {
    echo "✗ Configuration validation - FAILED: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "Installation verification completed successfully!" . PHP_EOL;
echo "You can now use the filesystem-ctfile package in your project." . PHP_EOL;
```

Run the verification script:

```bash
php test-installation.php
```

### Step 4: Clean Up (Optional)

Remove the test script after verification:

```bash
rm test-installation.php
```

### Troubleshooting Installation

If you encounter issues during installation:

1. **Composer Issues**:
   ```bash
   # Clear Composer cache
   composer clear-cache
   
   # Update Composer to latest version
   composer self-update
   
   # Install with verbose output
   composer require yangweijie/filesystem-ctfile -v
   ```

2. **PHP Version Issues**:
   ```bash
   # Check available PHP versions
   php --version
   
   # If using multiple PHP versions, specify the correct one
   /usr/bin/php8.1 -v
   composer config platform.php 8.1.0
   ```

3. **Extension Issues**:
   ```bash
   # Install missing extensions (Ubuntu/Debian)
   sudo apt-get install php8.1-json php8.1-mbstring php8.1-openssl
   
   # Install missing extensions (CentOS/RHEL)
   sudo yum install php81-json php81-mbstring php81-openssl
   
   # Install missing extensions (macOS with Homebrew)
   brew install php@8.1
   ```

4. **Permission Issues**:
   ```bash
   # Fix Composer permissions
   sudo chown -R $USER:$USER ~/.composer
   
   # Install globally if local installation fails
   composer global require yangweijie/filesystem-ctfile
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

## Package Information

### Version Information

- **Current Version**: 1.0.0
- **Release Date**: 2024-12-16
- **Stability**: Stable
- **Minimum PHP Version**: 8.1.0

### Distribution

This package is distributed through:

- **Packagist**: [yangweijie/filesystem-ctfile](https://packagist.org/packages/yangweijie/filesystem-ctfile)
- **GitHub**: [yangweijie/filesystem-ctfile](https://github.com/yangweijie/filesystem-ctfile)

### Installation Methods

```bash
# Standard installation
composer require yangweijie/filesystem-ctfile

# Install specific version
composer require yangweijie/filesystem-ctfile:^1.0

# Install development version
composer require yangweijie/filesystem-ctfile:dev-main

# Install with specific stability
composer require yangweijie/filesystem-ctfile --prefer-stable
```

### Semantic Versioning

This package follows [Semantic Versioning](https://semver.org/):

- **1.x.x**: Current stable branch
- **1.0.x**: Patch releases (bug fixes)
- **1.x.0**: Minor releases (new features, backward compatible)
- **x.0.0**: Major releases (breaking changes)

### Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history and breaking changes.

## Support

### Getting Help

If you encounter any issues or have questions:

1. **Documentation**: Check the [documentation](docs/) directory
2. **Examples**: Review [usage examples](docs/usage-examples.md)
3. **Troubleshooting**: See [troubleshooting guide](docs/troubleshooting-guide.md)
4. **Issues**: [Open an issue](https://github.com/yangweijie/filesystem-ctfile/issues) on GitHub
5. **Discussions**: Use [GitHub Discussions](https://github.com/yangweijie/filesystem-ctfile/discussions) for questions

### Reporting Issues

When reporting issues, please include:

- PHP version (`php --version`)
- Package version (`composer show yangweijie/filesystem-ctfile`)
- Operating system and version
- Complete error message and stack trace
- Minimal code example that reproduces the issue
- Steps to reproduce the problem

### Security Issues

For security-related issues, please email directly instead of opening a public issue:
- Email: yangweijie@example.com
- Subject: [SECURITY] Filesystem ctFile Extension

### Commercial Support

For commercial support, custom development, or consulting services, please contact:
- Email: yangweijie@example.com
- GitHub: [@yangweijie](https://github.com/yangweijie)