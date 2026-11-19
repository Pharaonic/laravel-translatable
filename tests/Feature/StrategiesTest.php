<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Pharaonic\Laravel\Translatable\Data\LocaleTranslation;
use Pharaonic\Laravel\Translatable\Events\TranslationRemoved;
use Pharaonic\Laravel\Translatable\Events\TranslationSet;
use Pharaonic\Laravel\Translatable\Exceptions\InvalidLocaleException;
use Pharaonic\Laravel\Translatable\Exceptions\InvalidTranslationValueException;
use Pharaonic\Laravel\Translatable\Exceptions\MissingTranslationException;
use Pharaonic\Laravel\Translatable\Exceptions\UnknownTranslatableFieldException;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\ColumnsPage;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\JsonPage;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\TablePage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The same behavior under the table, json and columns strategies.
 */
$strategies = (static function (): array {
    return [
        'table' => [TablePage::class],
        'json' => [JsonPage::class],
        'columns' => [ColumnsPage::class],
    ];
})();

/**
 * @param  class-string<Model>  $class
 */
function strategiesPage(string $class): Model
{
    $page = new $class(['slug' => 'home']);
    $page->setTranslation('title', 'en', 'Hello');
    $page->setTranslation('title', 'ar', 'مرحبا');
    $page->setTranslation('rank', 'en', 3);
    $page->save();

    return $class::query()->findOrFail($page->getKey());
}

it('stores and reads exact values', function (string $class) {
    $page = strategiesPage($class);

    $this->assertSame('Hello', $page->getTranslation('title', 'en'));
    $this->assertSame('مرحبا', $page->getTranslation('title', 'ar'));
    $this->assertSame(3, $page->getTranslation('rank', 'en'));
    $this->assertNull($page->getTranslation('summary', 'en'));
    $this->assertSame(['ar' => 'مرحبا', 'en' => 'Hello'], $page->getTranslations('title'));
    $this->assertSame(['title' => 'مرحبا', 'summary' => null, 'rank' => null], $page->getTranslationsForLocale('ar'));
    $this->assertEqualsCanonicalizing(['en', 'ar'], $page->getTranslatedLocales());
})->with($strategies);

it('reads the current locale on every access', function (string $class) {
    $page = strategiesPage($class);

    $this->assertSame('Hello', $page->title);

    App::setLocale('ar');

    $this->assertSame('مرحبا', $page->title);
})->with($strategies);

test('exact reads never fall back but displayed values do', function (string $class) {
    $page = strategiesPage($class);
    App::setLocale('ar');

    $this->assertNull($page->getTranslation('rank', 'ar'));
    $this->assertSame(3, $page->getTranslation('rank', 'ar', true));
    $this->assertSame(3, $page->rank);

    $resolved = $page->resolveTranslation('rank');
    $this->assertSame('ar', $resolved->requestedLocale);
    $this->assertSame('en', $resolved->resolvedLocale);
    $this->assertTrue($resolved->isFallback);
    $this->assertFalse($resolved->isMissing);

    $this->assertNull($page->getTranslation('rank', 'ar'), 'A fallback read must not store the fallback value.');
    $this->assertSame([], $page->getDirtyTranslations());
})->with($strategies);

test('the fallback can be disabled', function (string $class) {
    Config::set('app.fallback_locale', null);
    $page = strategiesPage($class);
    App::setLocale('ar');

    $this->assertNull($page->rank);
    $this->assertTrue($page->resolveTranslation('rank')->isMissing);
})->with($strategies);

test('present values are not missing', function (string $class) {
    $page = strategiesPage($class);
    $page->setTranslation('summary', 'en', '');
    $page->setTranslation('rank', 'ar', 0);
    $page->save();
    $page = $page->fresh();

    $this->assertSame('', $page->getTranslation('summary', 'en'));
    $this->assertSame(0, $page->getTranslation('rank', 'ar'));
    $this->assertFalse($page->resolveTranslation('rank', 'ar')->isFallback);
    $this->assertTrue($page->hasTranslation('en', 'summary'));
})->with($strategies);

test('a plain save keeps every stored translation', function (string $class) {
    $page = strategiesPage($class);
    $page->getTranslations();
    $page->slug = 'changed';
    $page->save();

    $this->assertSame(['ar' => 'مرحبا', 'en' => 'Hello'], $class::query()->findOrFail($page->getKey())->getTranslations('title'));
})->with($strategies);

it('sets the current locale through attributes and mass assignment', function (string $class) {
    App::setLocale('ar');
    $page = $class::create(['slug' => 'a', 'title' => 'عنوان', 'rank' => 1]);

    $fresh = $class::query()->findOrFail($page->getKey());
    $this->assertSame('عنوان', $fresh->getTranslation('title', 'ar'));
    $this->assertNull($fresh->getTranslation('title', 'en'));
    $this->assertSame(1, $fresh->getTranslation('rank', 'ar'));
})->with($strategies);

it('fills translations by locale', function (string $class) {
    $page = $class::create(['slug' => 'a', 'locale' => [
        'en' => ['title' => 'Hello', 'summary' => 'Intro'],
        'ar' => ['title' => 'مرحبا'],
    ]]);

    $this->assertSame(['title' => 'Hello', 'summary' => 'Intro', 'rank' => null], $class::query()->findOrFail($page->getKey())->getTranslationsForLocale('en'));
})->with($strategies);

test('the payload only accepts translated fields', function (string $class) {
    $this->expectException(UnknownTranslatableFieldException::class);

    $class::create(['slug' => 'a', 'locale' => ['en' => ['title' => 'Hello', 'id' => 99]]]);
})->with($strategies);

it('rejects malformed locales', function (string $class) {
    $this->expectException(InvalidLocaleException::class);

    (new $class)->setTranslation('title', "en' OR 1=1 --", 'x');
})->with($strategies);

test('typed fields reject lossy values', function (string $class) {
    $this->expectException(InvalidTranslationValueException::class);

    (new $class)->setTranslation('rank', 'en', '12abc');
})->with($strategies);

it('forgets one field and whole locales', function (string $class) {
    $page = strategiesPage($class);
    $page->forgetTranslation('title', 'ar')->save();
    $page = $class::query()->findOrFail($page->getKey());

    $this->assertNull($page->getTranslation('title', 'ar'));
    $this->assertSame('Hello', $page->getTranslation('title', 'en'));

    $page->forgetTranslations('en')->save();
    $page = $class::query()->findOrFail($page->getKey());

    $this->assertSame([], $page->getTranslatedLocales());
    $this->assertFalse($page->hasTranslation());
})->with($strategies);

test('a null value removes the translation', function (string $class) {
    $page = strategiesPage($class);
    $page->setTranslation('title', 'ar', null)->save();

    $this->assertSame(['en' => 'Hello'], $class::query()->findOrFail($page->getKey())->getTranslations('title'));
})->with($strategies);

test('completeness counts required fields', function (string $class) {
    $page = strategiesPage($class);

    $this->assertTrue($page->hasCompleteTranslation('en'));
    $this->assertSame([], $page->missingTranslations('en'));
    $this->assertSame(100, $page->translationProgress('en'));

    $page->forgetTranslation('title', 'ar');

    $this->assertFalse($page->hasCompleteTranslation('ar'));
    $this->assertSame(['title'], $page->missingTranslations('ar'));
    $this->assertSame(0, $page->translationProgress('ar'));
})->with($strategies);

test('get translation or fail throws a domain exception', function (string $class) {
    $page = strategiesPage($class);

    $this->assertSame('Hello', $page->getTranslationOrFail('title', 'en'));

    $this->expectException(MissingTranslationException::class);
    $page->getTranslationOrFail('summary', 'en');
})->with($strategies);

it('tracks dirty and changed translations', function (string $class) {
    $page = strategiesPage($class);

    $this->assertFalse($page->isTranslationDirty());

    $page->setTranslation('title', 'en', 'Hello');
    $this->assertFalse($page->isTranslationDirty(), 'Setting the stored value is not a change.');

    $page->setTranslation('title', 'en', 'Hi');
    $this->assertTrue($page->isTranslationDirty('title', 'en'));
    $this->assertFalse($page->isTranslationDirty('title', 'ar'));
    $this->assertSame(['title' => ['en' => 'Hi']], $page->getDirtyTranslations());

    $page->save();

    $this->assertFalse($page->isTranslationDirty());
    $this->assertTrue($page->wasTranslationChanged('title', 'en'));
})->with($strategies);

test('refresh discards unsaved translations', function (string $class) {
    $page = strategiesPage($class);
    $page->setTranslation('title', 'en', 'Unsaved');

    $page->refresh();

    $this->assertSame('Hello', $page->getTranslation('title', 'en'));
    $this->assertFalse($page->isTranslationDirty());
})->with($strategies);

test('replicate copies values without the original records', function (string $class) {
    $page = strategiesPage($class);

    $copy = $page->replicate();
    $copy->setTranslation('title', 'en', 'Copy');
    $copy->save();

    $this->assertSame('Hello', $class::query()->findOrFail($page->getKey())->getTranslation('title', 'en'));
    $this->assertSame(['ar' => 'مرحبا', 'en' => 'Copy'], $class::query()->findOrFail($copy->getKey())->getTranslations('title'));
})->with($strategies);

it('serializes displayed values without storage columns', function (string $class) {
    $page = $class::query()->withTranslations()->findOrFail(strategiesPage($class)->getKey());
    App::setLocale('ar');

    $array = $page->toArray();

    $this->assertSame('مرحبا', $array['title']);
    $this->assertSame(3, $array['rank']);
    $this->assertNull($array['summary']);
    $this->assertArrayNotHasKey('title_en', $array);
    $this->assertArrayNotHasKey('title_ar', $array);
})->with($strategies);

test('hidden translated fields stay hidden', function (string $class) {
    $page = $class::query()->withTranslations()->findOrFail(strategiesPage($class)->getKey());
    $page->makeHidden('summary');

    $this->assertArrayNotHasKey('summary', $page->toArray());
    $this->assertArrayHasKey('title', $page->toArray());
})->with($strategies);

it('dispatches events after saving', function (string $class) {
    $page = strategiesPage($class);
    Event::fake([TranslationSet::class, TranslationRemoved::class]);

    $page->setTranslation('title', 'en', 'Hi');
    $page->forgetTranslation('title', 'ar');
    $page->save();

    Event::assertDispatchedTimes(TranslationSet::class, 1);
    Event::assertDispatched(TranslationSet::class, fn (TranslationSet $event): bool => $event->field === 'title' && $event->locale === 'en' && $event->value === 'Hi' && $event->previous === 'Hello');
    Event::assertDispatched(TranslationRemoved::class, fn (TranslationRemoved $event): bool => $event->locale === 'ar' && $event->previous === 'مرحبا');

    $page->save();

    Event::assertDispatchedTimes(TranslationSet::class, 1);
})->with($strategies);

test('save quietly still stores translations', function (string $class) {
    $page = strategiesPage($class);
    $page->setTranslation('title', 'en', 'Quiet');

    $class::withoutEvents(fn () => $page->save());

    $this->assertSame('Quiet', $class::query()->findOrFail($page->getKey())->getTranslation('title', 'en'));
})->with($strategies);

test('a failed save stores nothing and keeps the changes pending', function (string $class) {
    $page = strategiesPage($class);
    $page->setTranslation('title', 'en', 'Lost?');

    $class::saved(function () {
        throw new \RuntimeException('observer failed');
    });

    try {
        $page->save();
        $this->fail('The observer exception should propagate.');
    } catch (\RuntimeException $exception) {
        $this->assertSame('observer failed', $exception->getMessage());
    }

    $class::flushEventListeners();
    $class::boot();

    $this->assertSame('Hello', $class::query()->findOrFail($page->getKey())->getTranslation('title', 'en'));
    $this->assertTrue($page->isTranslationDirty('title', 'en'));

    $page->save();

    $this->assertSame('Lost?', $class::query()->findOrFail($page->getKey())->getTranslation('title', 'en'));
})->with($strategies);

test('a failed create leaves no rows', function (string $class) {
    $page = new $class(['slug' => 'x']);
    $page->setTranslation('title', 'en', 'Hello');

    $class::saved(function () {
        throw new \RuntimeException('observer failed');
    });

    try {
        $page->save();
    } catch (\RuntimeException) {
    }

    $class::flushEventListeners();

    $this->assertSame(0, $class::query()->count());
    $this->assertFalse($page->exists);
})->with($strategies);

test('deleting the model removes its translations', function (string $class) {
    $page = strategiesPage($class);
    $page->delete();

    $this->assertSame(0, $class::query()->count());

    if ($class === TablePage::class) {
        $this->assertSame(0, DB::table('table_page_translations')->count());
    }
})->with($strategies);

test('translate returns a model for table and a proxy otherwise', function (string $class) {
    $page = strategiesPage($class);

    $translation = $page->translate('ar');

    if ($class === TablePage::class) {
        $this->assertInstanceOf(Model::class, $translation);
    } else {
        $this->assertInstanceOf(LocaleTranslation::class, $translation);
        $this->assertNotInstanceOf(Model::class, $translation);
    }

    $this->assertSame('مرحبا', $translation->title);

    $page->forgetTranslations('ar')->save();
    $this->assertNull($page->translate('ar'));

    $page->translateOrNew('ar')->title = 'أهلا';
    $page->save();

    $this->assertSame('أهلا', $class::query()->findOrFail($page->getKey())->getTranslation('title', 'ar'));
})->with($strategies);

test('translate or default falls back to the fallback locale', function (string $class) {
    Config::set('app.fallback_locale', 'en');
    $page = new $class(['slug' => 'd']);
    $page->setTranslation('title', 'en', 'Hello')->setTranslation('title', 'ar', 'مرحبا')->save();
    $page = $class::query()->findOrFail($page->getKey());

    $this->assertSame('مرحبا', $page->translateOrDefault('ar')?->title, 'The locale itself when it has a translation.');
    $this->assertSame('Hello', $page->translateOrDefault('fr')?->title, 'Else the fallback locale.');

    App::setLocale('ar');
    $this->assertSame('مرحبا', $page->translateOrDefault()?->title, 'Without a locale, the current locale.');

    Config::set('app.fallback_locale', 'de');
    $this->assertNull($page->translateOrDefault('fr'), 'Null when neither has a translation.');
})->with($strategies);

test('translate returns null for a locale without values and translate or fail aborts', function (string $class) {
    $page = new $class(['slug' => 'e']);
    $page->setTranslation('title', 'en', 'Hello')->save();
    $page = $class::query()->findOrFail($page->getKey());

    $this->assertNull($page->translate('ar'));
    $this->assertSame('Hello', $page->translateOrFail('en')->title);

    App::setLocale('en');
    $this->assertSame('Hello', $page->translate()?->title, 'Without a locale, the current locale.');

    $this->expectException(NotFoundHttpException::class);
    $page->translateOrFail('ar');
})->with($strategies);

test('translate or new writes through on save', function (string $class) {
    $page = new $class(['slug' => 'f']);
    $page->translateOrNew('en')->title = 'Hello';
    $page->save();

    $translation = $page->translateOrNew('ar');
    $this->assertNull($translation->title, 'A new translation starts empty.');
    $translation->title = 'مرحبا';
    $translation->summary = 'ملخص';
    $page->save();

    $fresh = $class::query()->findOrFail($page->getKey());
    $this->assertSame(['ar' => 'مرحبا', 'en' => 'Hello'], $fresh->getTranslations('title'));
    $this->assertSame('ملخص', $fresh->translate('ar')?->summary);
})->with($strategies);
