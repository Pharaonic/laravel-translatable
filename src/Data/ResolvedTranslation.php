<?php

namespace Pharaonic\Laravel\Translatable\Data;

/**
 * A displayed value and where it came from.
 */
final class ResolvedTranslation
{
    public function __construct(
        public string $field,
        public mixed $value,
        public string $requestedLocale,
        public ?string $resolvedLocale,
        public bool $isFallback,
        public bool $isMissing,
    ) {}
}
