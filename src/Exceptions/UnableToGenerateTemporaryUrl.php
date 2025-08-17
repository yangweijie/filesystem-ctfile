<?php

declare(strict_types=1);

namespace YangWeijie\FilesystemCtfile\Exceptions;

use League\Flysystem\FilesystemOperationFailed;
use Throwable;

class UnableToGenerateTemporaryUrl extends FilesystemOperationFailed
{
    public static function dueToError(string $path, Throwable $previous): self
    {
        return new self(
            "Unable to generate temporary URL for file at path '{$path}': {$previous->getMessage()}",
            $path,
            $previous
        );
    }
}
