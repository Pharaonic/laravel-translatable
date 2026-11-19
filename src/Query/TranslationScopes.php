<?php

namespace Pharaonic\Laravel\Translatable\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Exceptions\UnsupportedTranslationQueryException;
use Pharaonic\Laravel\Translatable\Support\Translator;

/**
 * Validates scope arguments and hands them to the model strategy's compiler.
 * Exact-locale conditions never match values borrowed from another locale.
 *
 * @internal
 */
final class TranslationScopes
{
    public function __construct(private Translator $translator)
    {
    }

    /**
     * At least one present field in the locale (the current locale when null).
     */
    public function translated(Builder $query, Model $model, ?string $locale): void
    {
        $this->withTranslations($query, $model, null);
        $this->compiler($model)->whereAnyPresent($query, $model, $this->translator->fields($model)->names(), [$this->translator->locale($model, $locale)]);
    }

    /**
     * No present field in the locale (the current locale when null).
     */
    public function notTranslated(Builder $query, Model $model, ?string $locale): void
    {
        $this->withTranslations($query, $model, null);
        $this->compiler($model)->whereAnyPresent($query, $model, $this->translator->fields($model)->names(), [$this->translator->locale($model, $locale)], 'and', true);
    }

    public function whereHas(Builder $query, Model $model, string $locale, ?string $field, bool $not = false): void
    {
        $fields = $field === null ? $this->translator->fields($model)->names() : [$this->field($model, $field)];

        $this->compiler($model)->whereAnyPresent($query, $model, $fields, [$this->translator->locale($model, $locale)], 'and', $not);
    }

    /**
     * A comparison on the field's value in the locale, or in any locale when it is null.
     */
    public function where(Builder $query, Model $model, string $field, string $operator, mixed $value, ?string $locale, string $boolean = 'and'): void
    {
        $field = $this->field($model, $field);
        $locales = $locale === null ? [] : [$this->translator->locale($model, $locale)];

        if ($boolean !== 'and' && $boolean !== 'or') {
            throw new UnsupportedTranslationQueryException('The boolean of a translation condition must be "and" or "or".');
        }

        $this->compiler($model)->whereValue($query, $model, $field, Operators::normalize($operator), $value, $locales, $boolean);
    }

    /**
     * @param  iterable<mixed>  $values
     */
    public function whereIn(Builder $query, Model $model, string $field, iterable $values, ?string $locale, bool $not = false): void
    {
        $list = [];

        foreach ($values as $value) {
            $list[] = $value;
        }

        $this->compiler($model)->whereValueIn($query, $model, $this->field($model, $field), $list, $this->translator->locale($model, $locale), 'and', $not);
    }

    public function whereProgress(Builder $query, Model $model, string $locale, string $operator, float|int $percentage): void
    {
        $operator = Operators::normalize($operator);

        if ($operator === 'like' || $operator === 'not like') {
            throw new UnsupportedTranslationQueryException('whereTranslationProgress() compares numbers; LIKE is not supported.');
        }

        $this->compiler($model)->whereProgress($query, $model, $this->translator->fields($model)->required(), $this->translator->locale($model, $locale), $operator, (float) $percentage);
    }

    /**
     * Missing values sort last in both directions. With $fallback, a missing
     * value sorts by the fallback locale's value instead.
     */
    public function orderBy(Builder $query, Model $model, string $field, string $direction, ?string $locale, bool $fallback = false): void
    {
        $field = $this->field($model, $field);
        $locale = $this->translator->locale($model, $locale);
        $locales = $fallback ? $this->translator->chain($model, $field, $locale) : [$locale];

        $this->compiler($model)->orderBy($query, $model, $field, $locales, Operators::direction($direction));
    }

    /**
     * @param  string|list<string>|null  $locales
     */
    public function withTranslations(Builder $query, Model $model, string|array|null $locales): void
    {
        $locales = $locales === null ? null : array_map(
            fn (string $locale): string => $this->translator->locale($model, $locale),
            is_array($locales) ? array_values($locales) : [$locales]
        );

        $this->compiler($model)->withTranslations($query, $model, $locales);
    }

    private function field(Model $model, string $field): string
    {
        return $this->translator->definition($model, $field)->field;
    }

    private function compiler(Model $model): \Pharaonic\Laravel\Translatable\Contracts\TranslationQueryCompiler
    {
        return $this->translator->strategy($model)->query();
    }
}
