<?php

namespace Pharaonic\Laravel\Translatable\Exceptions;

use RuntimeException;

final class MissingTranslationException extends RuntimeException implements TranslatableException
{
    public static function forField(string $model, string $field, string $locale): self
    {
        return new self("[{$model}] has no \"{$locale}\" translation for [{$field}].");
    }
}
