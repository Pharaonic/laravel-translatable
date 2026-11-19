<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Feature;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Pharaonic\Laravel\Translatable\Contracts\FallbackPolicy;
use Pharaonic\Laravel\Translatable\Exceptions\TranslationSchemaException;
use Pharaonic\Laravel\Translatable\Exceptions\UnknownTranslatableFieldException;
use Pharaonic\Laravel\Translatable\Support\Translator;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\Article;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\Category;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\ColumnsPage;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\JsonPage;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\Post;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\TablePage;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\TypedItem;
use Pharaonic\Laravel\Translatable\Tests\TestCase;

final class IntegrityTest extends TestCase
{
    public function test_two_stale_models_writing_different_json_locales_keep_both(): void
    {
        $page = new JsonPage(['slug' => 'a']);
        $page->setTranslation('title', 'en', 'Hello')->save();

        $first = JsonPage::query()->findOrFail($page->getKey());
        $second = JsonPage::query()->findOrFail($page->getKey());

        $first->setTranslation('title', 'en', 'Hi')->save();
        $second->setTranslation('title', 'ar', 'مرحبا')->save();

        $this->assertSame(['ar' => 'مرحبا', 'en' => 'Hi'], JsonPage::query()->findOrFail($page->getKey())->getTranslations('title'));
    }

    public function test_two_stale_models_writing_different_columns_keep_both(): void
    {
        $page = new ColumnsPage(['slug' => 'a']);
        $page->setTranslation('title', 'en', 'Hello')->save();

        $first = ColumnsPage::query()->findOrFail($page->getKey());
        $second = ColumnsPage::query()->findOrFail($page->getKey());

        $first->setTranslation('title', 'en', 'Hi')->save();
        $second->setTranslation('title', 'ar', 'مرحبا')->save();

        $this->assertSame(['ar' => 'مرحبا', 'en' => 'Hi'], ColumnsPage::query()->findOrFail($page->getKey())->getTranslations('title'));
    }

    public function test_two_stale_models_writing_different_table_fields_keep_both(): void
    {
        $page = new TablePage(['slug' => 'a']);
        $page->setTranslation('title', 'en', 'Hello')->save();

        $first = TablePage::query()->with('translations')->findOrFail($page->getKey());
        $second = TablePage::query()->with('translations')->findOrFail($page->getKey());

        $first->setTranslation('title', 'en', 'Hi')->save();
        $second->setTranslation('summary', 'en', 'Intro')->save();

        $this->assertSame(['title' => 'Hi', 'summary' => 'Intro', 'rank' => null], TablePage::query()->findOrFail($page->getKey())->getTranslationsForLocale('en'));
    }

    public function test_a_concurrently_created_table_row_is_updated_instead_of_duplicated(): void
    {
        $page = new TablePage(['slug' => 'a']);
        $page->setTranslation('title', 'en', 'Hello')->save();

        $stale = TablePage::query()->with('translations')->findOrFail($page->getKey());

        DB::table('table_page_translations')->insert(['table_page_id' => $page->getKey(), 'locale' => 'fr', 'title' => 'Concurrent', 'rank' => 7]);

        $stale->setTranslation('title', 'fr', 'Bonjour')->save();

        $rows = DB::table('table_page_translations')->where('locale', 'fr')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('Bonjour', $rows[0]->title);
        $this->assertSame(7, (int) $rows[0]->rank, 'Columns the save did not change stay as stored.');
    }

    public function test_a_json_write_on_a_deleted_row_fails_loudly(): void
    {
        $page = new JsonPage(['slug' => 'a']);
        $page->setTranslation('title', 'en', 'Hello')->save();
        DB::table('json_pages')->delete();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $page->setTranslation('title', 'ar', 'مرحبا')->save();
    }

    public function test_the_payload_cannot_reach_foreign_keys_or_untranslated_columns(): void
    {
        $page = new TablePage(['slug' => 'a']);
        $page->setTranslation('title', 'en', 'Hello')->save();

        $this->expectException(UnknownTranslatableFieldException::class);
        $page->update(['locale' => ['fr' => ['title' => 'x', 'table_page_id' => 999]]]);
    }

    public function test_the_payload_respects_mass_assignment(): void
    {
        $article = Article::create(['locale' => ['en' => ['title' => 'Ignored']]]);

        $this->assertNull($article->fresh()->getTranslation('title', 'en'), '"locale" is not the payload key of Article, nor fillable.');

        $article = Article::create(['translations_payload' => ['en' => ['title' => 'Hello']]]);
        $this->assertSame('Hello', $article->fresh()->getTranslation('title', 'en'));
    }

    public function test_display_values_never_leak_into_raw_attributes(): void
    {
        $page = new TablePage(['slug' => 'a']);
        $page->setTranslation('title', 'en', 'Hello')->save();
        $page = TablePage::query()->with('translations')->findOrFail($page->getKey());

        $this->assertSame('Hello', $page->title);
        $this->assertArrayNotHasKey('title', $page->getAttributes());
        $this->assertSame([], $page->getDirty());
    }

    public function test_a_save_without_changes_touches_nothing(): void
    {
        $page = new JsonPage(['slug' => 'a']);
        $page->setTranslation('title', 'en', 'Hello')->save();
        $page = JsonPage::query()->findOrFail($page->getKey());
        $updatedAt = DB::table('json_pages')->value('updated_at');

        Carbon::setTestNow(Carbon::now()->addDay());
        $page->setTranslation('title', 'en', 'Hello')->save();
        Carbon::setTestNow();

        $this->assertSame($updatedAt, DB::table('json_pages')->value('updated_at'));
    }

    public function test_columns_use_lowercase_underscored_suffixes(): void
    {
        $category = Category::create(['name' => 'Books']);
        $category->setTranslation('name', 'pt-BR', 'Livros')->save();

        $this->assertSame('Livros', DB::table('categories')->value('name_pt_br'));
        $this->assertSame('Livros', $category->fresh()->getTranslation('name', 'pt-BR'));
    }

    public function test_columns_take_their_locales_from_the_table(): void
    {
        $category = Category::create(['name' => 'Books']);
        $category->setTranslation('name', 'ar', 'كتب')->save();

        $this->assertSame(['ar' => 'كتب', 'en' => 'Books'], $category->fresh()->getTranslations('name'));
    }

    public function test_columns_reject_a_locale_without_a_column_when_it_is_set(): void
    {
        try {
            (new Category)->setTranslation('name', 'de', 'Bücher');
            $this->fail('A locale without a column must be rejected.');
        } catch (TranslationSchemaException $exception) {
            $this->assertStringContainsString('name_de', $exception->getMessage());
        }
    }

    public function test_invalid_json_is_reported_instead_of_misread(): void
    {
        $id = DB::table('json_pages')->insertGetId(['slug' => 'a', 'title' => '"plain text"']);

        $this->expectException(TranslationSchemaException::class);
        JsonPage::query()->findOrFail($id)->getTranslation('title', 'en');
    }

    public function test_typed_values_round_trip(): void
    {
        $item = new TypedItem;
        $item->fillTranslations(['en' => [
            'flag' => false,
            'tags' => ['a', 'b'],
            'meta' => ['color' => 'red'],
            'price' => 10,
            'released_on' => '2026-11-19 15:30:00',
            'starts_at' => Carbon::create(2026, 11, 19, 15, 30, 0),
        ]])->save();

        $item = TypedItem::query()->findOrFail($item->getKey());

        $this->assertFalse($item->getTranslation('flag', 'en'));
        $this->assertTrue($item->hasTranslation('en', 'flag'), 'false is a present value.');
        $this->assertSame(['a', 'b'], $item->getTranslation('tags', 'en'));
        $this->assertSame('red', $item->getTranslation('meta', 'en')->color);
        $this->assertSame(10.0, $item->getTranslation('price', 'en'));
        $this->assertSame('2026-11-19 00:00:00', $item->getTranslation('released_on', 'en')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-19 15:30:00', $item->getTranslation('starts_at', 'en')->format('Y-m-d H:i:s'));
    }

    public function test_the_fallback_is_laravels_fallback_locale(): void
    {
        Config::set('app.fallback_locale', 'ar');
        Config::set('Pharaonic.translatable.default', 'en');

        $post = Post::create(['published' => true]);
        $post->setTranslation('title', 'ar', 'مرحبا')->setTranslation('title', 'en', 'Hello')->save();

        $this->assertSame('مرحبا', $post->fresh()->getTranslation('title', 'fr', true), 'The legacy key is no longer read.');
    }

    public function test_a_custom_fallback_policy_can_be_bound(): void
    {
        $this->app->instance(FallbackPolicy::class, new class implements FallbackPolicy
        {
            public function chain(Model $model, string $field, string $locale): array
            {
                return $locale === 'ar-EG' ? ['ar-EG', 'ar'] : [$locale];
            }
        });

        $page = new TablePage(['slug' => 'a']);
        $page->setTranslation('title', 'ar', 'مرحبا')->save();

        $this->assertSame('مرحبا', $page->fresh()->getTranslation('title', 'ar-EG', true));
        $this->assertSame('ar', $page->fresh()->resolveTranslation('title', 'ar-EG')->resolvedLocale);
    }

    public function test_warm_singletons_use_the_container_of_the_current_request(): void
    {
        $page = new TablePage(['slug' => 'a']);
        $page->setTranslation('title', 'ar', 'مرحبا')->save();
        $this->app->make(Translator::class);

        // What Octane does per request: run it in a copy of the warm application.
        $sandbox = clone $this->app;
        Container::setInstance($sandbox);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($sandbox);

        try {
            $sandbox->instance(FallbackPolicy::class, new class implements FallbackPolicy
            {
                public function chain(Model $model, string $field, string $locale): array
                {
                    return [$locale, 'ar'];
                }
            });

            $this->assertSame('مرحبا', $page->fresh()->getTranslation('title', 'fr', true), 'The request binds its own fallback policy.');
        } finally {
            Container::setInstance($this->app);
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($this->app);
        }

        $this->assertNull($page->fresh()->getTranslation('title', 'fr', true), 'The request binding does not leak into the next request.');
    }

    public function test_regional_and_script_locale_tags_keep_their_spelling(): void
    {
        $page = new TablePage(['slug' => 'a']);
        $page->setTranslation('title', 'zh-Hant-TW', '你好')->setTranslation('title', 'pt_BR', 'Olá')->save();

        $this->assertSame(['pt_BR' => 'Olá', 'zh-Hant-TW' => '你好'], $page->fresh()->getTranslations('title'));
    }
}
