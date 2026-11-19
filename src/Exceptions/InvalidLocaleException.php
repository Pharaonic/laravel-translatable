<?php

namespace Pharaonic\Laravel\Translatable\Exceptions;

use InvalidArgumentException;

final class InvalidLocaleException extends InvalidArgumentException implements TranslatableException
{
    public static function malformed(string $locale): self
    {
        return new self("The locale \"{$locale}\" is not a valid locale tag.");
    }

    public static function notAllowed(string $locale, string $model): self
    {
        return new self("The locale \"{$locale}\" is not an accepted locale of [{$model}].");
    }
}
