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
use Symfony\Component\Console\Output\BufferedOutput;

$directions = (static function (): array {
    $directions = [];

    foreach (['table', 'json', 'columns'] as $from) {
        foreach (['table', 'json', 'columns'] as $to) {
            if ($from !== $to) {
                $directions["{$from} to {$to}"] = [$from, $to];
            }
        }
    }

    return $directions;
})();

beforeEach(function () {

    $this->storage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'translatable-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->storage);
    $this->app->useStoragePath($this->storage);
});

afterEach(function () {
    File::deleteDirectory($this->storage);
});

/**
 * Three books written through the model with the given strategy.
 *
 * @return list<int|string>
 */
function migrationEngineSeedBooks(string $strategy): array
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
function migrationEngineReadAll(string $strategy): array
{
    Config::set('translatable.strategy', $strategy);

    return Book::query()->orderBy('id')->get()->map(fn (Book $book): array => $book->getTranslations())->all();
}

/**
 * @param  array<string, mixed>  $parameters
 */
function migrationEngineAssertCommand(string $command, array $parameters, int $exitCode, string $output): void
{
    test()->mockConsoleOutput = false;
    app()->offsetUnset(OutputStyle::class);
    $buffer = new BufferedOutput;

    try {
        test()->assertSame($exitCode, Artisan::call($command, $parameters, $buffer));
        test()->assertStringContainsString($output, $buffer->fetch());
    } finally {
        test()->mockConsoleOutput = true;
    }
}

function migrationEngineLatestMigrationId(): string
{
    $files = glob(test()->storage.'/translatable/migrations/*.json') ?: [];
    rsort($files);

    return basename($files[0], '.json');
}

test('every direction copies verifies and cuts over', function (string $from, string $to) {
    migrationEngineSeedBooks($from);
    $expected = migrationEngineReadAll($from);
    $targetBefore = migrationEngineReadAll($to);
    Config::set('translatable.strategy', $from);

    $this->artisan('translatable:migrate', ['model' => Book::class, '--from' => $from, '--to' => $to])->assertExitCode(0);
    $this->assertSame($targetBefore, migrationEngineReadAll($to), 'A dry run changes nothing.');

    $this->artisan('translatable:verify', ['model' => Book::class, '--from' => $from, '--to' => $to])->assertExitCode(1);

    Event::fake([TranslationsMigrated::class]);
    $this->artisan('translatable:migrate', ['model' => Book::class, '--from' => $from, '--to' => $to, '--apply' => true, '--chunk' => 2])->assertExitCode(0);
    Event::assertDispatched(TranslationsMigrated::class, fn (TranslationsMigrated $event): bool => $event->from === $from && $event->to === $to && $event->records === 3);

    $this->artisan('translatable:verify', ['model' => Book::class, '--from' => $from, '--to' => $to])->assertExitCode(0);

    $this->assertSame($expected, migrationEngineReadAll($to), 'After the cutover the model reads the same values.');
    $this->assertSame($expected, migrationEngineReadAll($from), 'The source is never changed.');
})->with($directions);

test('running twice gives the same result', function () {
    migrationEngineSeedBooks('table');
    $expected = migrationEngineReadAll('table');

    foreach ([1, 2] as $run) {
        $this->artisan('translatable:migrate', ['model' => Book::class, '--from' => 'table', '--to' => 'json', '--apply' => true])->assertExitCode(0);
    }

    $this->assertSame($expected, migrationEngineReadAll('json'));
});

test('a resumed migration continues after the checkpoint', function () {
    $keys = migrationEngineSeedBooks('json');
    $journal = new Journal($this->storage.DIRECTORY_SEPARATOR.'translatable'.DIRECTORY_SEPARATOR.'migrations');
    $state = $journal->start(Book::class, 'json', 'columns', ['title', 'summary', 'rank']);
    $state['last_key'] = $keys[1];
    $journal->save($state);

    migrationEngineAssertCommand('translatable:migrate', ['model' => Book::class, '--from' => 'json', '--to' => 'columns', '--apply' => true, '--resume' => true, '--json' => true], 0, '"records": 1');

    $this->assertNull(DB::table('books')->where('id', $keys[0])->value('title_en'), 'Records before the checkpoint are not visited again.');
    $this->assertSame('الأيام', DB::table('books')->where('id', $keys[2])->value('title_ar'));
    $this->assertSame('completed', $journal->load((string) $state['id'])['status'] ?? null);
});

test('rollback restores the previous target values', function () {
    migrationEngineSeedBooks('table');
    DB::table('books')->update(['title' => '{"en":"Old"}']);
    $before = migrationEngineReadAll('json');

    $this->artisan('translatable:migrate', ['model' => Book::class, '--from' => 'table', '--to' => 'json', '--apply' => true, '--json' => true])->assertExitCode(0);
    $id = migrationEngineLatestMigrationId();

    $this->artisan('translatable:rollback', ['model' => Book::class, '--migration-id' => $id])->assertExitCode(0);
    $this->assertNotSame($before, migrationEngineReadAll('json'), 'A rollback dry run changes nothing.');

    $this->artisan('translatable:rollback', ['model' => Book::class, '--migration-id' => $id, '--apply' => true])->assertExitCode(0);
    $this->assertSame($before, migrationEngineReadAll('json'));
});

test('table metadata the target cannot hold blocks the migration', function () {
    migrationEngineSeedBooks('table');
    DB::table('book_translations')->where('locale', 'en')->update(['translator' => 'Sam']);

    migrationEngineAssertCommand('translatable:migrate', ['model' => Book::class, '--from' => 'table', '--to' => 'json', '--apply' => true], 1, '[translator]');
    $this->assertSame(0, DB::table('books')->whereNotNull('title')->count());

    $this->artisan('translatable:migrate', ['model' => Book::class, '--from' => 'table', '--to' => 'json', '--apply' => true, '--ignore-columns' => 'translator'])->assertExitCode(0);
});

test('locales without columns block the migration', function () {
    migrationEngineSeedBooks('table');
    Config::set('translatable.models.'.Book::class.'.locales', ['en', 'ar', 'fr']);
    (new Book)->setTranslation('title', 'fr', 'Le livre')->save();
    Config::set('translatable.models.'.Book::class.'.locales', ['en', 'ar']);

    migrationEngineAssertCommand('translatable:migrate', ['model' => Book::class, '--from' => 'table', '--to' => 'columns', '--apply' => true], 1, 'fr');
});

test('partial field migrations only touch those fields', function () {
    migrationEngineSeedBooks('table');

    $this->artisan('translatable:migrate', ['model' => Book::class, '--from' => 'table', '--to' => 'columns', '--apply' => true, '--fields' => 'title'])->assertExitCode(0);

    $this->assertSame('Dune', DB::table('books')->orderBy('id')->value('title_en'));
    $this->assertNull(DB::table('books')->orderBy('id')->value('rank_en'));
    migrationEngineAssertCommand('translatable:verify', ['model' => Book::class, '--from' => 'table', '--to' => 'columns', '--fields' => 'title'], 1, 'Only some fields were verified');
});

test('inspect reports layouts and a strategy changed before migrating', function () {
    migrationEngineSeedBooks('table');

    $this->artisan('translatable:inspect', ['model' => Book::class])->assertExitCode(0);

    Config::set('translatable.strategy', 'json');
    migrationEngineAssertCommand('translatable:inspect', ['model' => Book::class], 1, 'The active json layout holds no translations but the table layout does');
});

test('commands reject unknown models and layouts', function () {
    $this->artisan('translatable:migrate', ['model' => 'App\\Missing', '--from' => 'table', '--to' => 'json'])->assertExitCode(1);
    $this->artisan('translatable:migrate', ['model' => Book::class, '--from' => 'shared', '--to' => 'json'])->assertExitCode(1);
    $this->artisan('translatable:migrate', ['model' => Book::class, '--from' => 'json', '--to' => 'json'])->assertExitCode(1);
    $this->artisan('translatable:rollback', ['model' => Book::class, '--migration-id' => '../../etc/passwd'])->assertExitCode(1);
});
