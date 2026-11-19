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
use Pharaonic\Laravel\Translatable\Tests\TestCase;

/**
 * Every scope returns the same records under the three strategies.
 *
 *   p1: en "Laravel tips" (rank 10, summary), ar "نصائح"
 *   p2: en "PHP news" (rank 2)
 *   p3: ar "عربي" (rank 5)
 */
final class QueryParityTest extends TestCase
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
    private function seedPages(string $class): void
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
    private function slugs(Builder $query, bool $sorted = true): array
    {
        $slugs = $query->get()->pluck('slug')->all();

        if ($sorted) {
            sort($slugs);
        }

        return $slugs;
    }

    /**
     * @dataProvider strategies
     */
    public function test_translated_and_not_translated(string $class): void
    {
        $this->seedPages($class);

        $this->assertSame(['p1', 'p3'], $this->slugs($class::translated('ar')));
        $this->assertSame(['p2'], $this->slugs($class::notTranslated('ar')));

        App::setLocale('ar');
        $this->assertSame(['p1', 'p3'], $this->slugs($class::translated()), 'Without a locale, the current locale.');
        $this->assertSame(['p2'], $this->slugs($class::notTranslated()));

        App::setLocale('en');
        $this->assertSame(['p1', 'p2'], $this->slugs($class::translated()));
        $this->assertSame(['p3'], $this->slugs($class::notTranslated()), 'translated() and notTranslated() split the records.');
    }

    /**
     * @dataProvider strategies
     */
    public function test_exact_and_like_conditions(string $class): void
    {
        $this->seedPages($class);

        $this->assertSame(['p2'], $this->slugs($class::whereTranslation('title', 'PHP news', 'en')));
        $this->assertSame([], $this->slugs($class::whereTranslation('title', 'PHP news', 'ar')), 'An exact-locale filter never borrows another locale.');
        $this->assertSame(['p1'], $this->slugs($class::whereTranslationLike('title', '%Laravel%', 'en')));
        $this->assertSame(['p1'], $this->slugs($class::whereTranslation('title', 'نصائح', 'ar')));
        $this->assertSame(['p2'], $this->slugs($class::whereTranslation('title', 'PHP news')), 'Without a locale, any locale matches.');
    }

    /**
     * @dataProvider strategies
     */
    public function test_or_conditions_stay_grouped(string $class): void
    {
        $this->seedPages($class);

        $this->assertSame(['p2', 'p3'], $this->slugs($class::query()->where('slug', 'p3')->orWhereTranslation('title', 'PHP news')));
        $this->assertSame([], $this->slugs($class::query()->where('slug', 'none')->whereTranslation('title', 'PHP news')));
        $this->assertSame(['p1', 'p2'], $this->slugs($class::whereTranslationLike('title', '%Laravel%', 'en')->orWhereTranslationLike('title', '%PHP%', 'en')));
    }

    /**
     * @dataProvider strategies
     */
    public function test_numeric_comparisons_are_numeric(string $class): void
    {
        $this->seedPages($class);

        $this->assertSame(['p1'], $this->slugs($class::whereTranslation('rank', 5, 'en', 'whereHas', '>')));
        $this->assertSame(['p2'], $this->slugs($class::whereTranslation('rank', 5, 'en', 'whereHas', '<')));
        $this->assertSame(['p1', 'p3'], $this->slugs($class::whereTranslation('rank', 5, null, 'whereHas', '>=')));
    }

    /**
     * @dataProvider strategies
     */
    public function test_in_and_presence_conditions(string $class): void
    {
        $this->seedPages($class);

        $this->assertSame(['p2'], $this->slugs($class::whereTranslationIn('title', ['PHP news', 'Other'], 'en')));
        $this->assertSame(['p1'], $this->slugs($class::whereTranslationNotIn('title', ['PHP news'], 'en')));
        $this->assertSame(['p1'], $this->slugs($class::whereHasTranslation('en', 'summary')));
        $this->assertSame(['p2', 'p3'], $this->slugs($class::whereMissingTranslation('en', 'summary')));
    }

    /**
     * @dataProvider strategies
     */
    public function test_ordering_puts_missing_values_last(string $class): void
    {
        $this->seedPages($class);

        $this->assertSame(['p2', 'p1', 'p3'], $this->slugs($class::orderByTranslation('rank', 'asc', 'en'), false));
        $this->assertSame(['p1', 'p2', 'p3'], $this->slugs($class::orderByTranslation('rank', 'desc', 'en'), false));
        $this->assertSame(['p2', 'p3', 'p1'], $this->slugs($class::orderByTranslation('rank', 'asc', 'ar', true), false), 'With fallback, a missing value sorts by the fallback locale.');
        $this->assertSame(['p1', 'p2'], $this->slugs($class::translatedSorting('en', 'title', 'asc'), false));
    }

    /**
     * @dataProvider strategies
     */
    public function test_progress_conditions(string $class): void
    {
        $this->seedPages($class);

        $this->assertSame(['p1', 'p3'], $this->slugs($class::whereTranslationProgress('ar', '>=', 100)));
        $this->assertSame(['p3'], $this->slugs($class::whereTranslationProgress('en', '<', 100)));
    }

    /**
     * @dataProvider strategies
     */
    public function test_values_are_bound_and_identifiers_validated(string $class): void
    {
        $this->seedPages($class);

        $this->assertSame([], $this->slugs($class::whereTranslationLike('title', "%' OR 1=1 --%", 'en')));

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
    }

    /**
     * @dataProvider strategies
     */
    public function test_scopes_keep_the_preceding_constraints(string $class): void
    {
        $this->seedPages($class);

        $query = $class::query()->where('slug', '!=', 'p1')->translated('ar');

        $this->assertSame(['p3'], $this->slugs($query));
    }

    /**
     * @dataProvider strategies
     */
    public function test_eager_loading_avoids_a_query_per_record(string $class): void
    {
        $this->seedPages($class);
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
    }
}
