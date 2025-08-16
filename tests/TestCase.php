<?php

declare(strict_types=1);

/*
 * This file is part of the yangweijie/filesystem-ctfile package.
 *
 * (c) Yang Weijie <yangweijie@example.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YangWeijie\FilesystemCtfile\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set timezone for consistent test results
        date_default_timezone_set('UTC');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up any test artifacts
        \Mockery::close();
    }
}
