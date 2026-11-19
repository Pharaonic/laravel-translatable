<?php

namespace Pharaonic\Laravel\Translatable\Support;

use Closure;
use DateTimeInterface;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Pharaonic\Laravel\Translatable\Contracts\FallbackPolicy;
use Pharaonic\Laravel\Translatable\Contracts\TranslationStrategy;
use Pharaonic\Laravel\Translatable\Data\LocaleTranslation;
use Pharaonic\Laravel\Translatable\Data\ResolvedTranslation;
use Pharaonic\Laravel\Translatable\Data\TranslationDefinition;
use Pharaonic\Laravel\Translatable\Data\TranslationPatchSet;
use Pharaonic\Laravel\Translatable\Events\TranslationRemoved;
use Pharaonic\Laravel\Translatable\Events\TranslationSet;
use Pharaonic\Laravel\Translatable\Exceptions\InvalidTranslatableDefinitionException;
use Pharaonic\Laravel\Translatable\Exceptions\MissingTranslationException;
use Pharaonic\Laravel\Translatable\Exceptions\TranslationConflictException;
use Pharaonic\Laravel\Translatable\Exceptions\UnknownTranslatableFieldException;
use Pharaonic\Laravel\Translatable\Resolution\DefinitionResolver;
use Pharaonic\Laravel\Translatable\Resolution\FieldRegistry;
use Pharaonic\Laravel\Translatable\Resolution\StrategyResolver;
use Pharaonic\Laravel\Translatable\Strategies\TableStrategy;
use Throwable;

/**
 * Reads, writes and saves translations for any strategy. Stateless: pending
 * changes live on each model, so one instance serves every model and request.
 * Request-bound services come from the current container on every call, never
 * from the one that built this instance (Octane runs each request in a copy).
 *
 * @internal
 */
final class Translator
{
    private const ATTEMPTS = 3;

    public function __construct(
        private StrategyResolver $strategies,
        private DefinitionResolver $definitions,
        private CastResolver $casts,
    ) {
    }

    public function strategy(Model $model): TranslationStrategy
    {
        return $this->strategies->resolve($model);
    }

    public function fields(Model $model): FieldRegistry
    {
        return $this->definitions->for($model);
    }

    public function isField(Model $model, string $key): bool
    {
        return $this->fields($model)->has($key);
    }

    public function definition(Model $model, string $field): TranslationDefinition
    {
        $definition = $this->fields($model)->get($field);

        if ($definition === null) {
            throw UnknownTranslatableFieldException::forField($field, get_class($model));
        }

        return $definition;
    }

    /**
     * A validated locale, as the model's strategy stores it; null is Laravel's
     * current locale, read on every call.
     */
    public function locale(Model $model, ?string $locale = null): string
    {
        return $this->strategy($model)->normalizeLocale(Locales::validate($model, $locale ?? App::getLocale(), []));
    }

    // ---------------------------------------------------------------- reads

    /**
     * The exact value of one locale, pending changes included; null when missing.
     */
    public function exact(Model $model, string $field, ?string $locale = null): mixed
    {
        $definition = $this->definition($model, $field);
        $locale = $this->locale($model, $locale);
        $patch = $this->patch($model);

        if ($patch->hasValue($field, $locale)) {
            return $this->normalize($model, $definition, $patch->value($field, $locale));
        }

        if ($patch->isRemoved($field, $locale)) {
            return null;
        }

        $stored = $this->strategy($model)->stored($model)[$field][$locale] ?? null;

        return $this->casts->fromStorage($model, $definition, $stored);
    }

    /**
     * locale => value of one field, pending changes included, sorted by locale.
     *
     * @return array<string, mixed>
     */
    public function values(Model $model, string $field): array
    {
        $definition = $this->definition($model, $field);
        $patch = $this->patch($model);
        $values = [];

        foreach ($this->strategy($model)->stored($model)[$field] ?? [] as $locale => $value) {
            if (! $patch->isRemoved($field, $locale)) {
                $values[$locale] = $this->casts->fromStorage($model, $definition, $value);
            }
        }

        foreach ($patch->values()[$field] ?? [] as $locale => $value) {
            $values[(string) $locale] = $this->normalize($model, $definition, $value);
        }

        $values = array_filter($values, fn (mixed $value): bool => $value !== null);
        ksort($values, SORT_STRING);

        return $values;
    }

    /**
     * field => locale => value.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(Model $model): array
    {
        $all = [];

        foreach ($this->fields($model)->names() as $field) {
            $all[$field] = $this->values($model, $field);
        }

        return $all;
    }

    /**
     * field => value of one locale; missing fields are null.
     *
     * @return array<string, mixed>
     */
    public function forLocale(Model $model, string $locale): array
    {
        $locale = $this->locale($model, $locale);
        $values = [];

        foreach ($this->fields($model)->names() as $field) {
            $values[$field] = $this->exact($model, $field, $locale);
        }

        return $values;
    }

    /**
     * Locales with at least one present value.
     *
     * @return list<string>
     */
    public function translatedLocales(Model $model): array
    {
        $locales = [];

        foreach ($this->all($model) as $values) {
            foreach (array_keys($values) as $locale) {
                $locales[(string) $locale] = true;
            }
        }

        return array_map('strval', array_keys($locales));
    }

    public function resolve(Model $model, string $field, ?string $locale = null, ?bool $fallback = null): ResolvedTranslation
    {
        $definition = $this->definition($model, $field);
        $requested = $this->locale($model, $locale);
        $fallback ??= $definition->fallback ?? FallbackResolver::fallbackLocale() !== null;

        $chain = $fallback ? $this->chain($model, $field, $requested) : [$requested];

        foreach ($chain as $candidate) {
            $value = $this->exact($model, $field, $candidate);

            if ($value !== null) {
                return new ResolvedTranslation($field, $value, $requested, $candidate, $candidate !== $requested, false);
            }
        }

        return new ResolvedTranslation($field, null, $requested, null, false, true);
    }

    public function display(Model $model, string $field): mixed
    {
        return $this->resolve($model, $field)->value;
    }

    public function orFail(Model $model, string $field, ?string $locale = null): mixed
    {
        $value = $this->exact($model, $field, $locale);

        if ($value === null) {
            throw MissingTranslationException::forField(get_class($model), $field, $this->locale($model, $locale));
        }

        return $value;
    }

    public function has(Model $model, ?string $locale = null, ?string $field = null): bool
    {
        if ($field !== null) {
            $this->definition($model, $field);
        }

        $fields = $field !== null ? [$field] : $this->fields($model)->names();

        if ($locale === null) {
            foreach ($fields as $name) {
                if ($this->values($model, $name) !== []) {
                    return true;
                }
            }

            return false;
        }

        foreach ($fields as $name) {
            if ($this->exact($model, $name, $locale) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Required fields without an exact value in the locale.
     *
     * @return list<string>
     */
    public function missing(Model $model, string $locale): array
    {
        return array_values(array_filter(
            $this->fields($model)->required(),
            fn (string $field): bool => $this->exact($model, $field, $locale) === null
        ));
    }

    /**
     * The percentage of required fields with an exact value; 100 when no field is required.
     */
    public function progress(Model $model, string $locale): int
    {
        $required = $this->fields($model)->required();

        if ($required === []) {
            return 100;
        }

        return (int) round((count($required) - count($this->missing($model, $locale))) * 100 / count($required));
    }

    /**
     * @return list<string>
     */
    public function chain(Model $model, string $field, string $locale): array
    {
        $policy = Container::getInstance()->make(FallbackPolicy::class);
        $chain = $policy instanceof FallbackPolicy ? $policy->chain($model, $field, $locale) : [$locale];
        $unique = [];

        $strategy = $this->strategy($model);

        foreach ([$locale, ...$chain] as $candidate) {
            if (! is_string($candidate) || ! Locales::isWellFormed($candidate)) {
                continue;
            }

            $candidate = $strategy->normalizeLocale($candidate);

            if (! in_array($candidate, $unique, true)) {
                $unique[] = $candidate;
            }

            if (count($unique) === 10) {
                break;
            }
        }

        return $unique;
    }

    // ---------------------------------------------------------------- writes

    public function set(Model $model, string $field, ?string $locale, mixed $value): void
    {
        $definition = $this->definition($model, $field);
        $locale = $this->locale($model, $locale);

        // Validates the type now, so a wrong value fails where it is set.
        $this->casts->toStorage($model, $definition, $value, true);

        $this->patch($model)->set($field, $locale, $value);
        $this->strategy($model)->stage($model, $this->patch($model));
    }

    /**
     * @param  array<mixed>  $values  locale => value
     */
    public function setMany(Model $model, string $field, array $values): void
    {
        foreach ($values as $locale => $value) {
            if (! is_string($locale)) {
                throw new InvalidTranslatableDefinitionException("setTranslations() expects a locale => value map for [{$field}].");
            }

            $this->set($model, $field, $locale, $value);
        }
    }

    /**
     * @param  mixed  $payload  locale => [field => value]
     */
    public function fill(Model $model, mixed $payload): void
    {
        if (! is_array($payload)) {
            throw new InvalidTranslatableDefinitionException('Translations must be given as locale => [field => value].');
        }

        foreach ($payload as $locale => $values) {
            if (! is_string($locale) || ! is_array($values)) {
                throw new InvalidTranslatableDefinitionException('Translations must be given as locale => [field => value].');
            }

            foreach ($values as $field => $value) {
                if (! is_string($field)) {
                    throw new InvalidTranslatableDefinitionException('Translations must be given as locale => [field => value].');
                }

                $this->set($model, $field, $locale, $value);
            }
        }
    }

    public function forget(Model $model, string $field, string $locale): void
    {
        $this->definition($model, $field);
        $this->patch($model)->forget($field, $this->locale($model, $locale));
        $this->strategy($model)->stage($model, $this->patch($model));
    }

    public function forgetLocale(Model $model, string $locale): void
    {
        $this->patch($model)->forgetLocale($this->locale($model, $locale), $this->fields($model)->names());
        $this->strategy($model)->stage($model, $this->patch($model));
    }

    /**
     * field => locale => new value (null when removed), for changes that differ from storage.
     *
     * @return array<string, array<string, mixed>>
     */
    public function dirty(Model $model): array
    {
        $patch = $this->patch($model);
        $original = $this->strategy($model)->original($model);
        $dirty = [];

        foreach ($patch->values() as $field => $locales) {
            $definition = $this->definition($model, $field);

            foreach ($locales as $locale => $value) {
                $value = $this->normalize($model, $definition, $value);

                if ($value != $this->casts->fromStorage($model, $definition, $original[$field][$locale] ?? null)) {
                    $dirty[$field][(string) $locale] = $value;
                }
            }
        }

        foreach ($patch->removed() as $field => $locales) {
            foreach ($locales as $locale) {
                if (isset($original[$field][$locale])) {
                    $dirty[$field][$locale] = null;
                }
            }
        }

        return $dirty;
    }

    // ---------------------------------------------------------------- translate()

    /**
     * Table: the translation model. Json and columns: a LocaleTranslation proxy.
     */
    public function translate(Model $model, ?string $locale, bool $create): Model|LocaleTranslation|null
    {
        $locale = $this->locale($model, $locale);
        $strategy = $this->strategy($model);

        if ($strategy instanceof TableStrategy) {
            return $create ? $strategy->recordOrNew($model, $locale) : $strategy->record($model, $locale);
        }

        return $create || $this->has($model, $locale) ? new LocaleTranslation($this, $model, $locale) : null;
    }

    public function translateOrDefault(Model $model, ?string $locale): Model|LocaleTranslation|null
    {
        $requested = $this->locale($model, $locale);
        $fallback = FallbackResolver::fallbackLocale();
        $fallback = $fallback === null ? null : $this->strategy($model)->normalizeLocale($fallback);

        foreach (array_unique(array_filter([$requested, $fallback])) as $candidate) {
            $translation = $this->translate($model, $candidate, false);

            if ($translation !== null) {
                return $translation;
            }
        }

        return null;
    }

    // ---------------------------------------------------------------- lifecycle

    /**
     * Save the model and its translations in one transaction on the model's connection.
     *
     * @param  array<string, mixed>  $options
     * @param  Closure(array<string, mixed>): bool  $parentSave
     */
    public function save(Model $model, array $options, Closure $parentSave): bool
    {
        $this->beforeSaving($model);
        $strategy = $this->strategy($model);
        $patch = $this->patch($model);
        $this->prune($model, $patch);
        $dispatcher = $model::getEventDispatcher();
        // withoutEvents() and saveQuietly() remove the dispatcher or swap in a NullDispatcher.
        $quiet = $dispatcher === null || is_a($dispatcher, 'Illuminate\\Events\\NullDispatcher');
        $connection = $model->getConnection();
        $previous = $patch->isEmpty() ? [] : $this->previousValues($model, $patch);

        $saved = false;

        for ($attempt = 1; ; $attempt++) {
            $state = $this->snapshot($model, $strategy);
            $this->saving($model, true);

            try {
                $saved = $connection->transaction(function () use ($model, $options, $parentSave, $strategy, $patch, $quiet) {
                    // Without an event dispatcher (saveQuietly, withoutEvents) the listeners do not run.
                    if ($quiet) {
                        $strategy->prepare($model, $patch);
                    }

                    if ($parentSave($options) === false) {
                        return false;
                    }

                    if ($quiet) {
                        $strategy->persist($model, $patch);
                    }

                    return true;
                });

                break;
            } catch (Throwable $exception) {
                $this->restore($model, $strategy, $state);

                if (! self::isConcurrencyError($exception) || $connection->transactionLevel() > 0) {
                    throw $exception;
                }

                if ($attempt >= self::ATTEMPTS) {
                    throw TranslationConflictException::afterRetries(get_class($model), $attempt, $exception);
                }
            } finally {
                $this->saving($model, false);
            }
        }

        if (! $saved) {
            return false;
        }

        $this->committed($model, $patch, $previous);

        return true;
    }

    /**
     * "saving" listener: write row-level translations before the model row.
     */
    public function onSaving(Model $model): void
    {
        if (! $this->isSaving($model)) {
            $this->beforeSaving($model);
        }

        $this->strategy($model)->prepare($model, $this->patch($model));
    }

    /**
     * "saved" listener: write the table strategy's rows; finish the save when
     * it did not go through Translatable::save() (a model overriding save()).
     */
    public function onSaved(Model $model): void
    {
        $patch = $this->patch($model);
        $this->strategy($model)->persist($model, $patch);

        if (! $this->isSaving($model)) {
            $this->committed($model, $patch, []);
        }
    }

    public function onDeleted(Model $model): void
    {
        if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
            return;
        }

        $this->strategy($model)->delete($model);
    }

    public function refreshed(Model $model): void
    {
        $this->resetPatch($model);
        $this->strategy($model)->flush($model);
    }

    /**
     * Stage every exact value of the original on its replica, without its translation records.
     */
    public function replicated(Model $original, Model $replica): void
    {
        $values = $this->all($original);

        $replica->unsetRelation('translations');
        $this->resetPatch($replica);

        foreach ($values as $field => $locales) {
            foreach ($locales as $locale => $value) {
                $this->set($replica, $field, (string) $locale, $value);
            }
        }
    }

    /**
     * Serialized attributes: storage columns removed, each translated field as its displayed value.
     *
     * @param  array<array-key, mixed>  $attributes
     * @param  Closure(array<array-key, mixed>): array<array-key, mixed>  $arrayable
     * @param  Closure(DateTimeInterface): string  $serializeDate
     * @return array<array-key, mixed>
     */
    public function serialize(Model $model, array $attributes, Closure $arrayable, Closure $serializeDate): array
    {
        $strategy = $this->strategy($model);

        foreach ($strategy->storageAttributes($model) as $column) {
            unset($attributes[$column]);
        }

        if (! $strategy->isLoaded($model) && $this->patch($model)->isEmpty()) {
            return $attributes;
        }

        $values = [];

        foreach ($this->fields($model)->names() as $field) {
            $value = $this->display($model, $field);
            $values[$field] = $value instanceof DateTimeInterface ? $serializeDate($value) : $value;
        }

        return array_merge($attributes, $arrayable($values));
    }

    // ---------------------------------------------------------------- internals

    public function patch(Model $model): TranslationPatchSet
    {
        $patch = method_exists($model, 'translationPatch') ? $model->translationPatch() : null;

        if (! $patch instanceof TranslationPatchSet) {
            throw new InvalidTranslatableDefinitionException('['.get_class($model).'] must use the Translatable trait.');
        }

        return $patch;
    }

    /**
     * The model's beforeSavingTranslations() hook (translated slugs), before anything is written.
     */
    private function beforeSaving(Model $model): void
    {
        if (method_exists($model, 'beforeSavingTranslations')) {
            $model->beforeSavingTranslations();
        }
    }

    private function resetPatch(Model $model): void
    {
        if (method_exists($model, 'resetTranslationPatch')) {
            $model->resetTranslationPatch();
        }
    }

    private function saving(Model $model, bool $saving): void
    {
        if (method_exists($model, 'markTranslationSave')) {
            $model->markTranslationSave($saving);
        }
    }

    private function isSaving(Model $model): bool
    {
        return method_exists($model, 'isSavingTranslations') && $model->isSavingTranslations() === true;
    }

    /**
     * Drop staged changes that match storage, so they write nothing and dispatch no event.
     */
    private function prune(Model $model, TranslationPatchSet $patch): void
    {
        if ($patch->isEmpty()) {
            return;
        }

        $original = $this->strategy($model)->original($model);

        foreach ($patch->values() as $field => $locales) {
            $definition = $this->definition($model, $field);

            foreach ($locales as $locale => $value) {
                $stored = $original[$field][$locale] ?? null;

                if ($stored !== null && $this->normalize($model, $definition, $value) == $this->casts->fromStorage($model, $definition, $stored)) {
                    $patch->discard($field, (string) $locale);
                }
            }
        }

        foreach ($patch->removed() as $field => $locales) {
            foreach ($locales as $locale) {
                if (! isset($original[$field][$locale])) {
                    $patch->discard($field, $locale);
                }
            }
        }
    }

    private function normalize(Model $model, TranslationDefinition $definition, mixed $value): mixed
    {
        return $this->casts->fromStorage($model, $definition, $this->casts->toStorage($model, $definition, $value, true));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function previousValues(Model $model, TranslationPatchSet $patch): array
    {
        $original = $this->strategy($model)->original($model);
        $previous = [];

        foreach ($patch->fields() as $field) {
            $definition = $this->definition($model, $field);

            foreach ($original[$field] ?? [] as $locale => $value) {
                $previous[$field][$locale] = $this->casts->fromStorage($model, $definition, $value);
            }
        }

        return $previous;
    }

    /**
     * @param  array<string, array<string, mixed>>  $previous
     */
    private function committed(Model $model, TranslationPatchSet $patch, array $previous): void
    {
        $events = [];

        foreach ($patch->values() as $field => $locales) {
            $definition = $this->definition($model, $field);

            foreach ($locales as $locale => $value) {
                $locale = (string) $locale;
                $events[] = new TranslationSet($model, $field, $locale, $this->normalize($model, $definition, $value), $previous[$field][$locale] ?? null);
            }
        }

        foreach ($patch->removed() as $field => $locales) {
            foreach ($locales as $locale) {
                if (! isset($previous[$field][$locale])) {
                    continue;
                }

                $events[] = new TranslationRemoved($model, $field, $locale, $previous[$field][$locale] ?? null);
            }
        }

        if (method_exists($model, 'rememberTranslationChanges')) {
            $model->rememberTranslationChanges($patch);
        }

        $this->resetPatch($model);
        $this->strategy($model)->flush($model);

        if ($events === []) {
            return;
        }

        $dispatch = function () use ($events) {
            foreach ($events as $event) {
                Event::dispatch($event);
            }
        };

        $connection = $model->getConnection();
        $container = Container::getInstance();
        $transactions = $container->bound('db.transactions') ? $container->make('db.transactions') : null;

        // Inside an outer transaction, wait for its commit where Laravel tracks it (8.19+).
        if ($connection->transactionLevel() > 0 && is_object($transactions) && method_exists($transactions, 'addCallback')) {
            $transactions->addCallback($dispatch);

            return;
        }

        $dispatch();
    }

    /**
     * @return array{0: array<array-key, mixed>, 1: array<array-key, mixed>, 2: bool, 3: bool, 4: mixed}
     */
    private function snapshot(Model $model, TranslationStrategy $strategy): array
    {
        $original = method_exists($model, 'getRawOriginal') ? $model->getRawOriginal() : $model->getOriginal();

        return [$model->getAttributes(), is_array($original) ? $original : [], $model->exists, $model->wasRecentlyCreated, $strategy->snapshot($model)];
    }

    /**
     * @param  array{0: array<array-key, mixed>, 1: array<array-key, mixed>, 2: bool, 3: bool, 4: mixed}  $state
     */
    private function restore(Model $model, TranslationStrategy $strategy, array $state): void
    {
        $model->setRawAttributes($state[1], true);
        $model->setRawAttributes($state[0]);
        $model->exists = $state[2];
        $model->wasRecentlyCreated = $state[3];
        $strategy->restore($model, $state[4]);
    }

    private static function isConcurrencyError(Throwable $exception): bool
    {
        if (! $exception instanceof QueryException) {
            return false;
        }

        $message = $exception->getMessage();

        return in_array((string) $exception->getCode(), ['40001', '40P01'], true)
            || str_contains($message, 'Deadlock found')
            || str_contains($message, 'deadlock detected')
            || str_contains($message, 'Lock wait timeout exceeded')
            || str_contains($message, 'database is locked');
    }
}
