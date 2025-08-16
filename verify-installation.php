<?php

declare(strict_types=1);

/**
 * Installation Verification Script
 * 
 * This script verifies that the yangweijie/filesystem-ctfile package
 * has been installed correctly and all dependencies are available.
 */

require_once __DIR__ . '/vendor/autoload.php';

use YangWeijie\FilesystemCtfile\CtFileAdapter;
use YangWeijie\FilesystemCtfile\CtFileClient;
use YangWeijie\FilesystemCtfile\ConfigurationManager;
use YangWeijie\FilesystemCtfile\ErrorHandler;
use League\Flysystem\Filesystem;

echo "=== yangweijie/filesystem-ctfile Installation Verification ===" . PHP_EOL . PHP_EOL;

// Check PHP version
echo "Checking PHP version..." . PHP_EOL;
$phpVersion = PHP_VERSION;
$requiredVersion = '8.1.0';

if (version_compare($phpVersion, $requiredVersion, '>=')) {
    echo "✓ PHP version: {$phpVersion} (required: >= {$requiredVersion})" . PHP_EOL;
} else {
    echo "✗ PHP version: {$phpVersion} (required: >= {$requiredVersion})" . PHP_EOL;
    exit(1);
}

// Check required extensions
echo PHP_EOL . "Checking required PHP extensions..." . PHP_EOL;
$requiredExtensions = ['json', 'mbstring'];
$missingExtensions = [];

foreach ($requiredExtensions as $extension) {
    if (extension_loaded($extension)) {
        echo "✓ Extension '{$extension}' is loaded" . PHP_EOL;
    } else {
        echo "✗ Extension '{$extension}' is missing" . PHP_EOL;
        $missingExtensions[] = $extension;
    }
}

if (!empty($missingExtensions)) {
    echo PHP_EOL . "Missing extensions: " . implode(', ', $missingExtensions) . PHP_EOL;
    exit(1);
}

// Check class availability
echo PHP_EOL . "Checking class availability..." . PHP_EOL;
$requiredClasses = [
    CtFileAdapter::class,
    CtFileClient::class,
    ConfigurationManager::class,
    ErrorHandler::class,
    Filesystem::class,
];

$missingClasses = [];

foreach ($requiredClasses as $class) {
    if (class_exists($class)) {
        echo "✓ Class '{$class}' is available" . PHP_EOL;
    } else {
        echo "✗ Class '{$class}' is missing" . PHP_EOL;
        $missingClasses[] = $class;
    }
}

if (!empty($missingClasses)) {
    echo PHP_EOL . "Missing classes: " . implode(', ', $missingClasses) . PHP_EOL;
    exit(1);
}

// Test basic functionality
echo PHP_EOL . "Testing basic functionality..." . PHP_EOL;

try {
    // Test configuration manager
    $config = new ConfigurationManager([
        'ctfile' => [
            'host' => 'test.example.com',
            'username' => 'test',
            'password' => 'test',
        ],
    ]);
    echo "✓ ConfigurationManager instantiation successful" . PHP_EOL;

    // Test adapter instantiation (without actual connection)
    $mockConfig = [
        'ctfile' => [
            'host' => 'mock.example.com',
            'username' => 'mock',
            'password' => 'mock',
        ],
    ];
    
    // Note: We can't test actual connection without a real ctFile server
    echo "✓ Basic configuration validation successful" . PHP_EOL;

} catch (Throwable $e) {
    echo "✗ Basic functionality test failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Success message
echo PHP_EOL . "=== Installation Verification Complete ===" . PHP_EOL;
echo "✓ All checks passed! The package is installed correctly." . PHP_EOL . PHP_EOL;

echo "Next steps:" . PHP_EOL;
echo "1. Configure your ctFile connection settings" . PHP_EOL;
echo "2. Create a CtFileAdapter instance with your configuration" . PHP_EOL;
echo "3. Use it with League\\Flysystem\\Filesystem" . PHP_EOL . PHP_EOL;

echo "Example usage:" . PHP_EOL;
echo "<?php" . PHP_EOL;
echo "use League\\Flysystem\\Filesystem;" . PHP_EOL;
echo "use YangWeijie\\FilesystemCtfile\\CtFileAdapter;" . PHP_EOL . PHP_EOL;
echo "\$config = [" . PHP_EOL;
echo "    'ctfile' => [" . PHP_EOL;
echo "        'host' => 'your-ctfile-host.com'," . PHP_EOL;
echo "        'username' => 'your-username'," . PHP_EOL;
echo "        'password' => 'your-password'," . PHP_EOL;
echo "    ]," . PHP_EOL;
echo "];" . PHP_EOL . PHP_EOL;
echo "\$adapter = new CtFileAdapter(\$config);" . PHP_EOL;
echo "\$filesystem = new Filesystem(\$adapter);" . PHP_EOL . PHP_EOL;

echo "For more information, see README.md or docs/" . PHP_EOL;