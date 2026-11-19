<?php

namespace Pharaonic\Laravel\Translatable\Strategies;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Contracts\TranslationQueryCompiler;
use Pharaonic\Laravel\Translatable\Contracts\TranslationStrategy;
use Pharaonic\Laravel\Translatable\Data\TranslationPatchSet;
use Pharaonic\Laravel\Translatable\Exceptions\TranslationSchemaException;
use Pharaonic\Laravel\Translatable\Query\ColumnsQueryCompiler;
use Pharaonic\Laravel\Translatable\Resolution\DefinitionResolver;
use Pharaonic\Laravel\Translatable\Resolution\LocaleColumnMapper;
use Pharaonic\Laravel\Translatable\Support\CastResolver;

/**
 * One column per field and locale on the model's table: title_en, title_ar,
 * title_pt_br. The locales are the columns the table has; a locale without
 * a column is rejected when it is set.
 *
 * @internal
 */
final class ColumnsStrategy implements TranslationStrategy
{
    public function __construct(
        private DefinitionResolver $definitions,
        private LocaleColumnMapper $mapper,
        private CastResolver $casts,
        private ColumnsQueryCompiler $compiler,
    ) {
    }

    public function name(): string
    {
        return 'columns';
    }

    public function normalizeLocale(string $locale): string
    {
        return LocaleColumnMapper::suffix($locale);
    }

    public function stored(Model $model): array
    {
        return $this->read($model, $model->getAttributes());
    }

    public function original(Model $model): array
    {
        $original = method_exists($model, 'getRawOriginal') ? $model->getRawOriginal() : $model->getOriginal();

        return $this->read($model, is_array($original) ? $original : []);
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     * @return array<string, array<string, mixed>>
     */
    private function read(Model $model, array $attributes): array
    {
        $stored = [];

        foreach ($this->definitions->for($model)->names() as $field) {
            foreach ($this->mapper->localeColumns($model, $field) as $locale => $column) {
                if (isset($attributes[$column])) {
                    $stored[$field][$locale] = $attributes[$column];
                }
            }
        }

        return $stored;
    }

    public function stage(Model $model, TranslationPatchSet $changes): void
    {
        $missing = [];

        foreach ($changes->values() as $field => $locales) {
            foreach (array_keys($locales) as $locale) {
                $column = $this->mapper->localeColumn($model, $field, (string) $locale);

                if (! $this->mapper->hasColumn($model, $column)) {
                    $missing[] = $column;
                }
            }
        }

        if ($missing !== []) {
            throw TranslationSchemaException::missingColumns(get_class($model), $model->getTable(), array_values(array_unique($missing)));
        }
    }

    public function prepare(Model $model, TranslationPatchSet $changes): void
    {
        $definitions = $this->definitions->for($model)->all();
        $updates = [];

        foreach ($changes->values() as $field => $locales) {
            foreach ($locales as $locale => $value) {
                $updates[$this->mapper->localeColumn($model, $field, (string) $locale)] = $this->casts->toStorage($model, $definitions[$field], $value, false);
            }
        }

        foreach ($changes->removed() as $field => $locales) {
            foreach ($locales as $locale) {
                $column = $this->mapper->localeColumn($model, $field, $locale);

                if ($this->mapper->hasColumn($model, $column)) {
                    $updates[$column] = null;
                }
            }
        }

        if ($updates === []) {
            return;
        }

        // Only the changed columns become dirty, so the update never rewrites other locales.
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
        $attributes = [];

        foreach ($this->definitions->for($model)->names() as $field) {
            foreach ($this->mapper->localeColumns($model, $field) as $column) {
                $attributes[] = $column;
            }
        }

        return $attributes;
    }

    public function query(): TranslationQueryCompiler
    {
        return $this->compiler;
    }
}
