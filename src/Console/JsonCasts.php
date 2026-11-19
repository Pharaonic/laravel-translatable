<?php

namespace Pharaonic\Laravel\Translatable\Console;

use Illuminate\Database\Eloquent\Model;

/**
 * The model casts of the json strategy: each translated field's JSON column is
 * cast to array, so code that reads the raw column gets the locales, not a string.
 * The strategy itself reads and writes the columns raw, so the casts never
 * change what it stores.
 *
 * @internal
 */
final class JsonCasts
{
    /**
     * The $casts property for the fields, as PHP code.
     *
     * @param  list<string>  $fields
     */
    public static function property(array $fields): string
    {
        $pairs = array_map(fn (string $field): string => "'{$field}' => 'array'", $fields);

        return "    /**\n     * The JSON columns of the translated fields.\n     *\n     * @var array<string, string>\n     */\n    protected \$casts = [".implode(', ', $pairs)."];\n";
    }

    /**
     * Fields of the model without a cast.
     *
     * @param  list<string>  $fields
     * @return list<string>
     */
    public static function missing(Model $model, array $fields): array
    {
        return array_values(array_filter($fields, fn (string $field): bool => ! $model->hasCast($field)));
    }

    /**
     * Fields of the model with a cast.
     *
     * @param  list<string>  $fields
     * @return list<string>
     */
    public static function present(Model $model, array $fields): array
    {
        return array_values(array_filter($fields, fn (string $field): bool => $model->hasCast($field)));
    }

    /**
     * What to change on the model when its strategy moves from one layout to another.
     *
     * @param  list<string>  $fields
     * @return list<string>
     */
    public static function advice(Model $model, string $from, string $to, array $fields): array
    {
        $class = get_class($model);
        $lines = [];

        if ($to === 'json' && ($missing = self::missing($model, $fields)) !== []) {
            $pairs = implode(', ', array_map(fn (string $field): string => "'{$field}' => 'array'", $missing));
            $lines[] = "When you switch [{$class}] to the json strategy, also cast its JSON columns: protected \$casts = [{$pairs}];";
        }

        if ($from === 'json' && $to !== 'json' && ($present = self::present($model, $fields)) !== []) {
            $lines[] = "When you switch [{$class}] away from the json strategy, remove the casts of ".implode(', ', $present).': those columns no longer hold the translations.';
        }

        return $lines;
    }
}
