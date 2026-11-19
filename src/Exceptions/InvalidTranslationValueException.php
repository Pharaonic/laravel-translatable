<?php

namespace Pharaonic\Laravel\Translatable\Exceptions;

use InvalidArgumentException;

final class InvalidTranslationValueException extends InvalidArgumentException implements TranslatableException
{
    public static function forType(string $model, string $field, string $type, mixed $value): self
    {
        return new self(sprintf(
            'The [%s] translation of [%s] must be of type %s, %s given.',
            $field,
            $model,
            $type,
            get_debug_type($value)
        ));
    }
}
