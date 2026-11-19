<?php

namespace Pharaonic\Laravel\Translatable\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Decides which locales a displayed value may fall back to.
 */
interface FallbackPolicy
{
    /**
     * The requested locale first, then its fallbacks in order, without duplicates.
     *
     * @return list<string>
     */
    public function chain(Model $model, string $field, string $locale): array;
}
