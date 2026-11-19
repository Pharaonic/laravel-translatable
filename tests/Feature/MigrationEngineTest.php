<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Feature;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Translatable\Events\TranslationsMigrated;
use Pharaonic\Laravel\Translatable\Migration\Journal;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\Book;
use Pharaonic\Laravel\Translatable\Tests\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class MigrationEngineTest extends TestCase
{
    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'translatable-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->storage);
        $this->app->useStoragePath($this->storage);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);

        parent::tearDown();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function directions(): array
    {
        $directions = [];

        foreach (['table', 'json', 'columns'] as $from) {
            foreach (['table', 'json', 'columns'] as $to) {
                if ($from !== $to) {
                    $directions["{$from} to {$to}"] = [$from, $to];
                }
            }
        }

        return $directions;
    }

    /**
     * Three books written through the model with the given strategy.
     *
     * @return list<int|string>
     */
    private function seedBooks(string $strategy): array
    {
        Config::set('translatable.strategy', $strategy);
        $keys = [];

        foreach ([
            ['en' => ['title' => 'Dune', 'rank' => 1, 'summary' => 'Spice'], 'ar' => ['title' => 'كثيب']],
            ['en' => ['title' => 'Emma', 'rank' => 0]],
            ['ar' => ['title' => 'الأيام', 'summary' => '']],
        ] as $index => $translations) {
            $book = new Book(['isbn' => 'isbn-'.$index]);
            $book->fillTranslations($translations)->save();
            $keys[] = $book->getKey();
        }

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    private function readAll(string $strategy): array
    {
        Config::set('translatable.strategy', $strategy);

        return Book::query()->orderBy('id')->get()->map(fn (Book $book): array => $book->getTranslations())->all();
    }

    /**
     * @dataProvider directions
     */
    public function test_every_direction_copies_verifies_and_cuts_over(string $from, string $to): void
    {
        $this->seedBooks($from);
        $expected = $this->readAll($from);
        $targetBefore = $this->readAll($to);
        Config::set('translatable.strategy', $from);

        $this->artisan('translatable:migrate', ['model' => Book::class, '--from' => $from, '--to' => $to])->assertExitCode(0);
        $this->assertSame($targetBefore, $this->readAll($to), 'A dry run changes nothing.');

        $this->artisan('translatable:verify', ['model' => Book::class, '--from' => $from, '--to' => $to])->assertExitCode(1);

        Event::fake([TranslationsMigrated::class]);
        $this->artisan('translatable:migrate', ['model' => Book::class, '--from' => $from, '--to' => $to, '--apply' => true, '--chunk' => 2])->assertExitCode(0);
        Event::assertDispatched(TranslationsMigrated::class, fn (TranslationsMigrated $event): bool => $event->from === $from && $event->to === $to && $event->records === 3);

        $this->artisan('translatable:verify', ['model' => Book::class, '--from' => $from, '--to' => $to])->assertExitCode(0);

        $this->assertSame($expected, $this->readAll($to), 'After the cutover the model reads the same values.');
        $this->assertSame($expected, $this->readAll($from), 'The source is never changed.');
    }

    public function test_running_twice_gives_the_same_result(): void
    {
        $this->seedBooks('table');
        $expected = $this->readAll('table');

        foreach ([1, 2] as $run) {
            $this->artisan('translatable:migrate', ['model' => Book::class, '--from' => 'table', '--to' => 'json', '--apply' => true])->assertExitCode(0);
        }

        $this->assertSame($expected, $this->readAll('json'));
    }

    public function test_a_resumed_migration_continues_after_the_checkpoint(): void
    {
        $keys = $this->seedBooks('json');
        $journal = new Journal($this->storage.DIRECTORY_SEPARATOR.'translatable'.DIRECTORY_SEPARATOR.'migrations');
        $state = $journal->start(Book::class, 'json', 'columns', ['title', 'summary', 'rank']);
        $state['last_key'] = $keys[1];
        $journal->save($state);

        $this->assertCommand('translatable:migrate', ['model' => Book::class, '--from' => 'json', '--to' => 'columns', '--apply' => true, '--resume' => true, '--json' => true], 0, '"records": 1');

        $this->assertNull(DB::table('books')->where('id', $keys[0])->value('title_en'), 'Records before the checkpoint are not visited again.');
        $this->assertSame('الأيام', DB::table('books')->where('id', $keys[2])->value('title_ar'));
        $this->assertSame('completed', $journal->load((string) $state['id'])['status'] ?? null);
    }

    public function test_rollback_restores_the_previous_target_values(): void
    {
        $this->seedBooks('table');
        DB::table('books')->update(['title' => '{"en":"Old"}']);
        $before = $this->readAll('json');

        $this->artisan('translatable:migrate', ['model' => Book::class, '--from' => 'table', '--to' => 'json', '--apply' => true, '--json' => true])->assertExitCode(0);
        $id = $this->latestMigrationId();

        $this->artisan('translatable:rollback', ['model' => Book::class, '--migration-id' => $id])->assertExitCode(0);
        $this->assertNotSame($before, $this->readAll('json'), 'A rollback dry run changes nothing.');

        $this->artisan('translatable:rollback', ['model' => Book::class, '--migration-id' => $id, '--apply' => true])->assertExitCode(0);
        $this->assertSame($before, $this->readAll('json'));
    }

    public function test_table_metadata_the_target_cannot_hold_blocks_the_migration(): void
    {
        $this->seedBooks('table');
        DB::table('book_translations')->where('locale', 'en')->update(['translator' => 'Sam']);

        $this->assertCommand('translatable:migrate', ['model' => Book::class, '--from' => 'table', '--to' => 'json', '--apply' => true], 1, '[translator]');
        $this->assertSame(0, DB::table('books')->whereNotNull('title')->count());

        $this->artisan('translatable:migrate', ['model' => Book::class, '--from' => 'table', '--to' => 'json', '--apply' => true, '--ignore-columns' => 'translator'])->assertExitCode(0);
    }

    public function test_locales_without_columns_block_the_migration(): void
    {
        $this->seedBooks('table');
        Config::set('translatable.models.'.Book::class.'.locales', ['en', 'ar', 'fr']);
        (new Book)->setTranslation('title', 'fr', 'Le livre')->save();
        Config::set('translatable.models.'.Book::class.'.locales', ['en', 'ar']);

        $this->assertCommand('translatable:migrate', ['model' => Book::class, '--from' => 'table', '--to' => 'columns', '--apply' => true], 1, 'fr');
    }

    public function test_partial_field_migrations_only_touch_those_fields(): void
    {
        $this->seedBooks('table');

        $this->artisan('translatable:migrate', ['model' => Book::class, '--from' => 'table', '--to' => 'columns', '--apply' => true, '--fields' => 'title'])->assertExitCode(0);

        $this->assertSame('Dune', DB::table('books')->orderBy('id')->value('title_en'));
        $this->assertNull(DB::table('books')->orderBy('id')->value('rank_en'));
        $this->assertCommand('translatable:verify', ['model' => Book::class, '--from' => 'table', '--to' => 'columns', '--fields' => 'title'], 1, 'Only some fields were verified');
    }

    public function test_inspect_reports_layouts_and_a_strategy_changed_before_migrating(): void
    {
        $this->seedBooks('table');

        $this->artisan('translatable:inspect', ['model' => Book::class])->assertExitCode(0);

        Config::set('translatable.strategy', 'json');
        $this->assertCommand('translatable:inspect', ['model' => Book::class], 1, 'The active json layout holds no translations but the table layout does');
    }

    public function test_commands_reject_unknown_models_and_layouts(): void
    {
        $this->artisan('translatable:migrate', ['model' => 'App\\Missing', '--from' => 'table', '--to' => 'json'])->assertExitCode(1);
        $this->artisan('translatable:migrate', ['model' => Book::class, '--from' => 'shared', '--to' => 'json'])->assertExitCode(1);
        $this->artisan('translatable:migrate', ['model' => Book::class, '--from' => 'json', '--to' => 'json'])->assertExitCode(1);
        $this->artisan('translatable:rollback', ['model' => Book::class, '--migration-id' => '../../etc/passwd'])->assertExitCode(1);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function assertCommand(string $command, array $parameters, int $exitCode, string $output): void
    {
        $this->mockConsoleOutput = false;
        $this->app->offsetUnset(OutputStyle::class);
        $buffer = new BufferedOutput;

        try {
            $this->assertSame($exitCode, Artisan::call($command, $parameters, $buffer));
            $this->assertStringContainsString($output, $buffer->fetch());
        } finally {
            $this->mockConsoleOutput = true;
        }
    }

    private function latestMigrationId(): string
    {
        $files = glob($this->storage.'/translatable/migrations/*.json') ?: [];
        rsort($files);

        return basename($files[0], '.json');
    }
}
