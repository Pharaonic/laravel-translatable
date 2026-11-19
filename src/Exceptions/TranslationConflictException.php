<?php

namespace Pharaonic\Laravel\Translatable\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Concurrent writes kept failing after the configured number of retries.
 */
final class TranslationConflictException extends RuntimeException implements TranslatableException
{
    public static function afterRetries(string $model, int $attempts, Throwable $previous): self
    {
        return new self("Saving the translations of [{$model}] failed after {$attempts} attempts because of concurrent writes.", 0, $previous);
    }
}
