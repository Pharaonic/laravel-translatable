<?php

namespace Pharaonic\Laravel\Translatable\Migration;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Resolution\DefinitionResolver;
use Pharaonic\Laravel\Translatable\Resolution\LocaleColumnMapper;
use Throwable;

/**
 * @internal
 */
final class ColumnsAdapter implements StorageAdapter
{
    public function __construct(
        private LocaleColumnMapper $columns,
        private DefinitionResolver $definitions,
        private Canonical $canonical,
    ) {}

    public function name(): string
    {
        return 'columns';
    }

    public function preflight(Model $model, bool $target): array
    {
        try {
            $issues = [];

            foreach ($this->definitions->for($model)->names() as $field) {
                if ($this->columns->localeColumns($model, $field) === []) {
                    $issues[] = "The [{$model->getTable()}] table has no {$field}_{locale} column.";
                }
            }

            return $issues;
        } catch (Throwable $exception) {
            return [$exception->getMessage()];
        }
    }

    public function read(Model $model, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $map = [];

        foreach ($this->definitions->for($model)->names() as $field) {
            $map[$field] = $this->columns->localeColumns($model, $field);
        }

        $select = [$model->getKeyName()];

        foreach ($map as $columns) {
            $select = array_merge($select, array_values($columns));
        }

        $rows = $model->getConnection()->table($model->getTable())->whereIn($model->getKeyName(), $keys)->get($select);
        $entities = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $key = $row[$model->getKeyName()];

            if (! is_int($key) && ! is_string($key)) {
                continue;
            }

            foreach ($map as $field => $columns) {
                foreach ($columns as $locale => $column) {
                    if (isset($row[$column])) {
                        $entities[$key][$field][$locale] = $this->canonical->fromStorage($model, $field, $row[$column]);
                    }
                }
            }
        }

        return $entities;
    }

    public function write(Model $model, int|string $key, array $values, array $fields): void
    {
        $data = [];

        foreach ($fields as $field) {
            $byLocale = [];

            foreach ($values[$field] ?? [] as $locale => $value) {
                $byLocale[LocaleColumnMapper::suffix((string) $locale)] = $value;
            }

            foreach ($this->columns->localeColumns($model, $field) as $locale => $column) {
                $data[$column] = $this->canonical->toStorage($model, $field, $byLocale[$locale] ?? null, false);
            }
        }

        $model->getConnection()->table($model->getTable())->where($model->getKeyName(), $key)->update($data);
    }

    public function unsupportedLocales(Model $model, array $entities): array
    {
        $unsupported = [];

        foreach ($entities as $fields) {
            foreach ($fields as $field => $locales) {
                $columns = $this->columns->localeColumns($model, (string) $field);

                foreach (array_keys($locales) as $locale) {
                    if (! isset($columns[LocaleColumnMapper::suffix((string) $locale)])) {
                        $unsupported[(string) $locale] = true;
                    }
                }
            }
        }

        return array_map('strval', array_keys($unsupported));
    }
}
