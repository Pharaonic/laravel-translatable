<?php

namespace Pharaonic\Laravel\Translatable\Exceptions;

use InvalidArgumentException;

final class UnknownTranslatableFieldException extends InvalidArgumentException implements TranslatableException
{
    public static function forField(string $field, string $model): self
    {
        return new self("[{$field}] is not a translatable field of [{$model}].");
    }
}
