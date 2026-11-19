<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Feature;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Pharaonic\Laravel\Translatable\Exceptions\InvalidLocaleException;
use Pharaonic\Laravel\Translatable\Exceptions\UnknownTranslatableFieldException;
use Pharaonic\Laravel\Translatable\Exceptions\UnsupportedTranslationQueryException;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\ColumnsPage;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\JsonPage;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\TablePage;

/**
 * Every scope returns the same records under the three strategies.
 *
 *   p1: en "Laravel tips" (rank 10, summary), ar "نصائح"
 *   p2: en "PHP news" (rank 2)
 *   p3: ar "عربي" (rank 5)
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
function queryParitySeedPages(string $class): void
{
    (new $class(['slug' => 'p1']))
        ->fillTranslations([
            'en' => ['title' => 'Laravel tips', 'rank' => 10, 'summary' => 'Short'],
            'ar' => ['title' => 'نصائح'],
        ])->save();

    (new $class(['slug' => 'p2']))
        ->fillTranslations(['en' => ['title' => 'PHP news', 'rank' => 2]])->save();

    (new $class(['slug' => 'p3']))
        ->fillTranslations(['ar' => ['title' => 'عربي', 'rank' => 5]])->save();
}

/**
 * @param  Builder<Model>  $query
 * @return list<string>
 */
function queryParitySlugs(Builder $query, bool $sorted = true): array
{
    $slugs = $query->get()->pluck('slug')->all();

    if ($sorted) {
        sort($slugs);
    }

    return $slugs;
}

test('translated and not translated', function (string $class) {
    queryParitySeedPages($class);

    $this->assertSame(['p1', 'p3'], queryParitySlugs($class::translated('ar')));
    $this->assertSame(['p2'], queryParitySlugs($class::notTranslated('ar')));

    App::setLocale('ar');
    $this->assertSame(['p1', 'p3'], queryParitySlugs($class::translated()), 'Without a locale, the current locale.');
    $this->assertSame(['p2'], queryParitySlugs($class::notTranslated()));

    App::setLocale('en');
    $this->assertSame(['p1', 'p2'], queryParitySlugs($class::translated()));
    $this->assertSame(['p3'], queryParitySlugs($class::notTranslated()), 'translated() and notTranslated() split the records.');
})->with($strategies);

test('exact and like conditions', function (string $class) {
    queryParitySeedPages($class);

    $this->assertSame(['p2'], queryParitySlugs($class::whereTranslation('title', 'PHP news', 'en')));
    $this->assertSame([], queryParitySlugs($class::whereTranslation('title', 'PHP news', 'ar')), 'An exact-locale filter never borrows another locale.');
    $this->assertSame(['p1'], queryParitySlugs($class::whereTranslationLike('title', '%Laravel%', 'en')));
    $this->assertSame(['p1'], queryParitySlugs($class::whereTranslation('title', 'نصائح', 'ar')));
    $this->assertSame(['p2'], queryParitySlugs($class::whereTranslation('title', 'PHP news')), 'Without a locale, any locale matches.');
})->with($strategies);

test('or conditions stay grouped', function (string $class) {
    queryParitySeedPages($class);

    $this->assertSame(['p2', 'p3'], queryParitySlugs($class::query()->where('slug', 'p3')->orWhereTranslation('title', 'PHP news')));
    $this->assertSame([], queryParitySlugs($class::query()->where('slug', 'none')->whereTranslation('title', 'PHP news')));
    $this->assertSame(['p1', 'p2'], queryParitySlugs($class::whereTranslationLike('title', '%Laravel%', 'en')->orWhereTranslationLike('title', '%PHP%', 'en')));
})->with($strategies);

test('numeric comparisons are numeric', function (string $class) {
    queryParitySeedPages($class);

    $this->assertSame(['p1'], queryParitySlugs($class::whereTranslation('rank', 5, 'en', 'whereHas', '>')));
    $this->assertSame(['p2'], queryParitySlugs($class::whereTranslation('rank', 5, 'en', 'whereHas', '<')));
    $this->assertSame(['p1', 'p3'], queryParitySlugs($class::whereTranslation('rank', 5, null, 'whereHas', '>=')));
})->with($strategies);

test('in and presence conditions', function (string $class) {
    queryParitySeedPages($class);

    $this->assertSame(['p2'], queryParitySlugs($class::whereTranslationIn('title', ['PHP news', 'Other'], 'en')));
    $this->assertSame(['p1'], queryParitySlugs($class::whereTranslationNotIn('title', ['PHP news'], 'en')));
    $this->assertSame(['p1'], queryParitySlugs($class::whereHasTranslation('en', 'summary')));
    $this->assertSame(['p2', 'p3'], queryParitySlugs($class::whereMissingTranslation('en', 'summary')));
})->with($strategies);

test('ordering puts missing values last', function (string $class) {
    queryParitySeedPages($class);

    $this->assertSame(['p2', 'p1', 'p3'], queryParitySlugs($class::orderByTranslation('rank', 'asc', 'en'), false));
    $this->assertSame(['p1', 'p2', 'p3'], queryParitySlugs($class::orderByTranslation('rank', 'desc', 'en'), false));
    $this->assertSame(['p2', 'p3', 'p1'], queryParitySlugs($class::orderByTranslation('rank', 'asc', 'ar', true), false), 'With fallback, a missing value sorts by the fallback locale.');
    $this->assertSame(['p1', 'p2'], queryParitySlugs($class::translatedSorting('en', 'title', 'asc'), false));
})->with($strategies);

test('progress conditions', function (string $class) {
    queryParitySeedPages($class);

    $this->assertSame(['p1', 'p3'], queryParitySlugs($class::whereTranslationProgress('ar', '>=', 100)));
    $this->assertSame(['p3'], queryParitySlugs($class::whereTranslationProgress('en', '<', 100)));
})->with($strategies);

test('values are bound and identifiers validated', function (string $class) {
    queryParitySeedPages($class);

    $this->assertSame([], queryParitySlugs($class::whereTranslationLike('title', "%' OR 1=1 --%", 'en')));

    try {
        $class::whereTranslation('title) or (1=1', 'x', 'en')->get();
        $this->fail('An unknown field must be rejected.');
    } catch (UnknownTranslatableFieldException) {
    }

    try {
        $class::whereTranslation('title', 'x', "en') or ('1'='1")->get();
        $this->fail('A malformed locale must be rejected.');
    } catch (InvalidLocaleException) {
    }

    $this->expectException(UnsupportedTranslationQueryException::class);
    $class::whereTranslation('title', 'x', 'en', 'whereHas', '; drop table')->get();
})->with($strategies);

test('scopes keep the preceding constraints', function (string $class) {
    queryParitySeedPages($class);

    $query = $class::query()->where('slug', '!=', 'p1')->translated('ar');

    $this->assertSame(['p3'], queryParitySlugs($query));
})->with($strategies);

test('eager loading avoids a query per record', function (string $class) {
    queryParitySeedPages($class);
    $connection = (new $class)->getConnection();

    $pages = $class::query()->withTranslations()->get();
    $connection->flushQueryLog();
    $connection->enableQueryLog();

    foreach ($pages as $page) {
        $page->getTranslation('title', 'en');
        $page->toArray();
    }

    $this->assertCount(0, $connection->getQueryLog());
    $connection->disableQueryLog();
})->with($strategies);
