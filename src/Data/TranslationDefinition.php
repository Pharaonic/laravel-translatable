<?php

namespace Pharaonic\Laravel\Translatable\Data;

/**
 * What one translated field is: its name, value type, and whether it is required.
 * It never says how the field is stored.
 */
final class TranslationDefinition
{
    public const TYPES = ['string', 'int', 'float', 'bool', 'array', 'object', 'date', 'datetime'];

    /**
     * @param  string|null  $type  One of TYPES, a custom cast class, or null for untyped values.
     * @param  bool|null  $fallback  Null follows the model and configuration.
     */
    public function __construct(
        public string $field,
        public ?string $type = null,
        public bool $required = false,
        public ?bool $fallback = null,
    ) {}
}
