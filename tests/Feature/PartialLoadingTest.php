<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Feature;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\TablePage;

function partialLoadingPage(): TablePage
{
    $page = new TablePage(['slug' => 'a']);
    $page->fillTranslations([
        'en' => ['title' => 'Hello', 'summary' => 'Short'],
        'ar' => ['title' => 'مرحبا'],
        'fr' => ['title' => 'Bonjour'],
    ])->save();

    return $page;
}

function partialLoadingQueries(callable $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

test('a locale outside a limited eager load is still read', function () {
    partialLoadingPage();
    $page = TablePage::withTranslations(['ar'])->firstOrFail();

    $this->assertSame(0, partialLoadingQueries(fn () => $this->assertSame('مرحبا', $page->getTranslation('title', 'ar'))), 'A loaded locale needs no query.');
    $this->assertSame('Hello', $page->getTranslation('title', 'en'), 'A locale that was not loaded is not missing.');
    $this->assertSame(['ar' => 'مرحبا', 'en' => 'Hello', 'fr' => 'Bonjour'], $page->getTranslations('title'));
    $this->assertSame(0, partialLoadingQueries(fn () => $page->getTranslation('title', 'fr')), 'The other locales are loaded once.');
});

test('a limited eager load keeps the fallback and completeness', function () {
    partialLoadingPage();
    Config::set('app.fallback_locale', 'en');
    App::setLocale('de');

    $page = TablePage::withTranslations(['de'])->firstOrFail();
    $this->assertSame('Hello', $page->title);

    $page = TablePage::withTranslations(['ar'])->firstOrFail();
    $this->assertSame(['ar', 'en', 'fr'], $page->getTranslatedLocales());
    $this->assertTrue($page->hasCompleteTranslation('fr'));
});

test('changes staged on a limited load survive loading the rest', function () {
    partialLoadingPage();
    $page = TablePage::withTranslations(['ar'])->firstOrFail();

    $page->setTranslation('title', 'ar', 'أهلا');
    $page->setTranslation('title', 'de', 'Hallo');
    $this->assertSame('Hello', $page->getTranslation('title', 'en'));
    $page->save();

    $fresh = TablePage::query()->findOrFail($page->getKey());
    $this->assertSame(['ar' => 'أهلا', 'de' => 'Hallo', 'en' => 'Hello', 'fr' => 'Bonjour'], $fresh->getTranslations('title'));
    $this->assertSame('Short', $fresh->getTranslation('summary', 'en'), 'Saving after a limited load never drops other locales.');
});

test('translate reaches every locale after a limited load', function () {
    partialLoadingPage();
    $page = TablePage::withTranslations(['ar'])->firstOrFail();

    $this->assertSame('Bonjour', $page->translate('fr')?->getAttribute('title'));
    $this->assertSame(['ar', 'en', 'fr'], $page->translations->keys()->sort()->values()->all());
});
