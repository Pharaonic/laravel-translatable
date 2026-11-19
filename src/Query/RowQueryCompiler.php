<?php

namespace Pharaonic\Laravel\Translatable\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Contracts\TranslationQueryCompiler;
use Pharaonic\Laravel\Translatable\Data\TranslationDefinition;
use Pharaonic\Laravel\Translatable\Resolution\DefinitionResolver;

/**
 * Shared compilation for strategies that keep translations on the model row
 * (json and columns). Expressions are built from validated identifiers wrapped
 * by the connection's grammar; every value is a binding.
 *
 * @internal
 */
abstract class RowQueryCompiler implements TranslationQueryCompiler
{
    public function __construct(protected DefinitionResolver $definitions) {}

    /**
     * The wrapped SQL expression of one field in one locale.
     *
     * @param  Builder<Model>  $query
     */
    abstract protected function expression(Builder $query, Model $model, string $field, string $locale): string;

    /**
     * The locales a condition covers; an empty list means every locale.
     *
     * @param  list<string>  $locales
     * @return list<string>
     */
    abstract protected function locales(Model $model, array $locales): array;

    /**
     * @param  Builder<Model>  $query
     */
    public function whereAnyPresent(Builder $query, Model $model, array $fields, array $locales, string $boolean = 'and', bool $not = false): void
    {
        $locales = $this->locales($model, $locales);

        $query->where(function (Builder $group) use ($model, $fields, $locales, $not) {
            foreach ($fields as $field) {
                foreach ($locales as $locale) {
                    $expression = $this->expression($group, $model, $field, $locale);
                    $not ? $group->whereRaw($expression.' is null') : $group->orWhereRaw($expression.' is not null');
                }
            }
        }, null, null, $boolean);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function whereValue(Builder $query, Model $model, string $field, string $operator, mixed $value, array $locales, string $boolean = 'and'): void
    {
        if ($locales === []) {
            $this->whereValueInAnyLocale($query, $model, $field, $operator, $value, $boolean);

            return;
        }

        $query->where(function (Builder $group) use ($model, $field, $operator, $value, $locales) {
            foreach ($locales as $locale) {
                $expression = $this->typed($group, $model, $field, $this->expression($group, $model, $field, $locale));

                if ($value === null) {
                    $group->orWhereRaw($expression.($operator === '!=' ? ' is not null' : ' is null'));

                    continue;
                }

                $group->orWhereRaw($expression.' '.$operator.' ?', [$this->binding($group, $model, $field, $value)]);
            }
        }, null, null, $boolean);
    }

    /**
     * The value of the field in any locale compares to the value.
     *
     * @param  Builder<Model>  $query
     */
    protected function whereValueInAnyLocale(Builder $query, Model $model, string $field, string $operator, mixed $value, string $boolean): void
    {
        $locales = $this->locales($model, []);

        if ($locales === []) {
            $query->whereRaw('1 = 0', [], $boolean);

            return;
        }

        $this->whereValue($query, $model, $field, $operator, $value, $locales, $boolean);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function whereValueIn(Builder $query, Model $model, string $field, array $values, string $locale, string $boolean = 'and', bool $not = false): void
    {
        if ($values === []) {
            $query->whereRaw($not ? '1 = 1' : '1 = 0', [], $boolean);

            return;
        }

        $expression = $this->typed($query, $model, $field, $this->expression($query, $model, $field, $locale));
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $bindings = array_map(fn (mixed $value): mixed => $this->binding($query, $model, $field, $value), $values);

        $query->whereRaw($expression.($not ? ' not in (' : ' in (').$placeholders.')', $bindings, $boolean);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function whereProgress(Builder $query, Model $model, array $required, string $locale, string $operator, float $percentage): void
    {
        if ($required === []) {
            $this->constant($query, 100.0, $operator, $percentage);

            return;
        }

        $cases = array_map(
            fn (string $field): string => 'case when '.$this->expression($query, $model, $field, $locale).' is not null then 1 else 0 end',
            $required
        );

        $query->whereRaw('('.implode(' + ', $cases).') * 100.0 / '.count($required).' '.$operator.' '.self::number($percentage));
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function orderBy(Builder $query, Model $model, string $field, array $locales, string $direction): void
    {
        $expressions = array_map(
            fn (string $locale): string => $this->typed($query, $model, $field, $this->expression($query, $model, $field, $locale)),
            $locales
        );
        $expression = count($expressions) === 1 ? $expressions[0] : 'coalesce('.implode(', ', $expressions).')';

        $query->orderByRaw('case when '.$expression.' is null then 1 else 0 end')
            ->orderByRaw($expression.' '.$direction);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function withTranslations(Builder $query, Model $model, ?array $locales): void {}

    /**
     * The expression compared or ordered for the field's declared type.
     *
     * @param  Builder<Model>  $query
     */
    protected function typed(Builder $query, Model $model, string $field, string $expression): string
    {
        return $expression;
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function binding(Builder $query, Model $model, string $field, mixed $value): mixed
    {
        return $value;
    }

    protected function definition(Model $model, string $field): ?TranslationDefinition
    {
        return $this->definitions->for($model)->get($field);
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function driver(Builder $query): string
    {
        $connection = $query->getQuery()->getConnection();

        $driver = method_exists($connection, 'getDriverName') ? $connection->getDriverName() : null;

        return is_string($driver) ? $driver : '';
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function wrap(Builder $query, string $column): string
    {
        return $query->getQuery()->getGrammar()->wrap($column);
    }

    /**
     * A float written as a SQL literal. PDO binds floats as strings, which
     * SQLite compares as text; a PHP float cannot carry SQL.
     */
    public static function number(float $value): string
    {
        return sprintf('%.6F', $value);
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function constant(Builder $query, float $value, string $operator, float $percentage): void
    {
        $matches = match ($operator) {
            '=' => $value == $percentage,
            '!=' => $value != $percentage,
            '<' => $value < $percentage,
            '>' => $value > $percentage,
            '<=' => $value <= $percentage,
            default => $value >= $percentage,
        };

        if (! $matches) {
            $query->whereRaw('1 = 0');
        }
    }
}
