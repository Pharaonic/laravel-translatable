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

test('two stale models writing different json locales keep both', function () {
    $page = new JsonPage(['slug' => 'a']);
    $page->setTranslation('title', 'en', 'Hello')->save();

    $first = JsonPage::query()->findOrFail($page->getKey());
    $second = JsonPage::query()->findOrFail($page->getKey());

    $first->setTranslation('title', 'en', 'Hi')->save();
    $second->setTranslation('title', 'ar', 'مرحبا')->save();

    $this->assertSame(['ar' => 'مرحبا', 'en' => 'Hi'], JsonPage::query()->findOrFail($page->getKey())->getTranslations('title'));
});

test('two stale models writing different columns keep both', function () {
    $page = new ColumnsPage(['slug' => 'a']);
    $page->setTranslation('title', 'en', 'Hello')->save();

    $first = ColumnsPage::query()->findOrFail($page->getKey());
    $second = ColumnsPage::query()->findOrFail($page->getKey());

    $first->setTranslation('title', 'en', 'Hi')->save();
    $second->setTranslation('title', 'ar', 'مرحبا')->save();

    $this->assertSame(['ar' => 'مرحبا', 'en' => 'Hi'], ColumnsPage::query()->findOrFail($page->getKey())->getTranslations('title'));
});

test('two stale models writing different table fields keep both', function () {
    $page = new TablePage(['slug' => 'a']);
    $page->setTranslation('title', 'en', 'Hello')->save();

    $first = TablePage::query()->with('translations')->findOrFail($page->getKey());
    $second = TablePage::query()->with('translations')->findOrFail($page->getKey());

    $first->setTranslation('title', 'en', 'Hi')->save();
    $second->setTranslation('summary', 'en', 'Intro')->save();

    $this->assertSame(['title' => 'Hi', 'summary' => 'Intro', 'rank' => null], TablePage::query()->findOrFail($page->getKey())->getTranslationsForLocale('en'));
});

test('a concurrently created table row is updated instead of duplicated', function () {
    $page = new TablePage(['slug' => 'a']);
    $page->setTranslation('title', 'en', 'Hello')->save();

    $stale = TablePage::query()->with('translations')->findOrFail($page->getKey());

    DB::table('table_page_translations')->insert(['table_page_id' => $page->getKey(), 'locale' => 'fr', 'title' => 'Concurrent', 'rank' => 7]);

    $stale->setTranslation('title', 'fr', 'Bonjour')->save();

    $rows = DB::table('table_page_translations')->where('locale', 'fr')->get();
    $this->assertCount(1, $rows);
    $this->assertSame('Bonjour', $rows[0]->title);
    $this->assertSame(7, (int) $rows[0]->rank, 'Columns the save did not change stay as stored.');
});

test('a json write on a deleted row fails loudly', function () {
    $page = new JsonPage(['slug' => 'a']);
    $page->setTranslation('title', 'en', 'Hello')->save();
    DB::table('json_pages')->delete();

    $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    $page->setTranslation('title', 'ar', 'مرحبا')->save();
});

test('the payload cannot reach foreign keys or untranslated columns', function () {
    $page = new TablePage(['slug' => 'a']);
    $page->setTranslation('title', 'en', 'Hello')->save();

    $this->expectException(UnknownTranslatableFieldException::class);
    $page->update(['locale' => ['fr' => ['title' => 'x', 'table_page_id' => 999]]]);
});

test('the payload respects mass assignment', function () {
    $article = Article::create(['locale' => ['en' => ['title' => 'Ignored']]]);

    $this->assertNull($article->fresh()->getTranslation('title', 'en'), '"locale" is not the payload key of Article, nor fillable.');

    $article = Article::create(['translations_payload' => ['en' => ['title' => 'Hello']]]);
    $this->assertSame('Hello', $article->fresh()->getTranslation('title', 'en'));
});

test('display values never leak into raw attributes', function () {
    $page = new TablePage(['slug' => 'a']);
    $page->setTranslation('title', 'en', 'Hello')->save();
    $page = TablePage::query()->with('translations')->findOrFail($page->getKey());

    $this->assertSame('Hello', $page->title);
    $this->assertArrayNotHasKey('title', $page->getAttributes());
    $this->assertSame([], $page->getDirty());
});

test('a save without changes touches nothing', function () {
    $page = new JsonPage(['slug' => 'a']);
    $page->setTranslation('title', 'en', 'Hello')->save();
    $page = JsonPage::query()->findOrFail($page->getKey());
    $updatedAt = DB::table('json_pages')->value('updated_at');

    Carbon::setTestNow(Carbon::now()->addDay());
    $page->setTranslation('title', 'en', 'Hello')->save();
    Carbon::setTestNow();

    $this->assertSame($updatedAt, DB::table('json_pages')->value('updated_at'));
});

test('columns use lowercase underscored suffixes', function () {
    $category = Category::create(['name' => 'Books']);
    $category->setTranslation('name', 'pt-BR', 'Livros')->save();

    $this->assertSame('Livros', DB::table('categories')->value('name_pt_br'));
    $this->assertSame('Livros', $category->fresh()->getTranslation('name', 'pt-BR'));
});

test('columns take their locales from the table', function () {
    $category = Category::create(['name' => 'Books']);
    $category->setTranslation('name', 'ar', 'كتب')->save();

    $this->assertSame(['ar' => 'كتب', 'en' => 'Books'], $category->fresh()->getTranslations('name'));
});

test('columns reject a locale without a column when it is set', function () {
    try {
        (new Category)->setTranslation('name', 'de', 'Bücher');
        $this->fail('A locale without a column must be rejected.');
    } catch (TranslationSchemaException $exception) {
        $this->assertStringContainsString('name_de', $exception->getMessage());
    }
});

test('invalid json is reported instead of misread', function () {
    $id = DB::table('json_pages')->insertGetId(['slug' => 'a', 'title' => '"plain text"']);

    $this->expectException(TranslationSchemaException::class);
    JsonPage::query()->findOrFail($id)->getTranslation('title', 'en');
});

test('typed values round trip', function () {
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
});

test('the fallback is laravels fallback locale', function () {
    Config::set('app.fallback_locale', 'ar');
    Config::set('Pharaonic.translatable.default', 'en');

    $post = Post::create(['published' => true]);
    $post->setTranslation('title', 'ar', 'مرحبا')->setTranslation('title', 'en', 'Hello')->save();

    $this->assertSame('مرحبا', $post->fresh()->getTranslation('title', 'fr', true), 'The legacy key is no longer read.');
});

test('a custom fallback policy can be bound', function () {
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
});

test('warm singletons use the container of the current request', function () {
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
});

test('regional and script locale tags keep their spelling', function () {
    $page = new TablePage(['slug' => 'a']);
    $page->setTranslation('title', 'zh-Hant-TW', '你好')->setTranslation('title', 'pt_BR', 'Olá')->save();

    $this->assertSame(['pt_BR' => 'Olá', 'zh-Hant-TW' => '你好'], $page->fresh()->getTranslations('title'));
});
