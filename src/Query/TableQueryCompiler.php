<?php

namespace Pharaonic\Laravel\Translatable\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Pharaonic\Laravel\Translatable\Contracts\TranslationQueryCompiler;
use Pharaonic\Laravel\Translatable\Exceptions\UnsupportedTranslationQueryException;
use Pharaonic\Laravel\Translatable\Strategies\Table\TranslationsRelation;
use Pharaonic\Laravel\Translatable\Strategies\TableStrategy;

/**
 * Correlated subqueries on the real translations relation (whereHas), so the
 * translation model's global scopes apply and the parent query keeps its own.
 *
 * @internal
 */
final class TableQueryCompiler implements TranslationQueryCompiler
{
    private const LOCALE = TableStrategy::LOCALE_COLUMN;

    /**
     * @param  Builder<Model>  $query
     */
    public function whereAnyPresent(Builder $query, Model $model, array $fields, array $locales, string $boolean = 'and', bool $not = false): void
    {
        $method = ($boolean === 'or' ? 'orWhere' : 'where').($not ? 'DoesntHave' : 'Has');

        $query->{$method}('translations', function (Builder $translations) use ($fields, $locales) {
            if ($locales !== []) {
                $translations->whereIn($translations->getModel()->qualifyColumn(self::LOCALE), $locales);
            }

            $translations->where(function (Builder $present) use ($fields) {
                foreach ($fields as $field) {
                    $present->orWhereNotNull($present->getModel()->qualifyColumn($field));
                }
            });
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function whereValue(Builder $query, Model $model, string $field, string $operator, mixed $value, array $locales, string $boolean = 'and'): void
    {
        $method = $boolean === 'or' ? 'orWhereHas' : 'whereHas';

        $query->{$method}('translations', function (Builder $translations) use ($field, $operator, $value, $locales) {
            $column = $translations->getModel()->qualifyColumn($field);

            if ($value === null) {
                $operator === '!=' ? $translations->whereNotNull($column) : $translations->whereNull($column);
            } else {
                $translations->where($column, $operator, $value);
            }

            if ($locales !== []) {
                $translations->whereIn($translations->getModel()->qualifyColumn(self::LOCALE), $locales);
            }
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function whereValueIn(Builder $query, Model $model, string $field, array $values, string $locale, string $boolean = 'and', bool $not = false): void
    {
        $method = $boolean === 'or' ? 'orWhereHas' : 'whereHas';

        $query->{$method}('translations', function (Builder $translations) use ($field, $values, $locale, $not) {
            $translations->where($translations->getModel()->qualifyColumn(self::LOCALE), $locale)
                ->whereIn($translations->getModel()->qualifyColumn($field), $values, 'and', $not);
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function whereProgress(Builder $query, Model $model, array $required, string $locale, string $operator, float $percentage): void
    {
        if ($required === []) {
            if (! $this->compare(100.0, $operator, $percentage)) {
                $query->whereRaw('1 = 0');
            }

            return;
        }

        $relation = $this->relation($model);
        $related = $relation->getRelated();
        $grammar = $query->getQuery()->getGrammar();

        $cases = array_map(
            fn (string $field): string => 'case when '.$grammar->wrap($related->qualifyColumn($field)).' is not null then 1 else 0 end',
            $required
        );

        $present = $related->newQuery()->toBase();
        $present->selectRaw(implode(' + ', $cases))
            ->whereColumn($relation->getQualifiedForeignKeyName(), '=', $model->getQualifiedKeyName())
            ->where($related->qualifyColumn(self::LOCALE), '=', $locale)
            ->limit(1);

        $query->whereRaw(
            'coalesce(('.$present->toSql().'), 0) * 100.0 / '.count($required).' '.$operator.' '.RowQueryCompiler::number($percentage),
            $present->getBindings()
        );
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function orderBy(Builder $query, Model $model, string $field, array $locales, string $direction): void
    {
        $relation = $this->relation($model);
        $related = $relation->getRelated();
        $sql = [];
        $bindings = [];

        foreach ($locales as $locale) {
            $value = $related->newQuery()->toBase();
            $value->select($related->qualifyColumn($field))
                ->whereColumn($relation->getQualifiedForeignKeyName(), '=', $model->getQualifiedKeyName())
                ->where($related->qualifyColumn(self::LOCALE), '=', $locale)
                ->limit(1);

            $sql[] = '('.$value->toSql().')';
            $bindings = array_merge($bindings, $value->getBindings());
        }

        $expression = count($sql) === 1 ? $sql[0] : 'coalesce('.implode(', ', $sql).')';

        $query->orderByRaw('case when '.$expression.' is null then 1 else 0 end', $bindings)
            ->orderByRaw($expression.' '.$direction, $bindings);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function withTranslations(Builder $query, Model $model, ?array $locales): void
    {
        $query->with(['translations' => function ($translations) use ($locales) {
            if ($locales === null) {
                return;
            }

            if ($translations instanceof TranslationsRelation) {
                $translations->onlyLocales($locales, self::LOCALE);
            } elseif ($translations instanceof HasMany) {
                $translations->getQuery()->whereIn($translations->getRelated()->qualifyColumn(self::LOCALE), $locales);
            }
        }]);
    }

    /**
     * @return HasMany<Model, Model>
     */
    private function relation(Model $model): HasMany
    {
        $relation = method_exists($model, 'translations') ? $model->translations() : null;

        if (! $relation instanceof HasMany) {
            throw new UnsupportedTranslationQueryException('['.get_class($model).'] must expose its translations through a HasMany translations() relation.');
        }

        return $relation;
    }

    private function compare(float $value, string $operator, float $percentage): bool
    {
        return match ($operator) {
            '=' => $value == $percentage,
            '!=' => $value != $percentage,
            '<' => $value < $percentage,
            '>' => $value > $percentage,
            '<=' => $value <= $percentage,
            default => $value >= $percentage,
        };
    }
}
