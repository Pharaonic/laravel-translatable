<?php

namespace Pharaonic\Laravel\Translatable\Resolution;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Support\SchemaInspector;

/**
 * Physical columns of the json and columns strategies, by convention only.
 *
 * json: a field is stored in the JSON column of the same name.
 * columns: a field is stored in one column per locale, {field}_{locale},
 * where the locale is lowercase with "-" turned into "_" (pt-BR → title_pt_br).
 * The locales of a field are the ones its table has columns for.
 *
 * @internal
 */
final class LocaleColumnMapper
{
    /**
     * A column suffix that reads as a locale: a 2–3 letter language, then an
     * optional 4-letter script and an optional 2-letter or 3-digit region.
     */
    private const SUFFIX = '/^[a-z]{2,3}(_[a-z]{4})?(_([a-z]{2}|[0-9]{3}))?$/';

    public function __construct(private SchemaInspector $schema, private DefinitionResolver $definitions) {}

    public function jsonColumn(Model $model, string $field): string
    {
        return $field;
    }

    /**
     * The columns-strategy spelling of a locale: "pt-BR" → "pt_br".
     */
    public static function suffix(string $locale): string
    {
        return strtolower(str_replace('-', '_', $locale));
    }

    public function localeColumn(Model $model, string $field, string $locale): string
    {
        return $field.'_'.self::suffix($locale);
    }

    public function hasColumn(Model $model, string $column): bool
    {
        return $this->schema->has($model->getConnection(), $model->getTable(), $column);
    }

    /**
     * locale => column of one field, from the table's columns.
     *
     * @return array<string, string>
     */
    public function localeColumns(Model $model, string $field): array
    {
        $longer = array_filter(
            $this->definitions->for($model)->names(),
            fn (string $other): bool => $other !== $field && str_starts_with($other, $field.'_')
        );
        $columns = [];

        foreach ($this->schema->columns($model->getConnection(), $model->getTable()) as $column) {
            $lower = strtolower($column);

            if (! str_starts_with($lower, strtolower($field).'_')) {
                continue;
            }

            foreach ($longer as $other) {
                if (str_starts_with($lower, strtolower($other).'_')) {
                    continue 2;
                }
            }

            $suffix = substr($lower, strlen($field) + 1);

            if (preg_match(self::SUFFIX, $suffix) === 1) {
                $columns[$suffix] = $column;
            }
        }

        ksort($columns, SORT_STRING);

        return $columns;
    }

    /**
     * Every locale any field has a column for.
     *
     * @return list<string>
     */
    public function locales(Model $model): array
    {
        $locales = [];

        foreach ($this->definitions->for($model)->names() as $field) {
            foreach (array_keys($this->localeColumns($model, $field)) as $locale) {
                $locales[(string) $locale] = true;
            }
        }

        $locales = array_map('strval', array_keys($locales));
        sort($locales, SORT_STRING);

        return $locales;
    }
}
