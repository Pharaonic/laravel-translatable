<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\ColumnsPage;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\JsonPage;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\TablePage;
use Pharaonic\Laravel\Translatable\Tests\TestCase;

/**
 * Real parallel writers: forked processes, each with its own connection, on a
 * server database (TRANSLATABLE_DB=mysql|mariadb|pgsql). Skipped on SQLite.
 */
final class ConcurrencyTest extends TestCase
{
    private const WRITERS = 8;

    protected function setUp(): void
    {
        if ((getenv('TRANSLATABLE_DB') ?: 'sqlite') === 'sqlite' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Needs a server database and pcntl.');
        }

        parent::setUp();
    }

    /**
     * Run the callback in parallel child processes; each gets its index.
     *
     * @param  callable(int): void  $callback
     */
    private function parallel(callable $callback): void
    {
        DB::disconnect();
        $children = [];

        for ($index = 0; $index < self::WRITERS; $index++) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                $code = 0;

                try {
                    DB::purge();
                    $callback($index);
                } catch (\Throwable $exception) {
                    fwrite(STDERR, $exception->getMessage().PHP_EOL);
                    $code = 1;
                }

                // Leave at once: PHPUnit's shutdown must not run in a child.
                if ($code === 0) {
                    posix_kill(getmypid(), SIGKILL);
                }

                exit($code);
            }

            $children[] = $pid;
        }

        $failed = 0;

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);

            if (pcntl_wifexited($status) && pcntl_wexitstatus($status) !== 0) {
                $failed++;
            }
        }

        DB::purge();
        $this->assertSame(0, $failed, 'Every writer must succeed.');
    }

    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function strategies(): array
    {
        return [
            'json' => [JsonPage::class],
            'columns' => [ColumnsPage::class],
            'table' => [TablePage::class],
        ];
    }

    /**
     * @dataProvider strategies
     */
    public function test_parallel_writers_of_different_fields_and_locales_lose_nothing(string $class): void
    {
        DB::table('table_page_translations')->delete();
        $page = new $class(['slug' => 'race']);
        $page->setTranslation('title', 'en', 'Start')->save();
        $key = $page->getKey();

        // Each writer loads the model, then changes one field of one locale.
        $this->parallel(function (int $index) use ($class, $key) {
            $model = $class::query()->findOrFail($key);
            $model->getTranslations();
            $locale = $index % 2 === 0 ? 'en' : 'ar';
            $field = ['summary', 'rank'][intdiv($index, 2) % 2];
            $model->setTranslation($field, $locale, $field === 'rank' ? $index : "writer {$index}")->save();
        });

        $stored = $class::query()->findOrFail($key);

        $this->assertSame('Start', $stored->getTranslation('title', 'en'));

        foreach (['en', 'ar'] as $locale) {
            $this->assertNotNull($stored->getTranslation('summary', $locale), "summary {$locale} was lost");
            $this->assertNotNull($stored->getTranslation('rank', $locale), "rank {$locale} was lost");
        }

        if ($class === TablePage::class) {
            $this->assertSame(2, DB::table('table_page_translations')->where('table_page_id', $key)->count(), 'One row per locale, never a duplicate.');
        }
    }

    public function test_parallel_writers_of_one_json_column_keep_every_locale(): void
    {
        $page = new JsonPage(['slug' => 'race']);
        $page->setTranslation('title', 'en', 'Start')->save();
        $key = $page->getKey();

        $this->parallel(function (int $index) use ($key) {
            $model = JsonPage::query()->findOrFail($key);
            $model->setTranslation('title', ['en', 'ar'][$index % 2], "writer {$index}")
                ->setTranslation('summary', ['en', 'ar'][$index % 2], "summary {$index}")
                ->save();
        });

        $stored = JsonPage::query()->findOrFail($key)->getTranslations();

        $this->assertSame(['ar', 'en'], array_keys($stored['title']));
        $this->assertSame(['ar', 'en'], array_keys($stored['summary']));
    }
}
