<?php

namespace Pharaonic\Laravel\Translatable\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Pharaonic\Laravel\Translatable\Contracts\FallbackPolicy;

/**
 * The requested locale, then config("app.fallback_locale").
 */
final class FallbackResolver implements FallbackPolicy
{
    public function chain(Model $model, string $field, string $locale): array
    {
        $fallback = self::fallbackLocale();

        return $fallback === null || $fallback === $locale ? [$locale] : [$locale, $fallback];
    }

    public static function fallbackLocale(): ?string
    {
        $fallback = Config::get('app.fallback_locale');

        return is_string($fallback) && $fallback !== '' ? $fallback : null;
    }
}
