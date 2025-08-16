<?php

declare(strict_types=1);

/**
 * Package Information Script
 * 
 * Displays information about the yangweijie/filesystem-ctfile package.
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "=== yangweijie/filesystem-ctfile Package Information ===" . PHP_EOL . PHP_EOL;

// Read composer.json
$composerFile = __DIR__ . '/composer.json';
if (file_exists($composerFile)) {
    $composer = json_decode(file_get_contents($composerFile), true);
    
    echo "Package Name: " . $composer['name'] . PHP_EOL;
    echo "Description: " . $composer['description'] . PHP_EOL;
    echo "Type: " . $composer['type'] . PHP_EOL;
    echo "License: " . $composer['license'] . PHP_EOL;
    echo "Keywords: " . implode(', ', $composer['keywords']) . PHP_EOL;
    echo "Homepage: " . $composer['homepage'] . PHP_EOL . PHP_EOL;
    
    echo "Authors:" . PHP_EOL;
    foreach ($composer['authors'] as $author) {
        echo "  - " . $author['name'];
        if (isset($author['email'])) {
            echo " <" . $author['email'] . ">";
        }
        if (isset($author['role'])) {
            echo " (" . $author['role'] . ")";
        }
        echo PHP_EOL;
    }
    echo PHP_EOL;
    
    echo "Requirements:" . PHP_EOL;
    foreach ($composer['require'] as $package => $version) {
        echo "  - {$package}: {$version}" . PHP_EOL;
    }
    echo PHP_EOL;
    
    echo "Development Requirements:" . PHP_EOL;
    foreach ($composer['require-dev'] as $package => $version) {
        echo "  - {$package}: {$version}" . PHP_EOL;
    }
    echo PHP_EOL;
    
    if (isset($composer['scripts'])) {
        echo "Available Scripts:" . PHP_EOL;
        foreach ($composer['scripts'] as $script => $commands) {
            echo "  - composer {$script}" . PHP_EOL;
        }
        echo PHP_EOL;
    }
}

// Check if classes are available
echo "Available Classes:" . PHP_EOL;
$classes = [
    'YangWeijie\\FilesystemCtfile\\CtFileAdapter',
    'YangWeijie\\FilesystemCtfile\\CtFileClient',
    'YangWeijie\\FilesystemCtfile\\ConfigurationManager',
    'YangWeijie\\FilesystemCtfile\\ErrorHandler',
    'YangWeijie\\FilesystemCtfile\\RetryHandler',
    'YangWeijie\\FilesystemCtfile\\CacheManager',
];

foreach ($classes as $class) {
    $status = class_exists($class) ? '✓' : '✗';
    echo "  {$status} {$class}" . PHP_EOL;
}

echo PHP_EOL . "For usage examples, run: php examples/basic-usage.php" . PHP_EOL;
echo "For installation verification, run: php verify-installation.php" . PHP_EOL;