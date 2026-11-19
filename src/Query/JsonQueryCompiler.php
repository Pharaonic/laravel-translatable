<?php

namespace Pharaonic\Laravel\Translatable\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Exceptions\UnsupportedTranslationQueryException;
use Pharaonic\Laravel\Translatable\Resolution\DefinitionResolver;
use Pharaonic\Laravel\Translatable\Resolution\LocaleColumnMapper;

/**
 * JSON path extraction through the grammar ("title->en"): json_extract on
 * SQLite, json_unquote(json_extract()) on MySQL and MariaDB, ->> on PostgreSQL.
 * Numbers are compared and ordered numerically on every engine. A condition on
 * any locale expands the JSON object: json_each (SQLite), json_table (MySQL 8,
 * MariaDB 10.6+) or json_each_text (PostgreSQL).
 *
 * @internal
 */
final class JsonQueryCompiler extends RowQueryCompiler
{
    public function __construct(DefinitionResolver $definitions, private LocaleColumnMapper $columns)
    {
        parent::__construct($definitions);
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function expression(Builder $query, Model $model, string $field, string $locale): string
    {
        return $this->wrap($query, $this->column($model, $field).'->'.$locale);
    }

    protected function locales(Model $model, array $locales): array
    {
        if ($locales === []) {
            throw new UnsupportedTranslationQueryException('This JSON translation condition on ['.get_class($model).'] needs a locale.');
        }

        return $locales;
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function whereValueInAnyLocale(Builder $query, Model $model, string $field, string $operator, mixed $value, string $boolean): void
    {
        if ($value === null) {
            throw new UnsupportedTranslationQueryException('A null comparison on any locale is not supported; use whereMissingTranslation() with a locale.');
        }

        $column = $this->wrap($query, $this->column($model, $field));
        $numeric = in_array($this->definition($model, $field)?->type, ['int', 'float'], true);

        $sql = match ($this->driver($query)) {
            'sqlite' => 'exists (select 1 from json_each('.$column.') where json_each.value '.$operator.' ?)',
            // MySQL 8 returns no rows for EXISTS over a JSON_TABLE of an outer column; a count does not have that bug.
            'mysql', 'mariadb' => '(select count(*) from json_table('.$column.", '\$.*' columns (translatable_value longtext path '\$')) as translatable_values where "
                .($numeric ? '(translatable_value + 0)' : 'translatable_value').' '.$operator.' ?) > 0',
            'pgsql' => 'exists (select 1 from json_each_text(('.$column.')::json) as translatable_values(locale, value) where '
                .($numeric ? '(translatable_values.value)::numeric' : 'translatable_values.value').' '.$operator.' ?)',
            default => throw new UnsupportedTranslationQueryException('A JSON condition on any locale is not supported on the '.$this->driver($query).' driver; pass a locale.'),
        };

        // A wrapped column, fixed SQL per driver and an allowlisted operator; the value is a binding.
        /** @var literal-string&non-falsy-string $raw */
        $raw = $sql;
        $query->whereRaw($raw, [$this->binding($query, $model, $field, $value)], $boolean);
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function typed(Builder $query, Model $model, string $field, string $expression): string
    {
        $type = $this->definition($model, $field)?->type;

        if ($type !== 'int' && $type !== 'float') {
            return $expression;
        }

        return match ($this->driver($query)) {
            'pgsql' => '('.$expression.')::numeric',
            'mysql', 'mariadb' => '('.$expression.' + 0)',
            default => $expression,
        };
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function binding(Builder $query, Model $model, string $field, mixed $value): mixed
    {
        if (! is_bool($value)) {
            return $value;
        }

        // SQLite's json_extract() returns 1/0; MySQL and PostgreSQL return the text true/false.
        return $this->driver($query) === 'sqlite' ? (int) $value : ($value ? 'true' : 'false');
    }

    private function column(Model $model, string $field): string
    {
        return $model->qualifyColumn($this->columns->jsonColumn($model, $field));
    }
}
