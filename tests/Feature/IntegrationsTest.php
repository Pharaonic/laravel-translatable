<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Pharaonic\Laravel\SEO\SEOServiceProvider;
use Pharaonic\Laravel\Translatable\Integrations\Seo\TranslatableSeo;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\SluggedColumnsPost;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\SluggedJsonPost;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\SluggedTablePost;
use Pharaonic\Laravel\Translatable\Tests\TestCase;
use Pharaonic\Laravel\Translatable\TranslatableServiceProvider;

final class IntegrationsTest extends TestCase
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [TranslatableServiceProvider::class, SEOServiceProvider::class];
    }

    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function strategies(): array
    {
        return [
            'table' => [SluggedTablePost::class],
            'json' => [SluggedJsonPost::class],
            'columns' => [SluggedColumnsPost::class],
        ];
    }

    /**
     * @dataProvider strategies
     */
    public function test_each_locale_gets_a_slug_from_its_own_value(string $class): void
    {
        $post = new $class;
        $post->setTranslation('title', 'en', 'Hello World')->save();

        $this->assertSame('hello-world', $post->getLocalizedSlug('en'));
        $this->assertNull($post->getLocalizedSlug('ar'), 'No slug is borrowed from a fallback locale.');

        $post->setTranslation('title', 'ar', 'Second Title')->save();

        $this->assertSame('second-title', $class::query()->findOrFail($post->getKey())->getLocalizedSlug('ar'));
        $this->assertSame('hello-world', $class::query()->findOrFail($post->getKey())->getLocalizedSlug('en'));
    }

    /**
     * @dataProvider strategies
     */
    public function test_slugs_are_unique_per_locale(string $class): void
    {
        (new $class)->setTranslation('title', 'en', 'Same')->setTranslation('title', 'ar', 'Same')->save();
        $second = (new $class)->setTranslation('title', 'en', 'Same');
        $second->save();

        $this->assertSame('same-2', $second->getLocalizedSlug('en'));
        $this->assertSame('same', $class::query()->firstOrFail()->getLocalizedSlug('ar'), 'Another locale may reuse the slug.');
        $this->assertSame($second->getKey(), $class::findByTranslatedSlug('same-2', 'en')?->getKey());
    }

    /**
     * @dataProvider strategies
     */
    public function test_a_slug_set_by_hand_is_kept(string $class): void
    {
        $post = (new $class)->setTranslation('title', 'en', 'Hello')->setTranslation('slug', 'en', 'custom');
        $post->save();

        $this->assertSame('custom', $post->getLocalizedSlug('en'));

        $post->setTranslation('description', 'en', 'Changed description')->save();
        $this->assertSame('custom', $class::query()->findOrFail($post->getKey())->getLocalizedSlug('en'));
    }

    /**
     * @dataProvider strategies
     */
    public function test_seo_uses_exact_values_and_only_real_alternates(string $class): void
    {
        $post = new $class;
        $post->fillTranslations([
            'en' => ['title' => 'Hello', 'description' => 'English page'],
            'ar' => ['description' => 'وصف فقط'],
        ])->save();

        App::setLocale('ar');
        $this->app->make(TranslatableSeo::class)->apply(
            $post,
            fn (Model $model, string $locale): string => "https://example.test/{$locale}/".$model->getKey(),
        );

        $seo = $this->app->make('pharaonic-seo');

        $this->assertNull($seo->title->get(), 'The Arabic page has no title of its own; the English one is not borrowed.');
        $this->assertSame('وصف فقط', $seo->description->get());
        $this->assertSame(['alternates' => ['en' => 'https://example.test/en/'.$post->getKey()]], $seo->alternates->toArray(), 'Only locales with every required field are alternates.');
        $this->assertNull($seo->canonical->get(), 'No canonical URL for an incomplete locale.');
    }

    /**
     * @dataProvider strategies
     */
    public function test_seo_skips_locales_without_a_url(string $class): void
    {
        $post = new $class;
        $post->fillTranslations(['en' => ['title' => 'Hello'], 'ar' => ['title' => 'مرحبا']])->save();

        $this->app->make(TranslatableSeo::class)->apply($post, fn (Model $model, string $locale): ?string => $locale === 'en' ? 'https://example.test/en' : null);
        $seo = $this->app->make('pharaonic-seo');

        $this->assertSame('Hello', $seo->title->get());
        $this->assertSame(['alternates' => ['en' => 'https://example.test/en']], $seo->alternates->toArray());
        $this->assertSame('https://example.test/en', $seo->canonical->get());
    }
}
