<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Translatable\Migration\Journal;
use Pharaonic\Laravel\Translatable\Migration\Migrator;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\UuidColumnsNote;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\UuidJsonNote;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\UuidNote;

$strategies = (static function (): array {
    return [
        'table' => [UuidNote::class],
        'json' => [UuidJsonNote::class],
        'columns' => [UuidColumnsNote::class],
    ];
})();

/**
 * @param  class-string<UuidNote>  $class
 */
function keysAndSoftDeletesNote(string $class, string $en, ?string $ar = null): UuidNote
{
    $note = new $class;
    $note->setTranslation('title', 'en', $en);

    if ($ar !== null) {
        $note->setTranslation('title', 'ar', $ar);
    }

    $note->save();

    return $note;
}

test('uuid keys read write and query', function (string $class) {
    $note = keysAndSoftDeletesNote($class, 'Hello', 'مرحبا');
    keysAndSoftDeletesNote($class, 'Apple');

    $this->assertIsString($note->getKey());
    $this->assertSame(36, strlen($note->getKey()));

    $fresh = $class::query()->findOrFail($note->getKey());
    $this->assertSame(['ar' => 'مرحبا', 'en' => 'Hello'], $fresh->getTranslations('title'));

    $fresh->setTranslation('title', 'en', 'Hi')->save();
    $this->assertSame('Hi', $class::query()->findOrFail($note->getKey())->getTranslation('title', 'en'));

    $this->assertSame([$note->getKey()], $class::whereTranslation('title', 'مرحبا', 'ar')->pluck('id')->all());
    $this->assertSame(['Apple', 'Hi'], $class::orderByTranslation('title', 'asc', 'en')->get()->map->getTranslation('title', 'en')->all());
})->with($strategies);

test('a soft delete keeps the translations and hides the record from the scopes', function (string $class) {
    $note = keysAndSoftDeletesNote($class, 'Hello', 'مرحبا');
    $note->delete();

    $this->assertSame([], $class::whereTranslation('title', 'Hello', 'en')->pluck('id')->all(), 'Trashed records stay out of the scopes.');
    $this->assertSame([$note->getKey()], $class::withTrashed()->whereTranslation('title', 'Hello', 'en')->pluck('id')->all());

    $trashed = $class::withTrashed()->findOrFail($note->getKey());
    $this->assertSame(['ar' => 'مرحبا', 'en' => 'Hello'], $trashed->getTranslations('title'));

    $trashed->restore();
    $this->assertSame('مرحبا', $class::query()->findOrFail($note->getKey())->getTranslation('title', 'ar'));

    if ($class === UuidNote::class) {
        $this->assertSame(2, DB::table('uuid_note_translations')->where('uuid_note_id', $note->getKey())->count());
    }
})->with($strategies);

test('a force delete removes the translation rows', function () {
    $note = keysAndSoftDeletesNote(UuidNote::class, 'Hello', 'مرحبا');
    $note->delete();
    $this->assertSame(2, DB::table('uuid_note_translations')->count(), 'A soft delete keeps the rows.');

    UuidNote::withTrashed()->findOrFail($note->getKey())->forceDelete();
    $this->assertSame(0, DB::table('uuid_note_translations')->count());
});

test('the migration engine pages through uuid keys and includes trashed records', function () {
    $notes = [];

    foreach (['One', 'Two', 'Three', 'Four', 'Five'] as $title) {
        $notes[] = keysAndSoftDeletesNote(UuidNote::class, $title, $title.' ع');
    }

    $notes[2]->delete();

    $migrator = $this->app->make(Migrator::class);
    $directory = sys_get_temp_dir().'/translatable-journal-'.bin2hex(random_bytes(4));
    $journal = new Journal($directory);
    $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($directory));
    $report = $migrator->migrate(new UuidNote, 'table', 'json', $journal, ['apply' => true, 'chunk' => 2]);

    $this->assertTrue($report->successful(), implode(' ', $report->issues));
    $this->assertSame(5, $report->records, 'Every record, the trashed one included, in chunks of two.');

    foreach ($notes as $note) {
        $copy = UuidJsonNote::withTrashed()->findOrFail($note->getKey());
        $this->assertSame($note->getTranslations('title'), $copy->getTranslations('title'));
    }

    $this->assertTrue($migrator->verify(new UuidNote, 'table', 'json', ['chunk' => 2])->successful());
    $this->assertTrue($migrator->migrate(new UuidNote, 'table', 'columns', $journal, ['apply' => true, 'chunk' => 3])->successful());
    $this->assertSame('Three ع', UuidColumnsNote::withTrashed()->findOrFail($notes[2]->getKey())->getTranslation('title', 'ar'));
});
