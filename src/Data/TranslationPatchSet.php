<?php

namespace Pharaonic\Laravel\Translatable\Data;

/**
 * Translation changes staged on a model until it is saved.
 */
final class TranslationPatchSet
{
    /** @var array<string, array<string, mixed>> */
    private array $values = [];

    /** @var array<string, array<string, true>> */
    private array $removed = [];

    /** @var array<string, true> */
    private array $removedLocales = [];

    public function set(string $field, string $locale, mixed $value): void
    {
        if ($value === null) {
            $this->forget($field, $locale);

            return;
        }

        unset($this->removed[$field][$locale]);
        $this->values[$field][$locale] = $value;
    }

    public function forget(string $field, string $locale): void
    {
        unset($this->values[$field][$locale]);
        $this->removed[$field][$locale] = true;
    }

    /**
     * Drop a staged change, for example one equal to the stored value.
     */
    public function discard(string $field, string $locale): void
    {
        unset($this->values[$field][$locale], $this->removed[$field][$locale]);
    }

    /**
     * @param  list<string>  $fields
     */
    public function forgetLocale(string $locale, array $fields): void
    {
        foreach ($fields as $field) {
            $this->forget($field, $locale);
        }

        $this->removedLocales[$locale] = true;
    }

    public function hasValue(string $field, string $locale): bool
    {
        return array_key_exists($locale, $this->values[$field] ?? []);
    }

    public function value(string $field, string $locale): mixed
    {
        return $this->values[$field][$locale] ?? null;
    }

    public function isRemoved(string $field, string $locale): bool
    {
        return isset($this->removed[$field][$locale]);
    }

    public function touches(string $field, string $locale): bool
    {
        return $this->hasValue($field, $locale) || $this->isRemoved($field, $locale);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function values(): array
    {
        return array_filter($this->values);
    }

    /**
     * @return array<string, list<string>>
     */
    public function removed(): array
    {
        return array_map(fn (array $locales): array => array_map('strval', array_keys($locales)), array_filter($this->removed));
    }

    /**
     * Locales whose every field was forgotten with forgetTranslations().
     *
     * @return list<string>
     */
    public function removedLocales(): array
    {
        return array_map('strval', array_keys($this->removedLocales));
    }

    /**
     * Every locale touched by a value or a removal.
     *
     * @return list<string>
     */
    public function locales(): array
    {
        $locales = [];

        foreach ([$this->values, $this->removed] as $changes) {
            foreach ($changes as $byLocale) {
                foreach (array_keys($byLocale) as $locale) {
                    $locales[(string) $locale] = true;
                }
            }
        }

        foreach ($this->removedLocales as $locale => $true) {
            $locales[(string) $locale] = true;
        }

        return array_map('strval', array_keys($locales));
    }

    /**
     * Every field touched by a value or a removal.
     *
     * @return list<string>
     */
    public function fields(): array
    {
        return array_values(array_unique(array_map('strval', array_merge(
            array_keys($this->values()),
            array_keys(array_filter($this->removed))
        ))));
    }

    public function isEmpty(): bool
    {
        return $this->values() === [] && array_filter($this->removed) === [] && $this->removedLocales === [];
    }
}
