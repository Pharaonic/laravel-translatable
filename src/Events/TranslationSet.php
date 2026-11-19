<?php

namespace Pharaonic\Laravel\Translatable\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Dispatched once per field and locale whose value a save() stored, after the
 * save's transaction commits (after the outer transaction on Laravel 8.19+).
 */
final class TranslationSet
{
    public function __construct(
        public Model $model,
        public string $field,
        public string $locale,
        public mixed $value,
        public mixed $previous,
    ) {
    }
}
