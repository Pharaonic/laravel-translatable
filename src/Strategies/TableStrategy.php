<?php

namespace Pharaonic\Laravel\Translatable\Strategies;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Translatable\Contracts\TranslationQueryCompiler;
use Pharaonic\Laravel\Translatable\Contracts\TranslationStrategy;
use Pharaonic\Laravel\Translatable\Data\TranslationDefinition;
use Pharaonic\Laravel\Translatable\Data\TranslationPatchSet;
use Pharaonic\Laravel\Translatable\Exceptions\InvalidTranslatableDefinitionException;
use Pharaonic\Laravel\Translatable\Query\TableQueryCompiler;
use Pharaonic\Laravel\Translatable\Resolution\DefinitionResolver;
use Pharaonic\Laravel\Translatable\Support\CastResolver;

/**
 * One translation row per model and locale, through a real HasMany relation.
 *
 * Conventions (v2): the PostTranslation model, the post_translations table,
 * the post_id foreign key and the locale column, each overridable on the
 * model with getTranslatableModel(), getTranslatableTable() and getTranslatableField().
 *
 * @internal
 */
final class TableStrategy implements TranslationStrategy
{
    public const LOCALE_COLUMN = 'locale';

    public function __construct(
        private DefinitionResolver $definitions,
        private CastResolver $casts,
        private TableQueryCompiler $compiler,
    ) {
    }

    public function name(): string
    {
        return 'table';
    }

    public function relation(Model $model): HasMany
    {
        return $model->hasMany($this->translationModel($model), $this->foreignKey($model));
    }

    /**
     * @return class-string<Model>
     */
    public function translationModel(Model $model): string
    {
        $class = method_exists($model, 'getTranslatableModel') ? $model->getTranslatableModel() : get_class($model).'Translation';

        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            throw new InvalidTranslatableDefinitionException(sprintf(
                'The translation model of [%s] must be an Eloquent model, %s given.',
                get_class($model),
                is_string($class) ? "[{$class}]" : get_debug_type($class)
            ));
        }

        return $class;
    }

    public function foreignKey(Model $model): string
    {
        $key = method_exists($model, 'getTranslatableField') ? $model->getTranslatableField() : null;

        return is_string($key) && $key !== '' ? $key : Str::snake(class_basename($model)).'_'.$model->getKeyName();
    }

    /**
     * The loaded translation records; loads them once when needed.
     */
    public function records(Model $model, bool $attach = false): Collection
    {
        if (! $model->relationLoaded('translations')) {
            // A model being created has no rows yet; cache the empty relation only when a record is staged on it.
            if (! $model->exists && ! $attach) {
                return new Collection;
            }

            $model->setRelation('translations', $model->exists ? $this->relation($model)->get() : new Collection);
        }

        $records = $model->getRelation('translations');

        if (! $records instanceof Collection) {
            $records = new Collection(is_iterable($records) ? $records : []);
            $model->setRelation('translations', $records);
        }

        return $records;
    }

    public function record(Model $model, string $locale): ?Model
    {
        foreach ($this->records($model) as $record) {
            if ($record instanceof Model && $record->getAttribute(self::LOCALE_COLUMN) === $locale) {
                return $record;
            }
        }

        return null;
    }

    /**
     * The locale's record, or a new one attached to the relation.
     */
    public function recordOrNew(Model $model, string $locale): Model
    {
        $record = $this->record($model, $locale);

        if ($record === null) {
            $class = $this->translationModel($model);
            $record = new $class;
            $record->setAttribute(self::LOCALE_COLUMN, $locale);

            if ($model->exists) {
                $record->setAttribute($this->foreignKey($model), $model->getKey());
            }

            $this->records($model, true)->push($record);
        }

        return $record;
    }

    public function normalizeLocale(string $locale): string
    {
        return $locale;
    }

    public function stored(Model $model): array
    {
        $fields = $this->definitions->for($model)->all();
        $stored = [];

        foreach ($this->records($model) as $record) {
            $locale = $record instanceof Model ? $record->getAttribute(self::LOCALE_COLUMN) : null;

            if (! $record instanceof Model || ! is_string($locale)) {
                continue;
            }

            foreach ($fields as $field => $definition) {
                $value = $this->read($record, $definition);

                if ($value !== null) {
                    $stored[$field][$locale] = $value;
                }
            }
        }

        return $stored;
    }

    public function original(Model $model): array
    {
        $fields = $this->definitions->for($model)->all();
        $original = [];

        foreach ($this->records($model) as $record) {
            if (! $record instanceof Model || ! $record->exists) {
                continue;
            }

            $locale = self::raw($record, self::LOCALE_COLUMN) ?? $record->getAttribute(self::LOCALE_COLUMN);

            if (! is_string($locale)) {
                continue;
            }

            foreach ($fields as $field => $definition) {
                $value = $definition->type === null ? $record->getOriginal($field) : self::raw($record, $field);

                if ($value !== null) {
                    $original[$field][$locale] = $value;
                }
            }
        }

        return $original;
    }

    public function stage(Model $model, TranslationPatchSet $changes): void
    {
        $fields = $this->definitions->for($model)->all();

        foreach ($changes->values() as $field => $locales) {
            foreach ($locales as $locale => $value) {
                $this->write($model, $this->recordOrNew($model, (string) $locale), $fields[$field], $value);
            }
        }

        foreach ($changes->removed() as $field => $locales) {
            foreach ($locales as $locale) {
                $record = $this->record($model, $locale);

                if ($record !== null) {
                    $this->write($model, $record, $fields[$field], null);
                }
            }
        }
    }

    public function prepare(Model $model, TranslationPatchSet $changes): void
    {
    }

    public function persist(Model $model, TranslationPatchSet $changes): void
    {
        if (! $model->relationLoaded('translations')) {
            return;
        }

        $foreignKey = $this->foreignKey($model);
        $records = $this->records($model);
        $removed = $changes->removedLocales();

        foreach ($records->all() as $index => $record) {
            if (! $record instanceof Model) {
                continue;
            }

            if (in_array($record->getAttribute(self::LOCALE_COLUMN), $removed, true)) {
                if ($record->exists) {
                    $record->delete();
                }

                $records->forget($index);

                continue;
            }

            // A record always belongs to the model it is loaded on.
            if ($record->getAttribute($foreignKey) != $model->getKey()) {
                $record->setAttribute($foreignKey, $model->getKey());
            }

            if (! $record->exists && ! $this->hasContent($record, $foreignKey)) {
                continue;
            }

            if (! $record->exists || $record->isDirty()) {
                $records->put($index, $this->save($model, $record));
            }
        }
    }

    public function delete(Model $model): void
    {
        $this->relation($model)->delete();
        $model->unsetRelation('translations');
    }

    public function snapshot(Model $model): mixed
    {
        if (! $model->relationLoaded('translations')) {
            return null;
        }

        $items = $this->records($model)->all();
        $states = [];

        foreach ($items as $record) {
            if ($record instanceof Model) {
                $states[] = [$record, $record->exists, $record->getAttributes(), method_exists($record, 'getRawOriginal') ? $record->getRawOriginal() : $record->getOriginal()];
            }
        }

        return ['items' => $items, 'states' => $states];
    }

    public function restore(Model $model, mixed $snapshot): void
    {
        if (! is_array($snapshot) || ! isset($snapshot['items'], $snapshot['states']) || ! is_array($snapshot['items']) || ! is_array($snapshot['states'])) {
            $model->unsetRelation('translations');

            return;
        }

        foreach ($snapshot['states'] as $state) {
            if (! is_array($state) || ! ($state[0] ?? null) instanceof Model || ! is_array($state[2] ?? null) || ! is_array($state[3] ?? null)) {
                continue;
            }

            $state[0]->setRawAttributes($state[3], true);
            $state[0]->setRawAttributes($state[2]);
            $state[0]->exists = (bool) $state[1];
        }

        $model->setRelation('translations', new Collection($snapshot['items']));
    }

    public function flush(Model $model): void
    {
    }

    public function isLoaded(Model $model): bool
    {
        return $model->relationLoaded('translations');
    }

    public function storageAttributes(Model $model): array
    {
        return [];
    }

    public function query(): TranslationQueryCompiler
    {
        return $this->compiler;
    }

    private function read(Model $record, TranslationDefinition $definition): mixed
    {
        // Untyped fields keep the translation model's own casts and accessors (v2 behavior).
        return $definition->type === null
            ? $record->getAttribute($definition->field)
            : ($record->getAttributes()[$definition->field] ?? null);
    }

    private function write(Model $model, Model $record, TranslationDefinition $definition, mixed $value): void
    {
        if ($definition->type === null) {
            $record->setAttribute($definition->field, $value);

            return;
        }

        $record->setRawAttributes(array_replace($record->getAttributes(), [
            $definition->field => $this->casts->toStorage($model, $definition, $value, false),
        ]));
    }

    private function hasContent(Model $record, string $foreignKey): bool
    {
        $attributes = $record->getAttributes();
        unset($attributes[$foreignKey], $attributes[self::LOCALE_COLUMN], $attributes[$record->getKeyName()]);

        return array_filter($attributes, fn (mixed $value): bool => $value !== null) !== [];
    }

    /**
     * Insert or update one record. Two writers can insert the same locale at
     * once; the unique (foreign key, locale) index rejects the second, which
     * then updates the row the first created. The savepoint keeps PostgreSQL's
     * transaction usable after the rejected insert.
     */
    private function save(Model $model, Model $record): Model
    {
        if ($record->exists) {
            $record->save();

            return $record;
        }

        try {
            $record->getConnection()->transaction(function () use ($record) {
                $record->save();
            });

            return $record;
        } catch (QueryException $exception) {
            if (! self::isUniqueViolation($exception)) {
                throw $exception;
            }

            $existing = $this->relation($model)
                ->where(self::LOCALE_COLUMN, $record->getAttribute(self::LOCALE_COLUMN))
                ->lockForUpdate()
                ->first();

            if (! $existing instanceof Model) {
                throw $exception;
            }

            $changes = $record->getAttributes();
            unset($changes[$record->getKeyName()]);

            $existing->setRawAttributes(array_replace($existing->getAttributes(), $changes));
            $existing->save();

            return $existing;
        }
    }

    private static function raw(Model $record, string $key): mixed
    {
        return method_exists($record, 'getRawOriginal') ? $record->getRawOriginal($key) : $record->getOriginal($key);
    }

    private static function isUniqueViolation(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();
        $message = $exception->getMessage();

        return in_array($code, ['23000', '23505'], true)
            && (str_contains($message, 'UNIQUE') || str_contains($message, 'Duplicate entry') || str_contains($message, 'duplicate key') || str_contains($message, 'unique'));
    }
}
