<?php

namespace Pharaonic\Laravel\Translatable\Migration;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Resolution\DefinitionResolver;
use Pharaonic\Laravel\Translatable\Resolution\LocaleColumnMapper;
use Pharaonic\Laravel\Translatable\Strategies\JsonStrategy;
use Pharaonic\Laravel\Translatable\Support\SchemaInspector;

/**
 * @internal
 */
final class JsonAdapter implements StorageAdapter
{
    public function __construct(
        private JsonStrategy $strategy,
        private LocaleColumnMapper $columns,
        private DefinitionResolver $definitions,
        private SchemaInspector $schema,
        private Canonical $canonical,
    ) {}

    public function name(): string
    {
        return 'json';
    }

    /**
     * @return array<string, string> field => column
     */
    private function columnsFor(Model $model): array
    {
        $columns = [];

        foreach ($this->definitions->for($model)->names() as $field) {
            $columns[$field] = $this->columns->jsonColumn($model, $field);
        }

        return $columns;
    }

    public function preflight(Model $model, bool $target): array
    {
        $existing = $this->schema->columns($model->getConnection(), $model->getTable());
        $issues = [];

        foreach ($this->columnsFor($model) as $column) {
            if (! in_array(strtolower($column), $existing, true)) {
                $issues[] = "The [{$model->getTable()}] table has no [{$column}] JSON column.";
            }
        }

        return $issues;
    }

    public function read(Model $model, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $columns = $this->columnsFor($model);
        $rows = $model->getConnection()->table($model->getTable())
            ->whereIn($model->getKeyName(), $keys)
            ->get([$model->getKeyName(), ...array_values($columns)]);
        $entities = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $key = $row[$model->getKeyName()];

            if (! is_int($key) && ! is_string($key)) {
                continue;
            }

            foreach ($columns as $field => $column) {
                foreach ($this->strategy->decode($model, $column, $row[$column] ?? null) as $locale => $value) {
                    $entities[$key][$field][$locale] = $this->canonical->fromStorage($model, $field, $value);
                }
            }
        }

        return $entities;
    }

    public function write(Model $model, int|string $key, array $values, array $fields): void
    {
        $columns = $this->columnsFor($model);
        $data = [];

        foreach ($fields as $field) {
            $locales = [];

            foreach ($values[$field] ?? [] as $locale => $value) {
                if ($value !== null) {
                    $locales[(string) $locale] = $this->canonical->toStorage($model, $field, $value, true);
                }
            }

            ksort($locales, SORT_STRING);
            $data[$columns[$field]] = $locales === [] ? null : $this->strategy->encode($locales);
        }

        $model->getConnection()->table($model->getTable())->where($model->getKeyName(), $key)->update($data);
    }

    public function unsupportedLocales(Model $model, array $entities): array
    {
        return [];
    }
}
