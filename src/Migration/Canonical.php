<?php

namespace Pharaonic\Laravel\Translatable\Migration;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Resolution\DefinitionResolver;
use Pharaonic\Laravel\Translatable\Resolution\LocaleColumnMapper;
use Pharaonic\Laravel\Translatable\Support\CastResolver;

/**
 * Converts values between a storage layout and the canonical form shared by
 * every adapter, and hashes entities for verification.
 *
 * @internal
 */
final class Canonical
{
    public function __construct(private DefinitionResolver $definitions, private CastResolver $casts) {}

    /**
     * A stored value (from any layout) in canonical form.
     */
    public function fromStorage(Model $model, string $field, mixed $value): mixed
    {
        $definition = $this->definitions->for($model)->get($field);

        if ($definition === null || $value === null) {
            return $value;
        }

        return $this->casts->toStorage($model, $definition, $this->casts->fromStorage($model, $definition, $value), true);
    }

    /**
     * A canonical value in the storage form of a layout.
     */
    public function toStorage(Model $model, string $field, mixed $value, bool $json): mixed
    {
        $definition = $this->definitions->for($model)->get($field);

        if ($definition === null || $value === null) {
            return $value;
        }

        return $this->casts->toStorage($model, $definition, $this->casts->fromStorage($model, $definition, $value), $json);
    }

    /**
     * Values of the fields, nulls dropped and locales sorted; with $columnLocales,
     * locales spelled as the columns layout stores them ("pt-BR" → "pt_br").
     *
     * @param  array<string, array<string, mixed>>  $values
     * @param  list<string>  $fields
     * @return array<string, array<string, mixed>>
     */
    public static function normalize(array $values, array $fields, bool $columnLocales = false): array
    {
        $normalized = [];

        foreach ($fields as $field) {
            $locales = [];

            foreach ($values[$field] ?? [] as $locale => $value) {
                if ($value !== null) {
                    $locales[$columnLocales ? LocaleColumnMapper::suffix((string) $locale) : (string) $locale] = $value;
                }
            }

            ksort($locales, SORT_STRING);

            if ($locales !== []) {
                $normalized[$field] = $locales;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, array<string, mixed>>  $values
     * @param  list<string>  $fields
     */
    public static function hash(array $values, array $fields, bool $columnLocales = false): string
    {
        return hash('sha256', (string) json_encode(self::normalize($values, $fields, $columnLocales), JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }
}
