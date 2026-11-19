<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Feature;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\TablePage;
use Pharaonic\Laravel\Translatable\Tests\TestCase;

final class PartialLoadingTest extends TestCase
{
    private function page(): TablePage
    {
        $page = new TablePage(['slug' => 'a']);
        $page->fillTranslations([
            'en' => ['title' => 'Hello', 'summary' => 'Short'],
            'ar' => ['title' => 'مرحبا'],
            'fr' => ['title' => 'Bonjour'],
        ])->save();

        return $page;
    }

    private function queries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_a_locale_outside_a_limited_eager_load_is_still_read(): void
    {
        $this->page();
        $page = TablePage::withTranslations(['ar'])->firstOrFail();

        $this->assertSame(0, $this->queries(fn () => $this->assertSame('مرحبا', $page->getTranslation('title', 'ar'))), 'A loaded locale needs no query.');
        $this->assertSame('Hello', $page->getTranslation('title', 'en'), 'A locale that was not loaded is not missing.');
        $this->assertSame(['ar' => 'مرحبا', 'en' => 'Hello', 'fr' => 'Bonjour'], $page->getTranslations('title'));
        $this->assertSame(0, $this->queries(fn () => $page->getTranslation('title', 'fr')), 'The other locales are loaded once.');
    }

    public function test_a_limited_eager_load_keeps_the_fallback_and_completeness(): void
    {
        $this->page();
        Config::set('app.fallback_locale', 'en');
        App::setLocale('de');

        $page = TablePage::withTranslations(['de'])->firstOrFail();
        $this->assertSame('Hello', $page->title);

        $page = TablePage::withTranslations(['ar'])->firstOrFail();
        $this->assertSame(['ar', 'en', 'fr'], $page->getTranslatedLocales());
        $this->assertTrue($page->hasCompleteTranslation('fr'));
    }

    public function test_changes_staged_on_a_limited_load_survive_loading_the_rest(): void
    {
        $this->page();
        $page = TablePage::withTranslations(['ar'])->firstOrFail();

        $page->setTranslation('title', 'ar', 'أهلا');
        $page->setTranslation('title', 'de', 'Hallo');
        $this->assertSame('Hello', $page->getTranslation('title', 'en'));
        $page->save();

        $fresh = TablePage::query()->findOrFail($page->getKey());
        $this->assertSame(['ar' => 'أهلا', 'de' => 'Hallo', 'en' => 'Hello', 'fr' => 'Bonjour'], $fresh->getTranslations('title'));
        $this->assertSame('Short', $fresh->getTranslation('summary', 'en'), 'Saving after a limited load never drops other locales.');
    }

    public function test_translate_reaches_every_locale_after_a_limited_load(): void
    {
        $this->page();
        $page = TablePage::withTranslations(['ar'])->firstOrFail();

        $this->assertSame('Bonjour', $page->translate('fr')?->getAttribute('title'));
        $this->assertSame(['ar', 'en', 'fr'], $page->translations->keys()->sort()->values()->all());
    }
}
