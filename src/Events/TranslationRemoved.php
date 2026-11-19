<?php

namespace Pharaonic\Laravel\Translatable\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Dispatched once per field and locale whose stored value a save() removed
 * (forgetTranslation(), forgetTranslations() or a null value), after commit.
 */
final class TranslationRemoved
{
    public function __construct(
        public Model $model,
        public string $field,
        public string $locale,
        public mixed $previous,
    ) {
    }
}
