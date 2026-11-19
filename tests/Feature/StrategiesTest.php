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
use Pharaonic\Laravel\Translatable\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The same behavior under the table, json and columns strategies.
 */
final class StrategiesTest extends TestCase
{
    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function strategies(): array
    {
        return [
            'table' => [TablePage::class],
            'json' => [JsonPage::class],
            'columns' => [ColumnsPage::class],
        ];
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function page(string $class): Model
    {
        $page = new $class(['slug' => 'home']);
        $page->setTranslation('title', 'en', 'Hello');
        $page->setTranslation('title', 'ar', 'مرحبا');
        $page->setTranslation('rank', 'en', 3);
        $page->save();

        return $class::query()->findOrFail($page->getKey());
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_it_stores_and_reads_exact_values(string $class): void
    {
        $page = $this->page($class);

        $this->assertSame('Hello', $page->getTranslation('title', 'en'));
        $this->assertSame('مرحبا', $page->getTranslation('title', 'ar'));
        $this->assertSame(3, $page->getTranslation('rank', 'en'));
        $this->assertNull($page->getTranslation('summary', 'en'));
        $this->assertSame(['ar' => 'مرحبا', 'en' => 'Hello'], $page->getTranslations('title'));
        $this->assertSame(['title' => 'مرحبا', 'summary' => null, 'rank' => null], $page->getTranslationsForLocale('ar'));
        $this->assertEqualsCanonicalizing(['en', 'ar'], $page->getTranslatedLocales());
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_it_reads_the_current_locale_on_every_access(string $class): void
    {
        $page = $this->page($class);

        $this->assertSame('Hello', $page->title);

        App::setLocale('ar');

        $this->assertSame('مرحبا', $page->title);
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_exact_reads_never_fall_back_but_displayed_values_do(string $class): void
    {
        $page = $this->page($class);
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
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_the_fallback_can_be_disabled(string $class): void
    {
        Config::set('app.fallback_locale', null);
        $page = $this->page($class);
        App::setLocale('ar');

        $this->assertNull($page->rank);
        $this->assertTrue($page->resolveTranslation('rank')->isMissing);
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_present_values_are_not_missing(string $class): void
    {
        $page = $this->page($class);
        $page->setTranslation('summary', 'en', '');
        $page->setTranslation('rank', 'ar', 0);
        $page->save();
        $page = $page->fresh();

        $this->assertSame('', $page->getTranslation('summary', 'en'));
        $this->assertSame(0, $page->getTranslation('rank', 'ar'));
        $this->assertFalse($page->resolveTranslation('rank', 'ar')->isFallback);
        $this->assertTrue($page->hasTranslation('en', 'summary'));
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_a_plain_save_keeps_every_stored_translation(string $class): void
    {
        $page = $this->page($class);
        $page->getTranslations();
        $page->slug = 'changed';
        $page->save();

        $this->assertSame(['ar' => 'مرحبا', 'en' => 'Hello'], $class::query()->findOrFail($page->getKey())->getTranslations('title'));
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_it_sets_the_current_locale_through_attributes_and_mass_assignment(string $class): void
    {
        App::setLocale('ar');
        $page = $class::create(['slug' => 'a', 'title' => 'عنوان', 'rank' => 1]);

        $fresh = $class::query()->findOrFail($page->getKey());
        $this->assertSame('عنوان', $fresh->getTranslation('title', 'ar'));
        $this->assertNull($fresh->getTranslation('title', 'en'));
        $this->assertSame(1, $fresh->getTranslation('rank', 'ar'));
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_it_fills_translations_by_locale(string $class): void
    {
        $page = $class::create(['slug' => 'a', 'locale' => [
            'en' => ['title' => 'Hello', 'summary' => 'Intro'],
            'ar' => ['title' => 'مرحبا'],
        ]]);

        $this->assertSame(['title' => 'Hello', 'summary' => 'Intro', 'rank' => null], $class::query()->findOrFail($page->getKey())->getTranslationsForLocale('en'));
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_the_payload_only_accepts_translated_fields(string $class): void
    {
        $this->expectException(UnknownTranslatableFieldException::class);

        $class::create(['slug' => 'a', 'locale' => ['en' => ['title' => 'Hello', 'id' => 99]]]);
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_it_rejects_malformed_locales(string $class): void
    {
        $this->expectException(InvalidLocaleException::class);

        (new $class)->setTranslation('title', "en' OR 1=1 --", 'x');
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_typed_fields_reject_lossy_values(string $class): void
    {
        $this->expectException(InvalidTranslationValueException::class);

        (new $class)->setTranslation('rank', 'en', '12abc');
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_it_forgets_one_field_and_whole_locales(string $class): void
    {
        $page = $this->page($class);
        $page->forgetTranslation('title', 'ar')->save();
        $page = $class::query()->findOrFail($page->getKey());

        $this->assertNull($page->getTranslation('title', 'ar'));
        $this->assertSame('Hello', $page->getTranslation('title', 'en'));

        $page->forgetTranslations('en')->save();
        $page = $class::query()->findOrFail($page->getKey());

        $this->assertSame([], $page->getTranslatedLocales());
        $this->assertFalse($page->hasTranslation());
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_a_null_value_removes_the_translation(string $class): void
    {
        $page = $this->page($class);
        $page->setTranslation('title', 'ar', null)->save();

        $this->assertSame(['en' => 'Hello'], $class::query()->findOrFail($page->getKey())->getTranslations('title'));
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_completeness_counts_required_fields(string $class): void
    {
        $page = $this->page($class);

        $this->assertTrue($page->hasCompleteTranslation('en'));
        $this->assertSame([], $page->missingTranslations('en'));
        $this->assertSame(100, $page->translationProgress('en'));

        $page->forgetTranslation('title', 'ar');

        $this->assertFalse($page->hasCompleteTranslation('ar'));
        $this->assertSame(['title'], $page->missingTranslations('ar'));
        $this->assertSame(0, $page->translationProgress('ar'));
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_get_translation_or_fail_throws_a_domain_exception(string $class): void
    {
        $page = $this->page($class);

        $this->assertSame('Hello', $page->getTranslationOrFail('title', 'en'));

        $this->expectException(MissingTranslationException::class);
        $page->getTranslationOrFail('summary', 'en');
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_it_tracks_dirty_and_changed_translations(string $class): void
    {
        $page = $this->page($class);

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
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_refresh_discards_unsaved_translations(string $class): void
    {
        $page = $this->page($class);
        $page->setTranslation('title', 'en', 'Unsaved');

        $page->refresh();

        $this->assertSame('Hello', $page->getTranslation('title', 'en'));
        $this->assertFalse($page->isTranslationDirty());
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_replicate_copies_values_without_the_original_records(string $class): void
    {
        $page = $this->page($class);

        $copy = $page->replicate();
        $copy->setTranslation('title', 'en', 'Copy');
        $copy->save();

        $this->assertSame('Hello', $class::query()->findOrFail($page->getKey())->getTranslation('title', 'en'));
        $this->assertSame(['ar' => 'مرحبا', 'en' => 'Copy'], $class::query()->findOrFail($copy->getKey())->getTranslations('title'));
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_it_serializes_displayed_values_without_storage_columns(string $class): void
    {
        $page = $class::query()->withTranslations()->findOrFail($this->page($class)->getKey());
        App::setLocale('ar');

        $array = $page->toArray();

        $this->assertSame('مرحبا', $array['title']);
        $this->assertSame(3, $array['rank']);
        $this->assertNull($array['summary']);
        $this->assertArrayNotHasKey('title_en', $array);
        $this->assertArrayNotHasKey('title_ar', $array);
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_hidden_translated_fields_stay_hidden(string $class): void
    {
        $page = $class::query()->withTranslations()->findOrFail($this->page($class)->getKey());
        $page->makeHidden('summary');

        $this->assertArrayNotHasKey('summary', $page->toArray());
        $this->assertArrayHasKey('title', $page->toArray());
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_it_dispatches_events_after_saving(string $class): void
    {
        $page = $this->page($class);
        Event::fake([TranslationSet::class, TranslationRemoved::class]);

        $page->setTranslation('title', 'en', 'Hi');
        $page->forgetTranslation('title', 'ar');
        $page->save();

        Event::assertDispatchedTimes(TranslationSet::class, 1);
        Event::assertDispatched(TranslationSet::class, fn (TranslationSet $event): bool => $event->field === 'title' && $event->locale === 'en' && $event->value === 'Hi' && $event->previous === 'Hello');
        Event::assertDispatched(TranslationRemoved::class, fn (TranslationRemoved $event): bool => $event->locale === 'ar' && $event->previous === 'مرحبا');

        $page->save();

        Event::assertDispatchedTimes(TranslationSet::class, 1);
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_save_quietly_still_stores_translations(string $class): void
    {
        $page = $this->page($class);
        $page->setTranslation('title', 'en', 'Quiet');

        $class::withoutEvents(fn () => $page->save());

        $this->assertSame('Quiet', $class::query()->findOrFail($page->getKey())->getTranslation('title', 'en'));
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_a_failed_save_stores_nothing_and_keeps_the_changes_pending(string $class): void
    {
        $page = $this->page($class);
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
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_a_failed_create_leaves_no_rows(string $class): void
    {
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
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_deleting_the_model_removes_its_translations(string $class): void
    {
        $page = $this->page($class);
        $page->delete();

        $this->assertSame(0, $class::query()->count());

        if ($class === TablePage::class) {
            $this->assertSame(0, DB::table('table_page_translations')->count());
        }
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_translate_returns_a_model_for_table_and_a_proxy_otherwise(string $class): void
    {
        $page = $this->page($class);

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
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_translate_or_default_falls_back_to_the_fallback_locale(string $class): void
    {
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
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_translate_returns_null_for_a_locale_without_values_and_translate_or_fail_aborts(string $class): void
    {
        $page = new $class(['slug' => 'e']);
        $page->setTranslation('title', 'en', 'Hello')->save();
        $page = $class::query()->findOrFail($page->getKey());

        $this->assertNull($page->translate('ar'));
        $this->assertSame('Hello', $page->translateOrFail('en')->title);

        App::setLocale('en');
        $this->assertSame('Hello', $page->translate()?->title, 'Without a locale, the current locale.');

        $this->expectException(NotFoundHttpException::class);
        $page->translateOrFail('ar');
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function test_translate_or_new_writes_through_on_save(string $class): void
    {
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
    }
}
