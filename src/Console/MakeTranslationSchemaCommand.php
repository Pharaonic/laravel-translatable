<?php

namespace Pharaonic\Laravel\Translatable\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Pharaonic\Laravel\Translatable\Console\Concerns\InteractsWithTranslatableModels;
use Pharaonic\Laravel\Translatable\Console\Concerns\ReadsLocales;
use Pharaonic\Laravel\Translatable\Exceptions\TranslatableException;

class MakeTranslationSchemaCommand extends Command
{
    use InteractsWithTranslatableModels;
    use ReadsLocales;

    /**
     * @var string
     */
    protected $signature = 'translatable:make-schema
        {model : The translatable model class}
        {--to= : The layout to create: table, json or columns}
        {--locales= : Comma-separated locales of the columns layout (default: app.locale and app.fallback_locale)}';

    /**
     * @var string
     */
    protected $description = 'Write a migration that adds the storage of a translation strategy, for review';

    public function handle(SchemaGenerator $generator): int
    {
        try {
            $model = $this->translatableModel();
            $strategy = $this->layout('to');
            $locales = $strategy === 'columns' ? $this->localesOption() : [];
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        $stamp = date('Y_m_d_His');
        $class = $generator->className($model, $strategy, str_replace('_', '', $stamp));
        $directory = $this->laravel->databasePath('migrations');
        $path = $directory.DIRECTORY_SEPARATOR.$stamp.'_'.strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $class)).'.php';

        File::ensureDirectoryExists($directory);

        if (File::exists($path)) {
            $this->error("[{$path}] already exists.");

            return 1;
        }

        try {
            $migration = $generator->migration($model, $strategy, $class, $locales);
        } catch (TranslatableException $exception) {
            $this->error($exception->getMessage());

            if ($strategy === 'table') {
                $this->line('Create the translation model first (php artisan make:translatable), then run this command again.');
            }

            return 1;
        }

        File::put($path, $migration);

        if ($strategy === 'table' && $generator->parentKeyColumn($model, $model->getForeignKey()) === null) {
            $this->warn("The type of [{$model->getTable()}.{$model->getKeyName()}] could not be read; check that the foreign key column matches it.");
        }

        $this->info("Created [{$path}]. Review it, run it, then use translatable:migrate.");

        return 0;
    }
}
