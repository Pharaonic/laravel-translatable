<?php

namespace Pharaonic\Laravel\Translatable\Migration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Pharaonic\Laravel\Translatable\Resolution\DefinitionResolver;
use Pharaonic\Laravel\Translatable\Strategies\TableStrategy;
use Pharaonic\Laravel\Translatable\Support\SchemaInspector;

/**
 * @internal
 */
final class TableAdapter implements StorageAdapter
{
    public function __construct(
        private TableStrategy $strategy,
        private DefinitionResolver $definitions,
        private SchemaInspector $schema,
        private Canonical $canonical,
    ) {
    }

    public function name(): string
    {
        return 'table';
    }

    public function table(Model $model): string
    {
        $class = $this->strategy->translationModel($model);

        return (new $class)->getTable();
    }

    public function preflight(Model $model, bool $target): array
    {
        $class = $this->strategy->translationModel($model);
        $translation = new $class;
        $issues = [];

        if ($translation->getConnectionName() !== $model->getConnectionName()) {
            $issues[] = sprintf('The translation model [%s] uses another connection than [%s]; a migration needs one connection.', $class, get_class($model));
        }

        $table = $this->table($model);

        if (! $model->getConnection()->getSchemaBuilder()->hasTable($table)) {
            return [...$issues, "The [{$table}] table does not exist."];
        }

        $columns = $this->schema->columns($model->getConnection(), $table);
        $required = [$this->strategy->foreignKey($model), TableStrategy::LOCALE_COLUMN, ...$this->definitions->for($model)->names()];

        foreach ($required as $column) {
            if (! in_array(strtolower($column), $columns, true)) {
                $issues[] = "The [{$table}] table has no [{$column}] column.";
            }
        }

        return $issues;
    }

    /**
     * Columns of the translation table that hold data no other layout can store.
     *
     * @return array<string, int> column => rows with a value
     */
    public function extraColumns(Model $model): array
    {
        $table = $this->table($model);
        $class = $this->strategy->translationModel($model);
        $known = array_map('strtolower', [
            (new $class)->getKeyName(),
            $this->strategy->foreignKey($model),
            TableStrategy::LOCALE_COLUMN,
            'created_at',
            'updated_at',
            ...$this->definitions->for($model)->names(),
        ]);
        $extra = [];

        foreach ($this->schema->columns($model->getConnection(), $table) as $column) {
            if (in_array($column, $known, true)) {
                continue;
            }

            $rows = $model->getConnection()->table($table)->whereNotNull($column)->count();

            if ($rows > 0) {
                $extra[$column] = $rows;
            }
        }

        return $extra;
    }

    public function read(Model $model, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $foreignKey = $this->strategy->foreignKey($model);
        $fields = $this->definitions->for($model)->names();
        $rows = $model->getConnection()->table($this->table($model))
            ->whereIn($foreignKey, $keys)
            ->get([$foreignKey, TableStrategy::LOCALE_COLUMN, ...$fields]);
        $entities = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $key = $row[$foreignKey];
            $locale = $row[TableStrategy::LOCALE_COLUMN];

            if ((! is_int($key) && ! is_string($key)) || ! is_string($locale)) {
                continue;
            }

            foreach ($fields as $field) {
                if (isset($row[$field])) {
                    $entities[$key][$field][$locale] = $this->canonical->fromStorage($model, $field, $row[$field]);
                }
            }
        }

        return $entities;
    }

    public function write(Model $model, int|string $key, array $values, array $fields): void
    {
        $connection = $model->getConnection();
        $table = $this->table($model);
        $foreignKey = $this->strategy->foreignKey($model);
        $columns = $this->schema->columns($connection, $table);

        $existing = [];

        foreach ($connection->table($table)->where($foreignKey, $key)->get([TableStrategy::LOCALE_COLUMN]) as $row) {
            $locale = ((array) $row)[TableStrategy::LOCALE_COLUMN] ?? null;

            if (is_string($locale)) {
                $existing[$locale] = true;
            }
        }

        $locales = array_keys($existing);

        foreach ($values as $byLocale) {
            $locales = array_merge($locales, array_map('strval', array_keys($byLocale)));
        }

        foreach (array_unique($locales) as $locale) {
            $data = [];

            foreach ($fields as $field) {
                $data[$field] = $this->canonical->toStorage($model, $field, $values[$field][$locale] ?? null, false);
            }

            if (isset($existing[$locale])) {
                if (in_array('updated_at', $columns, true)) {
                    $data['updated_at'] = Carbon::now();
                }

                $connection->table($table)->where($foreignKey, $key)->where(TableStrategy::LOCALE_COLUMN, $locale)->update($data);

                continue;
            }

            if (array_filter($data, fn (mixed $value): bool => $value !== null) === []) {
                continue;
            }

            foreach (['created_at', 'updated_at'] as $timestamp) {
                if (in_array($timestamp, $columns, true)) {
                    $data[$timestamp] = Carbon::now();
                }
            }

            $connection->table($table)->insert([$foreignKey => $key, TableStrategy::LOCALE_COLUMN => $locale] + $data);
        }
    }

    public function unsupportedLocales(Model $model, array $entities): array
    {
        return [];
    }
}
