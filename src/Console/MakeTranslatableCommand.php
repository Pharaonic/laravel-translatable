<?php

namespace Pharaonic\Laravel\Translatable\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Pharaonic\Laravel\Translatable\Console\Concerns\ReadsLocales;
use Pharaonic\Laravel\Translatable\Data\TranslationDefinition;
use Pharaonic\Laravel\Translatable\Resolution\LocaleColumnMapper;
use Pharaonic\Laravel\Translatable\Resolution\StrategyResolver;

/**
 * A translatable model, its translation model for the table strategy and, with
 * --migrations, its migrations, rendered from the stubs. --table, --json or
 * --columns writes that strategy's marker on the model; without one, the model
 * follows config("translatable.strategy"). Existing files are never overwritten.
 */
class MakeTranslatableCommand extends Command
{
    use ReadsLocales;

    private const MARKERS = [
        'table' => 'TableTranslatable',
        'json' => 'JsonTranslatable',
        'columns' => 'ColumnsTranslatable',
    ];

    /**
     * @var string
     */
    protected $signature = 'make:translatable
        {name : The model name, e.g. Post or Blog/Post}
        {--table : Store the translations in a translations table}
        {--json : Store the translations in one JSON column per field}
        {--columns : Store the translations in one column per field and locale}
        {--m|migrations : Also create the migrations}
        {--locales= : Comma-separated locales of the columns strategy (default: app.locale and app.fallback_locale)}';

    /**
     * @var string
     */
    protected $description = 'Create a translatable model, with the default or the given strategy';

    public function handle(Stubs $stubs, SchemaGenerator $schema): int
    {
        $name = $this->argument('name');

        if (! is_string($name) || preg_match('/^[A-Z][A-Za-z0-9]*(\/[A-Z][A-Za-z0-9]*)*$/', $name) !== 1) {
            $this->error('The name must be StudlyCase segments separated by "/", e.g. Post or Blog/Post.');

            return 1;
        }

        $chosen = array_values(array_filter(array_keys(self::MARKERS), fn (string $strategy): bool => $this->option($strategy) === true));

        if (count($chosen) > 1) {
            $this->error('Choose at most one of --table, --json and --columns.');

            return 1;
        }

        $strategy = $chosen[0] ?? Config::get('translatable.strategy') ?? 'table';

        if (! is_string($strategy) || ! in_array($strategy, StrategyResolver::STRATEGIES, true)) {
            $this->error('config("translatable.strategy") must be table, json or columns.');

            return 1;
        }

        try {
            $locales = $strategy === 'columns' ? $this->localesOption() : [];
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        $appPath = $this->laravel->make('path');

        if (! is_string($appPath)) {
            $this->error('The application path is not available.');

            return 1;
        }

        $segments = explode('/', $name);
        $class = (string) array_pop($segments);
        $base = File::isDirectory($appPath.DIRECTORY_SEPARATOR.'Models') ? 'Models' : '';
        $namespace = implode('\\', array_filter(['App', $base, ...$segments]));
        $directory = implode(DIRECTORY_SEPARATOR, array_filter([$appPath, $base, ...$segments]));
        $table = Str::snake(Str::pluralStudly($class));
        $translations = Str::snake($class).'_translations';
        $foreignKey = Str::snake($class).'_id';
        $marker = isset($chosen[0]) ? self::MARKERS[$strategy] : null;

        $ok = $this->put($directory.DIRECTORY_SEPARATOR.$class.'.php', $stubs->render('model', [
            'namespace' => $namespace,
            'class' => $class,
            'imports' => $marker === null ? '' : "use Pharaonic\\Laravel\\Translatable\\Contracts\\{$marker};\n",
            'implements' => $marker === null ? '' : " implements {$marker}",
            'casts' => $strategy === 'json' ? "\n".JsonCasts::property(['title']) : '',
        ]));

        if ($strategy === 'table') {
            $ok = $this->put($directory.DIRECTORY_SEPARATOR.$class.'Translation.php', $stubs->render('model.translation', [
                'namespace' => $namespace,
                'class' => $class,
                'translationsTable' => $translations,
                'foreignKey' => $foreignKey,
            ])) && $ok;
        }

        if ($this->option('migrations') === true) {
            $ok = $this->put($this->migrationPath('create_'.$table.'_table', 0), $stubs->render('migration.create', [
                'migration' => 'Create'.Str::studly($table).'Table',
                'table' => $table,
                'columns' => Stubs::lines($this->parentColumns($strategy, $locales)),
            ])) && $ok;

            if ($strategy === 'table') {
                $ok = $this->put(
                    $this->migrationPath('create_'.$translations.'_table', 1),
                    $schema->translationsTable('Create'.Str::studly($translations).'Table', $translations, $table, $foreignKey, 'id', SchemaGenerator::defaultKeyColumn($foreignKey, false), ['title' => new TranslationDefinition('title', 'string')])
                ) && $ok;
            }
        }

        $this->line($marker === null
            ? "The model follows config(\"translatable.strategy\") ({$strategy})."
            : "The model always uses the {$strategy} strategy ({$marker}).");

        return $ok ? 0 : 1;
    }

    /**
     * @param  list<string>  $locales
     * @return list<string>
     */
    private function parentColumns(string $strategy, array $locales): array
    {
        $columns = ["\$table->bigIncrements('id');"];

        if ($strategy === 'json') {
            $columns[] = "\$table->json('title')->nullable();";
        }

        if ($strategy === 'columns') {
            foreach ($locales as $locale) {
                $columns[] = "\$table->string('title_".LocaleColumnMapper::suffix($locale)."')->nullable();";
            }
        }

        $columns[] = '$table->timestamps();';

        return $columns;
    }

    private function put(string $path, string $contents): bool
    {
        if (File::exists($path)) {
            $this->error("[{$path}] already exists; it was not changed.");

            return false;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
        $this->info("Created [{$path}].");

        return true;
    }

    private function migrationPath(string $name, int $offset): string
    {
        return $this->laravel->databasePath('migrations').DIRECTORY_SEPARATOR.date('Y_m_d_His', time() + $offset).'_'.$name.'.php';
    }
}
