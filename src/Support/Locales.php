<?php

namespace Pharaonic\Laravel\Translatable\Support;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Exceptions\InvalidLocaleException;

/**
 * Locale tags are validated before they reach storage or SQL. Tags keep their
 * spelling: "pt-BR" and "pt_BR" are different locales.
 *
 * @internal
 */
final class Locales
{
    private const PATTERN = '/^[A-Za-z]{2,8}(?:[-_][A-Za-z0-9]{1,8}){0,4}$/';

    public static function isWellFormed(string $locale): bool
    {
        return preg_match(self::PATTERN, $locale) === 1;
    }

    /**
     * @param  list<string>  $accepted  Empty accepts every well-formed tag.
     */
    public static function validate(Model $model, string $locale, array $accepted): string
    {
        if (! self::isWellFormed($locale)) {
            throw InvalidLocaleException::malformed($locale);
        }

        if ($accepted !== [] && ! in_array($locale, $accepted, true)) {
            throw InvalidLocaleException::notAllowed($locale, get_class($model));
        }

        return $locale;
    }
}
