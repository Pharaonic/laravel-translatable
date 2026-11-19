<?php

namespace Pharaonic\Laravel\Translatable\Strategies;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Pharaonic\Laravel\Translatable\Contracts\TranslationQueryCompiler;
use Pharaonic\Laravel\Translatable\Contracts\TranslationStrategy;
use Pharaonic\Laravel\Translatable\Data\TranslationPatchSet;
use Pharaonic\Laravel\Translatable\Exceptions\TranslationSchemaException;
use Pharaonic\Laravel\Translatable\Query\JsonQueryCompiler;
use Pharaonic\Laravel\Translatable\Resolution\DefinitionResolver;
use Pharaonic\Laravel\Translatable\Resolution\LocaleColumnMapper;
use Pharaonic\Laravel\Translatable\Support\CastResolver;
use Pharaonic\Laravel\Translatable\Support\RawOriginal;

/**
 * One JSON column per translated field on the model's table, holding
 * {"en": "Hello", "ar": "مرحبا"}. A field uses the column of the same name
 * unless config("translatable.models.{class}.columns") maps it.
 *
 * Saving an existing model locks its row, reads the JSON columns again and
 * patches only the changed locales, so concurrent writers of other locales are kept.
 *
 * @internal
 */
final class JsonStrategy implements TranslationStrategy
{
    public function __construct(
        private DefinitionResolver $definitions,
        private LocaleColumnMapper $columns,
        private CastResolver $casts,
        private JsonQueryCompiler $compiler,
    ) {
    }

    public function name(): string
    {
        return 'json';
    }

    public function column(Model $model, string $field): string
    {
        return $this->columns->jsonColumn($model, $field);
    }

    public function normalizeLocale(string $locale): string
    {
        return $locale;
    }

    public function stored(Model $model, ?string $locale = null): array
    {
        return $this->read($model, $model->getAttributes());
    }

    public function original(Model $model): array
    {
        return $this->read($model, RawOriginal::of($model));
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     * @return array<string, array<string, mixed>>
     */
    private function read(Model $model, array $attributes): array
    {
        $stored = [];

        foreach ($this->definitions->for($model)->names() as $field) {
            $column = $this->column($model, $field);
            $values = $this->decode($model, $column, $attributes[$column] ?? null);

            if ($values !== []) {
                $stored[$field] = $values;
            }
        }

        return $stored;
    }

    public function stage(Model $model, TranslationPatchSet $changes): void
    {
    }

    public function prepare(Model $model, TranslationPatchSet $changes): void
    {
        $fields = $changes->fields();

        if ($fields === []) {
            return;
        }

        $columns = [];

        foreach ($fields as $field) {
            $columns[$field] = $this->column($model, $field);
        }

        $current = $model->exists ? $this->fresh($model, array_values($columns)) : $model->getAttributes();
        $definitions = $this->definitions->for($model)->all();
        $updates = [];

        foreach ($columns as $field => $column) {
            $values = $this->decode($model, $column, $current[$column] ?? null);
            $before = $values;

            foreach ($changes->values()[$field] ?? [] as $locale => $value) {
                $values[(string) $locale] = $this->casts->toStorage($model, $definitions[$field], $value, true);
            }

            foreach ($changes->removed()[$field] ?? [] as $locale) {
                unset($values[$locale]);
            }

            // MySQL and PostgreSQL reformat stored JSON; compare values, not strings.
            if ($values == $before && array_key_exists($column, $model->getAttributes())) {
                continue;
            }

            $updates[$column] = $values === [] ? null : $this->encode($values);
        }

        if ($updates === []) {
            return;
        }

        // Written as raw attributes so model casts on the column never encode twice.
        $model->setRawAttributes(array_replace($model->getAttributes(), $updates));
    }

    public function persist(Model $model, TranslationPatchSet $changes): void
    {
    }

    public function delete(Model $model): void
    {
    }

    public function snapshot(Model $model): mixed
    {
        return null;
    }

    public function restore(Model $model, mixed $snapshot): void
    {
    }

    public function flush(Model $model): void
    {
    }

    public function isLoaded(Model $model): bool
    {
        $attributes = $model->getAttributes();

        foreach ($this->storageAttributes($model) as $column) {
            if (! array_key_exists($column, $attributes)) {
                return false;
            }
        }

        return true;
    }

    public function storageAttributes(Model $model): array
    {
        return array_map(fn (string $field): string => $this->column($model, $field), $this->definitions->for($model)->names());
    }

    public function query(): TranslationQueryCompiler
    {
        return $this->compiler;
    }

    /**
     * @return array<string, mixed>
     */
    public function decode(Model $model, string $column, mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $values = is_string($raw) ? json_decode($raw, true) : $raw;

        if (! is_array($values) || ($values !== [] && array_keys($values) === range(0, count($values) - 1))) {
            throw new TranslationSchemaException(sprintf(
                'The [%s] column of [%s] #%s must hold a JSON object of locale => value.',
                $column,
                get_class($model),
                is_scalar($model->getKey()) ? (string) $model->getKey() : '?'
            ));
        }

        $decoded = [];

        foreach ($values as $locale => $value) {
            if ($value !== null) {
                $decoded[(string) $locale] = $value;
            }
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function encode(array $values): string
    {
        $encoded = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        if ($encoded === false) {
            throw new TranslationSchemaException('The translations cannot be encoded as JSON: '.json_last_error_msg());
        }

        return $encoded;
    }

    /**
     * The model's row read again under a row lock, without scopes or casts.
     *
     * @param  list<string>  $columns
     * @return array<array-key, mixed>
     */
    private function fresh(Model $model, array $columns): array
    {
        $row = $model->getConnection()
            ->table($model->getTable())
            ->where($model->getKeyName(), '=', $model->getKey())
            ->lockForUpdate()
            ->first($columns);

        if ($row === null) {
            $key = $model->getKey();

            throw (new ModelNotFoundException)->setModel(get_class($model), is_int($key) || is_string($key) ? [$key] : []);
        }

        return (array) $row;
    }
}
