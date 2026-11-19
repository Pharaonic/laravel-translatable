<?php

namespace Pharaonic\Laravel\Translatable\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Compiles translation conditions and ordering for one strategy.
 *
 * Every field and locale reaching a compiler is already validated; values are bound.
 *
 * @internal
 */
interface TranslationQueryCompiler
{
    /**
     * At least one of the fields has a value in one of the locales.
     *
     * @param  list<string>  $fields
     * @param  list<string>  $locales
     * @param  Builder<Model>  $query
     */
    public function whereAnyPresent(Builder $query, Model $model, array $fields, array $locales, string $boolean = 'and', bool $not = false): void;

    /**
     * A comparison on the value of the field in one of the locales, or in any locale when the list is empty.
     *
     * @param  list<string>  $locales
     * @param  Builder<Model>  $query
     */
    public function whereValue(Builder $query, Model $model, string $field, string $operator, mixed $value, array $locales, string $boolean = 'and'): void;

    /**
     * The value of the field in the locale is one of the values.
     *
     * @param  list<mixed>  $values
     * @param  Builder<Model>  $query
     */
    public function whereValueIn(Builder $query, Model $model, string $field, array $values, string $locale, string $boolean = 'and', bool $not = false): void;

    /**
     * The percentage of present required fields in the locale compares to the given number.
     *
     * @param  list<string>  $required
     * @param  Builder<Model>  $query
     */
    public function whereProgress(Builder $query, Model $model, array $required, string $locale, string $operator, float $percentage): void;

    /**
     * Order by the value of the field in the first locale of the chain that has one; missing values last.
     *
     * @param  list<string>  $locales
     * @param  Builder<Model>  $query
     */
    public function orderBy(Builder $query, Model $model, string $field, array $locales, string $direction): void;

    /**
     * Eager load whatever the strategy reads translations from.
     *
     * @param  list<string>|null  $locales
     * @param  Builder<Model>  $query
     */
    public function withTranslations(Builder $query, Model $model, ?array $locales): void;
}
