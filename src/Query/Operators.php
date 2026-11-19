<?php

namespace Pharaonic\Laravel\Translatable\Query;

use Pharaonic\Laravel\Translatable\Exceptions\UnsupportedTranslationQueryException;

/**
 * The comparison operators translation scopes accept.
 *
 * @internal
 */
final class Operators
{
    public const ALLOWED = ['=', '!=', '<>', '<', '>', '<=', '>=', 'like', 'not like'];

    public static function normalize(string $operator): string
    {
        $normalized = strtolower(trim($operator));

        if (! in_array($normalized, self::ALLOWED, true)) {
            throw new UnsupportedTranslationQueryException(sprintf('The operator "%s" is not supported by translation scopes. Supported: %s.', $operator, implode(', ', self::ALLOWED)));
        }

        return $normalized === '<>' ? '!=' : $normalized;
    }

    public static function direction(string $direction): string
    {
        $normalized = strtolower(trim($direction));

        if ($normalized !== 'asc' && $normalized !== 'desc') {
            throw new UnsupportedTranslationQueryException('Order direction must be "asc" or "desc".');
        }

        return $normalized;
    }
}
