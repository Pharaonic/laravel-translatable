<?php

namespace Pharaonic\Laravel\Translatable;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Translatable\Data\LocaleTranslation;
use Pharaonic\Laravel\Translatable\Data\ResolvedTranslation;
use Pharaonic\Laravel\Translatable\Data\TranslationPatchSet;
use Pharaonic\Laravel\Translatable\Exceptions\UnsupportedTranslationQueryException;
use Pharaonic\Laravel\Translatable\Query\TranslationScopes;
use Pharaonic\Laravel\Translatable\Strategies\TableStrategy;
use Pharaonic\Laravel\Translatable\Support\Translator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Translations for an Eloquent model. The model's strategy is its marker
 * interface (TableTranslatable, JsonTranslatable, ColumnsTranslatable) or
 * config("translatable.strategy"), "table" by default.
 *
 * Declare the translated fields in $translatables or translatable().
 */
trait Translatable
{
    private ?TranslationPatchSet $translationPatch = null;

    private ?TranslationPatchSet $translationChanges = null;

    private bool $savingTranslations = false;

    /** @var list<string>|null */
    private ?array $loadedTranslationLocales = null;

    public static function bootTranslatable(): void
    {
        static::saving(function (Model $model) {
            self::translatableService()->onSaving($model);
        });

        static::saved(function (Model $model) {
            self::translatableService()->onSaved($model);
        });

        static::deleted(function (Model $model) {
            self::translatableService()->onDeleted($model);
        });
    }

    // ---------------------------------------------------------------- reading

    /**
     * The exact stored value; null when the locale has none. With $fallback,
     * the displayed value, which may come from a fallback locale.
     */
    public function getTranslation(string $field, ?string $locale = null, bool $fallback = false): mixed
    {
        $translator = self::translatableService();

        return $fallback ? $translator->resolve($this, $field, $locale, true)->value : $translator->exact($this, $field, $locale);
    }

    /**
     * locale => value of one field, or field => [locale => value] of every field.
     *
     * @return array<string, mixed>
     */
    public function getTranslations(?string $field = null): array
    {
        $translator = self::translatableService();

        return $field === null ? $translator->all($this) : $translator->values($this, $field);
    }

    /**
     * field => value of one locale; fields without a value are null.
     *
     * @return array<string, mixed>
     */
    public function getTranslationsForLocale(string $locale): array
    {
        return self::translatableService()->forLocale($this, $locale);
    }

    /**
     * Locales with at least one stored value.
     *
     * @return list<string>
     */
    public function getTranslatedLocales(): array
    {
        return self::translatableService()->translatedLocales($this);
    }

    public function resolveTranslation(string $field, ?string $locale = null): ResolvedTranslation
    {
        return self::translatableService()->resolve($this, $field, $locale);
    }

    /**
     * @throws \Pharaonic\Laravel\Translatable\Exceptions\MissingTranslationException
     */
    public function getTranslationOrFail(string $field, ?string $locale = null): mixed
    {
        return self::translatableService()->orFail($this, $field, $locale);
    }

    /**
     * Whether the locale has a value for the field, or for any field; without a locale, any locale.
     */
    public function hasTranslation(?string $locale = null, ?string $field = null): bool
    {
        return self::translatableService()->has($this, $locale, $field);
    }

    public function hasCompleteTranslation(string $locale): bool
    {
        return self::translatableService()->missing($this, $locale) === [];
    }

    /**
     * Required fields without a value in the locale.
     *
     * @return list<string>
     */
    public function missingTranslations(string $locale): array
    {
        return self::translatableService()->missing($this, $locale);
    }

    /**
     * The percentage (0-100) of required fields with a value in the locale; 100 without required fields.
     */
    public function translationProgress(string $locale): int
    {
        return self::translatableService()->progress($this, $locale);
    }

    // ---------------------------------------------------------------- writing

    /**
     * @return $this
     */
    public function setTranslation(string $field, string $locale, mixed $value): static
    {
        self::translatableService()->set($this, $field, $locale, $value);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $values  locale => value
     * @return $this
     */
    public function setTranslations(string $field, array $values): static
    {
        self::translatableService()->setMany($this, $field, $values);

        return $this;
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations  locale => [field => value]
     * @return $this
     */
    public function fillTranslations(array $translations): static
    {
        self::translatableService()->fill($this, $translations);

        return $this;
    }

    /**
     * @return $this
     */
    public function forgetTranslation(string $field, string $locale): static
    {
        self::translatableService()->forget($this, $field, $locale);

        return $this;
    }

    /**
     * Forget every field of the locale; the table strategy deletes the locale's row on save.
     *
     * @return $this
     */
    public function forgetTranslations(string $locale): static
    {
        self::translatableService()->forgetLocale($this, $locale);

        return $this;
    }

    // ---------------------------------------------------------------- dirty tracking

    /**
     * Unsaved changes that differ from storage: field => [locale => value], null when removed.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDirtyTranslations(): array
    {
        return self::translatableService()->dirty($this);
    }

    public function isTranslationDirty(?string $field = null, ?string $locale = null): bool
    {
        return self::matchesChanges($this->getDirtyTranslations(), $field, $locale);
    }

    /**
     * Changes stored by the last successful save: field => [locale => value], null when removed.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getTranslationChanges(): array
    {
        $changes = [];

        if ($this->translationChanges === null) {
            return $changes;
        }

        foreach ($this->translationChanges->values() as $field => $locales) {
            $changes[$field] = $locales;
        }

        foreach ($this->translationChanges->removed() as $field => $locales) {
            foreach ($locales as $locale) {
                $changes[$field][$locale] = null;
            }
        }

        return $changes;
    }

    public function wasTranslationChanged(?string $field = null, ?string $locale = null): bool
    {
        return self::matchesChanges($this->getTranslationChanges(), $field, $locale);
    }

    // ---------------------------------------------------------------- translate()

    /**
     * Table: the locale's translation model. Json and columns: a LocaleTranslation proxy. Null when missing.
     */
    public function translate(?string $locale = null): Model|LocaleTranslation|null
    {
        return self::translatableService()->translate($this, $locale, false);
    }

    public function translateOrNew(string $locale): Model|LocaleTranslation
    {
        $translation = self::translatableService()->translate($this, $locale, true);

        if ($translation === null) {
            throw new UnsupportedTranslationQueryException('translateOrNew() could not create a translation.');
        }

        return $translation;
    }

    /**
     * The locale's translation, else the fallback locale's.
     */
    public function translateOrDefault(?string $locale = null): Model|LocaleTranslation|null
    {
        return self::translatableService()->translateOrDefault($this, $locale);
    }

    /**
     * @throws NotFoundHttpException when the locale has no translation (v2 behavior).
     */
    public function translateOrFail(string $locale): Model|LocaleTranslation
    {
        return $this->translate($locale) ?? throw new NotFoundHttpException;
    }

    // ---------------------------------------------------------------- table strategy (v2 conventions)

    /**
     * The real translations relation of the table strategy.
     *
     * @return HasMany
     */
    public function translations()
    {
        $strategy = self::translatableService()->strategy($this);

        if (! $strategy instanceof TableStrategy) {
            throw new UnsupportedTranslationQueryException(sprintf('[%s] uses the %s strategy, which has no translations relation. Use getTranslations().', static::class, $strategy->name()));
        }

        return $strategy->relation($this);
    }

    /**
     * Table: the translation models keyed by locale. Json and columns: field => [locale => value].
     *
     * @return Collection<array-key, mixed>|array<string, mixed>
     */
    public function getTranslationsAttribute(): Collection|array
    {
        $strategy = self::translatableService()->strategy($this);

        if ($strategy instanceof TableStrategy) {
            return $strategy->records($this)->keyBy(TableStrategy::LOCALE_COLUMN);
        }

        return $this->getTranslations();
    }

    /**
     * @return list<string>
     */
    public function getLocalesAttribute(): array
    {
        return $this->getTranslatedLocales();
    }

    /**
     * @return string
     */
    public function getTranslatableModel()
    {
        return __CLASS__.'Translation';
    }

    /**
     * @return string
     */
    public function getTranslatableTable()
    {
        return Str::snake(Str::pluralStudly(class_basename($this).'Translation'));
    }

    /**
     * @return string
     */
    public function getTranslatableField()
    {
        return Str::snake(Str::studly(class_basename($this))).'_'.$this->getKeyName();
    }

    // ---------------------------------------------------------------- Eloquent integration

    /**
     * A translated field reads as its displayed value in the current locale;
     * a get accessor of the field receives that value.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (is_string($key) && $key !== '' && self::translatableService()->isField($this, $key)) {
            $value = self::translatableService()->display($this, $key);

            if ($this->hasGetMutator($key)) {
                return $this->mutateAttribute($key, $value);
            }

            return $value;
        }

        return parent::getAttribute($key);
    }

    /**
     * Setting a translated field stages the current locale's value; setting the
     * translations key ("locale" unless $translationsKey says otherwise) fills
     * locale => [field => value]. Both are mass assignable only when fillable.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    public function setAttribute($key, $value)
    {
        if (is_string($key) && $key !== '') {
            $translator = self::translatableService();

            if ($translator->isField($this, $key)) {
                $translator->set($this, $key, null, $value);

                return $this;
            }

            if ($key === $this->translationsKey()) {
                $translator->fill($this, $value);

                return $this;
            }
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return bool
     */
    public function save(array $options = [])
    {
        return self::translatableService()->save($this, $options, function (array $options) {
            return parent::save($options);
        });
    }

    /**
     * Reload the model; unsaved translation changes are discarded.
     *
     * @return $this
     */
    public function refresh()
    {
        self::translatableService()->refreshed($this);

        return parent::refresh();
    }

    /**
     * The replica gets the exact translations as unsaved changes, never the original's translation records.
     *
     * @param  array<int, string>|null  $except
     * @return static
     */
    public function replicate(?array $except = null)
    {
        $replica = parent::replicate($except);

        self::translatableService()->replicated($this, $replica);

        return $replica;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function attributesToArray()
    {
        return self::translatableService()->serialize(
            $this,
            parent::attributesToArray(),
            fn (array $values): array => $this->getArrayableItems($values),
            fn (\DateTimeInterface $date): string => $this->serializeDate($date),
        );
    }

    // ---------------------------------------------------------------- scopes

    public function scopeTranslated(Builder $query, ?string $locale = null): void
    {
        self::translatableScopes()->translated($query, $this, $locale);
    }

    public function scopeNotTranslated(Builder $query, ?string $locale = null): void
    {
        self::translatableScopes()->notTranslated($query, $this, $locale);
    }

    public function scopeWhereHasTranslation(Builder $query, string $locale, ?string $field = null): void
    {
        self::translatableScopes()->whereHas($query, $this, $locale, $field);
    }

    public function scopeWhereMissingTranslation(Builder $query, string $locale, ?string $field = null): void
    {
        self::translatableScopes()->whereHas($query, $this, $locale, $field, true);
    }

    /**
     * v2 signature: $action is "whereHas" or "orWhereHas", $op a comparison operator. Eager loads the translations (v2).
     */
    public function scopeWhereTranslation(Builder $query, string $field, mixed $value, ?string $locale = null, string $action = 'whereHas', string $op = '='): void
    {
        $boolean = match ($action) {
            'whereHas' => 'and',
            'orWhereHas' => 'or',
            default => throw new UnsupportedTranslationQueryException('The action of whereTranslation() must be "whereHas" or "orWhereHas".'),
        };

        self::translatableScopes()->withTranslations($query, $this, null);
        self::translatableScopes()->where($query, $this, $field, $op, $value, $locale, $boolean);
    }

    public function scopeOrWhereTranslation(Builder $query, string $field, mixed $value, ?string $locale = null): void
    {
        self::translatableScopes()->withTranslations($query, $this, null);
        self::translatableScopes()->where($query, $this, $field, '=', $value, $locale, 'or');
    }

    public function scopeWhereTranslationLike(Builder $query, string $field, string $pattern, ?string $locale = null): void
    {
        self::translatableScopes()->withTranslations($query, $this, null);
        self::translatableScopes()->where($query, $this, $field, 'like', $pattern, $locale);
    }

    public function scopeOrWhereTranslationLike(Builder $query, string $field, string $pattern, ?string $locale = null): void
    {
        self::translatableScopes()->withTranslations($query, $this, null);
        self::translatableScopes()->where($query, $this, $field, 'like', $pattern, $locale, 'or');
    }

    /**
     * @param  iterable<mixed>  $values
     */
    public function scopeWhereTranslationIn(Builder $query, string $field, iterable $values, ?string $locale = null): void
    {
        self::translatableScopes()->whereIn($query, $this, $field, $values, $locale);
    }

    /**
     * @param  iterable<mixed>  $values
     */
    public function scopeWhereTranslationNotIn(Builder $query, string $field, iterable $values, ?string $locale = null): void
    {
        self::translatableScopes()->whereIn($query, $this, $field, $values, $locale, true);
    }

    public function scopeWhereTranslationProgress(Builder $query, string $locale, string $operator, int|float $percentage): void
    {
        self::translatableScopes()->whereProgress($query, $this, $locale, $operator, $percentage);
    }

    public function scopeOrderByTranslation(Builder $query, string $field, string $direction = 'asc', ?string $locale = null, bool $fallback = false): void
    {
        self::translatableScopes()->orderBy($query, $this, $field, $direction, $locale, $fallback);
    }

    /**
     * v2: records translated in the locale, ordered by the field, then by key.
     */
    public function scopeTranslatedSorting(Builder $query, string $locale, string $field, string $method = 'asc'): void
    {
        $scopes = self::translatableScopes();

        $scopes->withTranslations($query, $this, null);
        $scopes->whereHas($query, $this, $locale, $field);
        $scopes->orderBy($query, $this, $field, $method, $locale);
        $query->orderBy($this->getQualifiedKeyName(), strtolower($method) === 'desc' ? 'desc' : 'asc');
    }

    /**
     * @param  string|list<string>|null  $locales
     */
    public function scopeWithTranslations(Builder $query, string|array|null $locales = null): void
    {
        self::translatableScopes()->withTranslations($query, $this, $locales);
    }

    // ---------------------------------------------------------------- internal

    /**
     * @internal
     *
     * @return list<string>|null
     */
    public function getLoadedTranslationLocales(): ?array
    {
        return $this->loadedTranslationLocales;
    }

    /**
     * @internal
     *
     * @param  list<string>|null  $locales
     */
    public function setLoadedTranslationLocales(?array $locales): void
    {
        $this->loadedTranslationLocales = $locales;
    }

    /**
     * @internal
     */
    public function translationPatch(): TranslationPatchSet
    {
        return $this->translationPatch ??= new TranslationPatchSet;
    }

    /**
     * @internal
     */
    public function resetTranslationPatch(): void
    {
        $this->translationPatch = null;
    }

    /**
     * @internal
     */
    public function rememberTranslationChanges(TranslationPatchSet $changes): void
    {
        $this->translationChanges = $changes;
    }

    /**
     * @internal
     */
    public function markTranslationSave(bool $saving): void
    {
        $this->savingTranslations = $saving;
    }

    /**
     * @internal
     */
    public function isSavingTranslations(): bool
    {
        return $this->savingTranslations;
    }

    protected function translationsKey(): string
    {
        $key = get_object_vars($this)['translationsKey'] ?? null;

        return is_string($key) && $key !== '' ? $key : 'locale';
    }

    /**
     * @param  array<string, array<string, mixed>>  $changes
     */
    private static function matchesChanges(array $changes, ?string $field, ?string $locale): bool
    {
        foreach ($changes as $changedField => $locales) {
            if ($field !== null && $changedField !== $field) {
                continue;
            }

            if ($locale === null || array_key_exists($locale, $locales)) {
                return true;
            }
        }

        return false;
    }

    private static function translatableService(): Translator
    {
        $translator = Container::getInstance()->make(Translator::class);

        if (! $translator instanceof Translator) {
            throw new UnsupportedTranslationQueryException('The container must resolve '.Translator::class.'.');
        }

        return $translator;
    }

    private static function translatableScopes(): TranslationScopes
    {
        $scopes = Container::getInstance()->make(TranslationScopes::class);

        if (! $scopes instanceof TranslationScopes) {
            throw new UnsupportedTranslationQueryException('The container must resolve '.TranslationScopes::class.'.');
        }

        return $scopes;
    }
}
