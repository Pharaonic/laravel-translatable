<?php

namespace Pharaonic\Laravel\Translatable\Support;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use JsonSerializable;
use Pharaonic\Laravel\Translatable\Data\TranslationDefinition;
use Pharaonic\Laravel\Translatable\Exceptions\InvalidTranslatableDefinitionException;
use Pharaonic\Laravel\Translatable\Exceptions\InvalidTranslationValueException;
use stdClass;
use Stringable;
use Throwable;

/**
 * Converts translated values between PHP and storage for the declared field type.
 *
 * Untyped fields pass through unchanged. Typed fields reject values that would
 * change on the way to storage ("12abc" for an int, an array for a string).
 *
 * @internal
 */
final class CastResolver
{
    /**
     * Laravel 7+; referenced by name so the class also loads on Laravel 6.
     */
    public const CASTS_ATTRIBUTES = 'Illuminate\\Contracts\\Database\\Eloquent\\CastsAttributes';

    /**
     * Validate a value and turn it into its storage form. JSON storage keeps
     * arrays and scalars native; column storage encodes arrays as JSON strings.
     */
    public function toStorage(Model $model, TranslationDefinition $definition, mixed $value, bool $json): mixed
    {
        if ($value === null || $definition->type === null) {
            return $value;
        }

        $type = $definition->type;

        switch ($type) {
            case 'string':
                if (is_string($value)) {
                    return $value;
                }

                if ($value instanceof Stringable) {
                    return (string) $value;
                }

                break;

            case 'int':
                if (is_int($value)) {
                    return $value;
                }

                break;

            case 'float':
                if (is_float($value) || is_int($value)) {
                    return (float) $value;
                }

                break;

            case 'bool':
                if (is_bool($value)) {
                    return $value;
                }

                break;

            case 'array':
                if (is_array($value)) {
                    return $json ? $value : $this->encode($value);
                }

                break;

            case 'object':
                if (is_array($value) || $value instanceof stdClass || $value instanceof JsonSerializable) {
                    $object = json_decode($this->encode($value), true);

                    return $json ? $object : $this->encode($object);
                }

                break;

            case 'date':
            case 'datetime':
                $date = $this->date($value);

                if ($date !== null) {
                    return $date->format($type === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s');
                }

                break;

            default:
                $cast = $this->custom($type, 'set');

                return method_exists($cast, 'set') ? $cast->set($model, $definition->field, $value, []) : $value;
        }

        throw InvalidTranslationValueException::forType(get_class($model), $definition->field, $type, $value);
    }

    /**
     * Turn a stored value into the declared PHP type.
     */
    public function fromStorage(Model $model, TranslationDefinition $definition, mixed $value): mixed
    {
        if ($value === null || $definition->type === null) {
            return $value;
        }

        switch ($definition->type) {
            case 'string':
                return is_scalar($value) ? (string) $value : $value;

            case 'int':
                return is_numeric($value) ? (int) $value : $value;

            case 'float':
                return is_numeric($value) ? (float) $value : $value;

            case 'bool':
                return is_bool($value) ? $value : in_array($value, [1, '1', 'true'], true);

            case 'array':
                return is_string($value) ? json_decode($value, true) : $value;

            case 'object':
                return json_decode(is_string($value) ? $value : $this->encode($value), false);

            case 'date':
                return is_string($value) ? Carbon::parse($value)->startOfDay() : $value;

            case 'datetime':
                return is_string($value) ? Carbon::parse($value) : $value;

            default:
                $cast = $this->custom($definition->type, 'get');

                return method_exists($cast, 'get') ? $cast->get($model, $definition->field, $value, []) : $value;
        }
    }

    private function date(mixed $value): ?Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function encode(mixed $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        if ($encoded === false) {
            throw new InvalidTranslationValueException('The translation cannot be encoded as JSON: '.json_last_error_msg());
        }

        return $encoded;
    }

    private function custom(string $type, string $method): object
    {
        $cast = interface_exists(self::CASTS_ATTRIBUTES) && is_subclass_of($type, self::CASTS_ATTRIBUTES) ? new $type : null;

        if (! is_object($cast) || ! method_exists($cast, $method)) {
            throw new InvalidTranslatableDefinitionException("The translation type [{$type}] is not a custom cast.");
        }

        return $cast;
    }
}
