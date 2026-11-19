<?php

namespace Pharaonic\Laravel\Translatable\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * A model's original attributes as stored, without casts. getRawOriginal()
 * only exists from Laravel 7 and getOriginal() casts from Laravel 7, so this
 * reads Eloquent's $original the same way on every line.
 *
 * @internal
 */
final class RawOriginal
{
    /**
     * @return array<array-key, mixed>
     */
    public static function of(Model $model): array
    {
        $read = Closure::bind(static fn (Model $model): mixed => $model->original, null, Model::class);
        $original = $read($model);

        return is_array($original) ? $original : [];
    }
}
