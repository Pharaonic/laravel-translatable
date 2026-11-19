<?php

namespace Pharaonic\Laravel\Translatable\Resolution;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Translatable\Data\TranslationDefinition;
use Pharaonic\Laravel\Translatable\Exceptions\InvalidTranslatableDefinitionException;
use Pharaonic\Laravel\Translatable\Support\CastResolver;
use ReflectionObject;

/**
 * Reads the translated fields from translatable(): array, or from the
 * $translatables list ($translatableAttributes, deprecated, when $translatables
 * is not declared). Definitions say what is translated, never how it is stored.
 *
 * @internal
 */
final class DefinitionResolver
{
    private const OPTIONS = ['type', 'required', 'fallback'];

    private const RESERVED = ['translations', 'locales'];

    /** @var array<string, FieldRegistry> */
    private array $registries = [];

    public function for(Model $model): FieldRegistry
    {
        $class = get_class($model);

        return $this->registries[$class] ??= $this->build($model, $class);
    }

    private function build(Model $model, string $class): FieldRegistry
    {
        $property = $this->declares($model, 'translatables') ? 'translatables' : 'translatableAttributes';
        $legacy = $this->listProperty($model, $property);
        $declared = method_exists($model, 'translatable') ? $model->translatable() : null;

        if ($declared !== null && ! is_array($declared)) {
            throw new InvalidTranslatableDefinitionException("[{$class}]::translatable() must return an array.");
        }

        if ($legacy !== null && ! is_array($legacy)) {
            throw new InvalidTranslatableDefinitionException("[{$class}]::\${$property} must be an array of field names.");
        }

        $fields = $declared !== null ? $this->fromMethod($class, $declared) : $this->fromList($class, $property, $legacy ?? []);

        if ($declared !== null && $legacy !== null && $legacy !== [] && $this->sorted($legacy) !== $this->sorted(array_keys($fields))) {
            throw new InvalidTranslatableDefinitionException("[{$class}] declares different translated fields in translatable() and \${$property}. Keep one of them.");
        }

        foreach ($fields as $field => $definition) {
            $this->assertUsable($model, $class, $field);
        }

        return new FieldRegistry($fields);
    }

    private function declares(Model $model, string $name): bool
    {
        return (new ReflectionObject($model))->hasProperty($name);
    }

    /**
     * A protected list property of the model, when it declares it.
     */
    private function listProperty(Model $model, string $name): mixed
    {
        if (! $this->declares($model, $name)) {
            return null;
        }

        $property = (new ReflectionObject($model))->getProperty($name);
        $property->setAccessible(true);

        return $property->isInitialized($model) ? $property->getValue($model) : null;
    }

    /**
     * @param  array<mixed>  $declared
     * @return array<string, TranslationDefinition>
     */
    private function fromMethod(string $class, array $declared): array
    {
        $fields = [];

        foreach ($declared as $key => $options) {
            if (is_int($key)) {
                if (! is_string($options)) {
                    throw new InvalidTranslatableDefinitionException("[{$class}]::translatable() lists a field name that is not a string.");
                }

                $fields[$options] = new TranslationDefinition($options);

                continue;
            }

            if (! is_array($options)) {
                throw new InvalidTranslatableDefinitionException("The options of [{$key}] in [{$class}]::translatable() must be an array.");
            }

            $fields[$key] = $this->definition($class, $key, $options);
        }

        return $fields;
    }

    /**
     * @param  array<mixed>  $list
     * @return array<string, TranslationDefinition>
     */
    private function fromList(string $class, string $property, array $list): array
    {
        $fields = [];

        foreach ($list as $field) {
            if (! is_string($field)) {
                throw new InvalidTranslatableDefinitionException("[{$class}]::\${$property} must only hold field names.");
            }

            $fields[$field] = new TranslationDefinition($field);
        }

        return $fields;
    }

    /**
     * @param  array<mixed>  $options
     */
    private function definition(string $class, string $field, array $options): TranslationDefinition
    {
        foreach (array_keys($options) as $option) {
            if ($option === 'strategy' || $option === 'storage') {
                throw new InvalidTranslatableDefinitionException("[{$field}] of [{$class}] declares \"{$option}\". A field never chooses its storage: the model's strategy stores every field.");
            }

            if (! in_array($option, self::OPTIONS, true)) {
                throw new InvalidTranslatableDefinitionException(sprintf('[%s] of [%s] has the unknown option "%s". Supported: %s.', $field, $class, $option, implode(', ', self::OPTIONS)));
            }
        }

        $type = $options['type'] ?? null;

        if ($type !== null && (! is_string($type) || ! $this->isType($type))) {
            throw new InvalidTranslatableDefinitionException(sprintf(
                'The type of [%s] in [%s] must be one of %s, or a class implementing %s.',
                $field,
                $class,
                implode(', ', TranslationDefinition::TYPES),
                CastResolver::CASTS_ATTRIBUTES
            ));
        }

        $required = $options['required'] ?? false;
        $fallback = $options['fallback'] ?? null;

        if (! is_bool($required) || ($fallback !== null && ! is_bool($fallback))) {
            throw new InvalidTranslatableDefinitionException("The \"required\" and \"fallback\" options of [{$field}] in [{$class}] must be booleans.");
        }

        return new TranslationDefinition($field, $type, $required, $fallback);
    }

    private function isType(string $type): bool
    {
        if (in_array($type, TranslationDefinition::TYPES, true)) {
            return true;
        }

        return interface_exists(CastResolver::CASTS_ATTRIBUTES) && is_subclass_of($type, CastResolver::CASTS_ATTRIBUTES);
    }

    private function assertUsable(Model $model, string $class, string $field): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field) !== 1) {
            throw new InvalidTranslatableDefinitionException("[{$field}] of [{$class}] is not a valid field name: use letters, digits and underscores.");
        }

        if (in_array($field, self::RESERVED, true) || $field === $model->getKeyName()) {
            throw new InvalidTranslatableDefinitionException("[{$field}] cannot be a translated field of [{$class}]: the name is reserved.");
        }

        if (method_exists($model, 'set'.Str::studly($field).'Attribute')) {
            throw new InvalidTranslatableDefinitionException("[{$class}] has a set mutator for the translated field [{$field}]. Translated values are set per locale; transform them before calling setTranslation().");
        }
    }

    /**
     * @param  array<mixed>  $fields
     * @return list<mixed>
     */
    private function sorted(array $fields): array
    {
        $fields = array_values($fields);
        sort($fields);

        return $fields;
    }
}
