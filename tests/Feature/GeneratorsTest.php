<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Translatable\Console\SchemaGenerator;
use Pharaonic\Laravel\Translatable\Support\SchemaInspector;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\JsonPage;
use Pharaonic\Laravel\Translatable\Tests\Fixtures\TablePage;
use Pharaonic\Laravel\Translatable\Tests\TestCase;

final class GeneratorsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'translatable-gen-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->directory.'/app/Models');
        File::ensureDirectoryExists($this->directory.'/database/migrations');
        File::ensureDirectoryExists($this->directory.'/storage');

        $this->app->useDatabasePath($this->directory.'/database');
        $this->app->useStoragePath($this->directory.'/storage');
        $this->app->instance('path', $this->directory.'/app');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    /**
     * @return list<string>
     */
    private function migrations(): array
    {
        $files = glob($this->directory.'/database/migrations/*.php') ?: [];
        sort($files);

        return $files;
    }

    private function runMigration(string $path): void
    {
        $before = get_declared_classes();
        require $path;
        $classes = array_values(array_diff(get_declared_classes(), $before));

        $this->assertCount(1, $classes);
        (new $classes[0])->up();
    }

    public function test_make_schema_json_adds_reviewable_columns_that_the_migration_fills(): void
    {
        (new TablePage(['slug' => 'a']))->fillTranslations(['en' => ['title' => 'Hello', 'rank' => 2]])->save();

        $this->artisan('translatable:make-schema', ['model' => TablePage::class, '--to' => 'json'])->assertExitCode(0);
        $files = $this->migrations();
        $this->assertCount(1, $files);
        $this->assertStringContainsString("\$table->json('title')->nullable();", (string) file_get_contents($files[0]));
        $this->assertFalse(Schema::hasColumn('table_pages', 'title'), 'make-schema never alters the database.');

        $this->runMigration($files[0]);

        $this->assertTrue(Schema::hasColumn('table_pages', 'title'));
        $this->artisan('translatable:migrate', ['model' => TablePage::class, '--from' => 'table', '--to' => 'json', '--apply' => true])->assertExitCode(0);
        $this->artisan('translatable:verify', ['model' => TablePage::class, '--from' => 'table', '--to' => 'json'])->assertExitCode(0);
    }

    public function test_make_schema_columns_creates_one_column_per_locale(): void
    {
        $this->artisan('translatable:make-schema', ['model' => JsonPage::class, '--to' => 'columns', '--locales' => 'en,ar'])->assertExitCode(0);
        $contents = (string) file_get_contents($this->migrations()[0]);

        foreach (['title_en', 'title_ar', 'rank_en', 'rank_ar'] as $column) {
            $this->assertStringContainsString("'{$column}'", $contents);
        }

        $this->assertStringContainsString("\$table->integer('rank_en')", $contents);

        $this->runMigration($this->migrations()[0]);
        $this->assertTrue(Schema::hasColumn('json_pages', 'summary_ar'));
    }

    public function test_make_schema_table_reads_the_type_of_the_model_key(): void
    {
        $connection = (new TablePage)->getConnection();
        $driver = $connection->getDriverName();
        $inspector = $this->app->make(SchemaInspector::class);

        // What bigIncrements and uuid() declare on each engine.
        [$bigKey, $bigColumn, $uuidColumn] = match ($driver) {
            'sqlite' => ['integer', 'unsignedBigInteger', "string('uuid_parent_id')"],
            'pgsql' => ['bigint', 'bigInteger', "uuid('uuid_parent_id')"],
            default => [null, 'unsignedBigInteger', "char('uuid_parent_id', 36)"],
        };

        $type = $inspector->columnType($connection, 'table_pages', 'id');

        if ($bigKey !== null) {
            $this->assertSame($bigKey, $type);
        } else {
            $this->assertMatchesRegularExpression('/^bigint(\(20\))? unsigned$/', (string) $type, 'MySQL 8 drops the display width; MariaDB keeps it.');
        }

        $this->assertNull($inspector->columnType($connection, 'table_pages', 'missing'));

        Schema::create('uuid_parents', function (Blueprint $table) {
            $table->uuid('id')->primary();
        });
        $this->assertSame("\$table->{$uuidColumn}", SchemaGenerator::keyColumnFor((string) $inspector->columnType($connection, 'uuid_parents', 'id'), 'uuid_parent_id', $driver));

        $this->artisan('translatable:make-schema', ['model' => TablePage::class, '--to' => 'table'])->assertExitCode(0);
        $this->assertStringContainsString("\$table->{$bigColumn}('table_page_id');", (string) file_get_contents($this->migrations()[0]));
    }

    public function test_make_schema_table_needs_the_translation_model(): void
    {
        $this->artisan('translatable:make-schema', ['model' => JsonPage::class, '--to' => 'table'])->assertExitCode(1);
        $this->assertSame([], $this->migrations());
    }

    public function test_make_translatable_uses_the_default_strategy_without_a_flag(): void
    {
        $this->artisan('make:translatable', ['name' => 'Blog/Post', '--migrations' => true])->assertExitCode(0);

        $model = (string) file_get_contents($this->directory.'/app/Models/Blog/Post.php');
        $this->assertStringContainsString('namespace App\\Models\\Blog;', $model);
        $this->assertStringNotContainsString('implements', $model, 'Without a flag the model follows the configuration.');
        $this->assertFileExists($this->directory.'/app/Models/Blog/PostTranslation.php');
        $this->assertCount(2, $this->migrations());

        foreach ([$this->directory.'/app/Models/Blog/Post.php', $this->directory.'/app/Models/Blog/PostTranslation.php', ...$this->migrations()] as $file) {
            exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file), $output, $code);
            $this->assertSame(0, $code, $file.' must be valid PHP.');
        }

        $this->artisan('make:translatable', ['name' => 'Blog/Post'])->assertExitCode(1);
    }

    public function test_the_generated_translations_table_has_a_locale_a_unique_relationship_and_a_cascading_foreign_key(): void
    {
        $this->artisan('make:translatable', ['name' => 'Gadget', '--table' => true, '-m' => true])->assertExitCode(0);
        [$parent, $translations] = $this->migrations();

        $source = (string) file_get_contents($translations);
        $this->assertStringContainsString("\$table->string('locale', 35);", $source);
        $this->assertStringContainsString("\$table->unique(['gadget_id', 'locale']);", $source);
        $this->assertStringContainsString("\$table->foreign('gadget_id')->references('id')->on('gadgets')->onDelete('cascade');", $source);

        Schema::enableForeignKeyConstraints();
        $this->runMigration($parent);
        $this->runMigration($translations);

        $db = $this->app->make('db')->connection();
        $db->table('gadgets')->insert(['id' => 1]);
        $db->table('gadget_translations')->insert(['gadget_id' => 1, 'locale' => 'en', 'title' => 'Phone']);
        $db->table('gadget_translations')->insert(['gadget_id' => 1, 'locale' => 'ar', 'title' => 'هاتف']);

        try {
            $db->table('gadget_translations')->insert(['gadget_id' => 1, 'locale' => 'en', 'title' => 'Again']);
            $this->fail('A second row for the same model and locale must be rejected.');
        } catch (QueryException $exception) {
            $this->assertSame(2, $db->table('gadget_translations')->count());
        }

        $db->table('gadgets')->where('id', 1)->delete();
        $this->assertSame(0, $db->table('gadget_translations')->count(), 'Deleting the model deletes its translations.');
    }

    public function test_make_translatable_writes_the_marker_of_the_chosen_strategy(): void
    {
        $this->artisan('make:translatable', ['name' => 'Product', '--columns' => true, '--locales' => 'en,ar', '--migrations' => true])->assertExitCode(0);

        $this->assertStringContainsString('implements ColumnsTranslatable', (string) file_get_contents($this->directory.'/app/Models/Product.php'));
        $this->assertFileDoesNotExist($this->directory.'/app/Models/ProductTranslation.php');
        $this->assertStringContainsString("'title_ar'", (string) file_get_contents($this->migrations()[0]));

        $this->artisan('make:translatable', ['name' => 'Tag', '--json' => true, '-m' => true])->assertExitCode(0);
        $this->assertStringContainsString('implements JsonTranslatable', (string) file_get_contents($this->directory.'/app/Models/Tag.php'));
        $this->assertCount(2, $this->migrations(), '-m is the shortcut of --migrations.');

        $this->artisan('make:translatable', ['name' => 'Page', '--table' => true])->assertExitCode(0);
        $this->assertStringContainsString('implements TableTranslatable', (string) file_get_contents($this->directory.'/app/Models/Page.php'));
        $this->assertFileExists($this->directory.'/app/Models/PageTranslation.php');
    }

    public function test_a_published_stub_wins(): void
    {
        File::ensureDirectoryExists($this->app->basePath('stubs/translatable'));
        File::put($this->app->basePath('stubs/translatable/model.stub'), "<?php\n// custom {{ class }}\n");

        try {
            $this->artisan('make:translatable', ['name' => 'Custom', '--json' => true])->assertExitCode(0);
            $this->assertSame("<?php\n// custom Custom\n", file_get_contents($this->directory.'/app/Models/Custom.php'));
        } finally {
            File::deleteDirectory($this->app->basePath('stubs/translatable'));
        }
    }

    public function test_make_translatable_rejects_unsafe_names(): void
    {
        $this->artisan('make:translatable', ['name' => '../../Evil'])->assertExitCode(1);
        $this->artisan('make:translatable', ['name' => 'Post', '--json' => true, '--columns' => true])->assertExitCode(1);
    }
}
