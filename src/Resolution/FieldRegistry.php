<?php

namespace Pharaonic\Laravel\Translatable\Resolution;

use Pharaonic\Laravel\Translatable\Data\TranslationDefinition;

/**
 * The translated fields of one model class.
 *
 * @internal
 */
final class FieldRegistry
{
    /**
     * @param  array<string, TranslationDefinition>  $fields
     */
    public function __construct(private array $fields) {}

    public function has(string $field): bool
    {
        return isset($this->fields[$field]);
    }

    public function get(string $field): ?TranslationDefinition
    {
        return $this->fields[$field] ?? null;
    }

    /**
     * @return array<string, TranslationDefinition>
     */
    public function all(): array
    {
        return $this->fields;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map('strval', array_keys($this->fields));
    }

    /**
     * @return list<string>
     */
    public function required(): array
    {
        return array_values(array_map(
            fn (TranslationDefinition $definition): string => $definition->field,
            array_filter($this->fields, fn (TranslationDefinition $definition): bool => $definition->required)
        ));
    }
}
