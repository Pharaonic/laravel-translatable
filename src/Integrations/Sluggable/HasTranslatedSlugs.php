<?php

namespace Pharaonic\Laravel\Translatable\Integrations\Sluggable;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use LogicException;
use Pharaonic\Laravel\Translatable\Query\TranslationScopes;
use Pharaonic\Slugify\Slugify;

/**
 * Per-locale slugs as a translated field, for every strategy. Use it on a
 * Translatable model and declare target => source translated fields:
 *
 *     public function translatedSlugs(): array
 *     {
 *         return ['slug' => 'title'];
 *     }
 *
 * A locale's slug is made from that locale's exact source value, never from
 * a fallback locale; a locale without a source value gets no slug. Slugs are
 * unique per locale. They are generated when the source of a locale changes
 * and the slug of that locale was not set by hand. The slugifier is
 * pharaonic/php-slugify (installed with pharaonic/laravel-sluggable) when it
 * is available, Laravel's Str::slug() otherwise; override makeTranslatedSlug() to change it.
 *
 * @mixin Model
 */
trait HasTranslatedSlugs
{
    /**
     * @return array<string, string> target field => source field
     */
    abstract public function translatedSlugs(): array;

    /**
     * Called by Translatable before the translations are written.
     *
     * @internal
     */
    public function beforeSavingTranslations(): void
    {
        foreach ($this->translatedSlugs() as $target => $source) {
            foreach ($this->getTranslations($source) as $locale => $value) {
                $locale = (string) $locale;

                if (! $this->isTranslationDirty($source, $locale) && $this->getTranslation($target, $locale) !== null) {
                    continue;
                }

                if ($this->isTranslationDirty($target, $locale)) {
                    continue;
                }

                $slug = is_scalar($value) ? $this->makeTranslatedSlug((string) $value, $locale) : '';

                if ($slug === '') {
                    continue;
                }

                $this->setTranslation($target, $locale, $this->uniqueTranslatedSlug($target, $locale, $slug));
            }
        }
    }

    public function getLocalizedSlug(?string $locale = null, string $target = 'slug'): ?string
    {
        $slug = $this->getTranslation($target, $locale);

        return is_string($slug) ? $slug : null;
    }

    /**
     * @return static|null
     */
    public static function findByTranslatedSlug(string $slug, ?string $locale = null, string $target = 'slug')
    {
        $query = static::query();
        $instance = $query->getModel();

        if (! $instance instanceof Model) {
            return null;
        }

        self::slugScopes()->where($query, $instance, $target, '=', $slug, $locale ?? App::getLocale());
        $model = $query->first();

        return $model instanceof static ? $model : null;
    }

    protected function makeTranslatedSlug(string $value, string $locale): string
    {
        if (class_exists(Slugify::class)) {
            return Slugify::of($value)->toString();
        }

        return Str::slug($value, '-', null);
    }

    private static function slugScopes(): TranslationScopes
    {
        $scopes = Container::getInstance()->make(TranslationScopes::class);

        if (! $scopes instanceof TranslationScopes) {
            throw new LogicException('The container must resolve '.TranslationScopes::class.'.');
        }

        return $scopes;
    }

    protected function uniqueTranslatedSlug(string $target, string $locale, string $slug): string
    {
        $candidate = $slug;

        for ($suffix = 2; ; $suffix++) {
            $query = static::query();
            self::slugScopes()->where($query, $this, $target, '=', $candidate, $locale);

            if ($this->exists) {
                $query->whereKeyNot($this->getKey());
            }

            if (! $query->exists()) {
                return $candidate;
            }

            if ($suffix > 1000) {
                throw new LogicException("No unique \"{$locale}\" slug for [{$slug}] after 1000 attempts.");
            }

            $candidate = $slug.'-'.$suffix;
        }
    }
}
