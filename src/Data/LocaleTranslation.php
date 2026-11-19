<?php

namespace Pharaonic\Laravel\Translatable\Data;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use Pharaonic\Laravel\Translatable\Support\Translator;

/**
 * The translations of one locale of a model that uses the json or columns
 * strategy. It reads and stages values on the model; it is not an Eloquent
 * model and saving happens through the model.
 *
 * @implements ArrayAccess<string, mixed>
 */
final class LocaleTranslation implements Arrayable, ArrayAccess, JsonSerializable
{
    public function __construct(private Translator $translator, private Model $model, private string $locale)
    {
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    public function __get(string $field): mixed
    {
        return $this->translator->exact($this->model, $field, $this->locale);
    }

    public function __set(string $field, mixed $value): void
    {
        $this->translator->set($this->model, $field, $this->locale, $value);
    }

    public function __isset(string $field): bool
    {
        return $this->translator->exact($this->model, $field, $this->locale) !== null;
    }

    public function __unset(string $field): void
    {
        $this->translator->forget($this->model, $field, $this->locale);
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && $this->__isset($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return is_string($offset) ? $this->__get($offset) : null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_string($offset)) {
            $this->__set($offset, $value);
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        if (is_string($offset)) {
            $this->__unset($offset);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(
            $this->translator->forLocale($this->model, $this->locale),
            fn (mixed $value): bool => $value !== null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
