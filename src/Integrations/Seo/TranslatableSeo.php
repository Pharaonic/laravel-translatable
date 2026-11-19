<?php

namespace Pharaonic\Laravel\Translatable\Integrations\Seo;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Pharaonic\Laravel\SEO\Classes\Manager;
use Pharaonic\Laravel\Translatable\Support\Translator;

/**
 * Sets pharaonic/laravel-seo elements from a translated model.
 *
 *     app(TranslatableSeo::class)->apply($post, fn (Post $post, string $locale) => route('posts.show', [$locale, $post->getLocalizedSlug($locale)]));
 *
 * Title and description come from the locale's exact values. hreflang
 * alternates and the canonical link exist only for locales whose required
 * fields all have a value and for which the URL resolver returns a URL;
 * a fallback text never creates a URL.
 */
final class TranslatableSeo
{
    public function __construct(private Translator $translator)
    {
    }

    /**
     * @param  Closure(Model, string): ?string  $url  The page URL of a locale, or null when it has none.
     * @param  array<string, string>  $fields  SEO element => translated field
     */
    public function apply(Model $model, Closure $url, ?string $locale = null, array $fields = ['title' => 'title', 'description' => 'description']): void
    {
        $seo = $this->seo();
        $locale = $this->translator->locale($model, $locale);

        foreach ($fields as $element => $field) {
            $value = $this->translator->exact($model, $field, $locale);

            if (is_scalar($value) && (string) $value !== '') {
                $this->call($seo, $element, 'set', (string) $value);
            }
        }

        foreach ($this->publishedLocales($model) as $candidate) {
            $address = $url($model, $candidate);

            if ($address === null || $address === '') {
                continue;
            }

            $this->call($seo, 'alternates', 'add', $candidate, $address);

            if ($candidate === $locale) {
                $this->call($seo, 'canonical', 'set', $address);
            }
        }
    }

    /**
     * Locales whose required fields all have a value.
     *
     * @return list<string>
     */
    public function publishedLocales(Model $model): array
    {
        return array_values(array_filter(
            $this->translator->translatedLocales($model),
            fn (string $locale): bool => $this->translator->missing($model, $locale) === []
        ));
    }

    private function call(Manager $seo, string $element, string $method, string ...$arguments): void
    {
        $target = $seo->__get($element);

        if (! is_object($target) || ! method_exists($target, $method)) {
            throw new LogicException("The SEO manager has no [{$element}] element with {$method}().");
        }

        $target->{$method}(...$arguments);
    }

    private function seo(): Manager
    {
        $container = Container::getInstance();

        if (! $container->bound('pharaonic-seo')) {
            throw new LogicException('The SEO integration needs pharaonic/laravel-seo.');
        }

        $seo = $container->make('pharaonic-seo');

        if (! $seo instanceof Manager) {
            throw new LogicException('pharaonic-seo must resolve to '.Manager::class.'.');
        }

        return $seo;
    }
}
