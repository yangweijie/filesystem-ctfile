<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Cache;

use Psr\SimpleCache\InvalidArgumentException;

/**
 * Exception thrown when an invalid cache key is used.
 */
class InvalidCacheKeyException extends \InvalidArgumentException implements InvalidArgumentException
{
}
