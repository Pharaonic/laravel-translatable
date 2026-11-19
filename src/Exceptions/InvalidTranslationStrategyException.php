<?php

namespace Pharaonic\Laravel\Translatable\Exceptions;

use LogicException;

final class InvalidTranslationStrategyException extends LogicException implements TranslatableException
{
    /**
     * @param  list<string>  $valid
     */
    public static function forValue(string $model, mixed $value, array $valid): self
    {
        $given = is_string($value) ? "\"{$value}\"" : get_debug_type($value);

        return new self(sprintf(
            'Invalid translation strategy %s for [%s] in config("translatable.strategy"). Valid strategies: %s.',
            $given,
            $model,
            implode(', ', $valid)
        ));
    }
}
