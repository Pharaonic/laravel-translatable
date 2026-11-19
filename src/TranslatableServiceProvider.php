<?php

namespace Pharaonic\Laravel\Translatable;

use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\Translatable\Console\InspectTranslationsCommand;
use Pharaonic\Laravel\Translatable\Console\MakeTranslatableCommand;
use Pharaonic\Laravel\Translatable\Console\MakeTranslationSchemaCommand;
use Pharaonic\Laravel\Translatable\Console\MigrateTranslationsCommand;
use Pharaonic\Laravel\Translatable\Console\RollbackTranslationMigrationCommand;
use Pharaonic\Laravel\Translatable\Console\SchemaGenerator;
use Pharaonic\Laravel\Translatable\Console\Stubs;
use Pharaonic\Laravel\Translatable\Console\VerifyTranslationsCommand;
use Pharaonic\Laravel\Translatable\Contracts\FallbackPolicy;
use Pharaonic\Laravel\Translatable\Migration\Canonical;
use Pharaonic\Laravel\Translatable\Migration\ColumnsAdapter;
use Pharaonic\Laravel\Translatable\Migration\JsonAdapter;
use Pharaonic\Laravel\Translatable\Migration\Migrator;
use Pharaonic\Laravel\Translatable\Migration\TableAdapter;
use Pharaonic\Laravel\Translatable\Query\ColumnsQueryCompiler;
use Pharaonic\Laravel\Translatable\Query\JsonQueryCompiler;
use Pharaonic\Laravel\Translatable\Query\TableQueryCompiler;
use Pharaonic\Laravel\Translatable\Query\TranslationScopes;
use Pharaonic\Laravel\Translatable\Resolution\DefinitionResolver;
use Pharaonic\Laravel\Translatable\Resolution\LocaleColumnMapper;
use Pharaonic\Laravel\Translatable\Resolution\StrategyResolver;
use Pharaonic\Laravel\Translatable\Strategies\ColumnsStrategy;
use Pharaonic\Laravel\Translatable\Strategies\JsonStrategy;
use Pharaonic\Laravel\Translatable\Strategies\TableStrategy;
use Pharaonic\Laravel\Translatable\Support\CastResolver;
use Pharaonic\Laravel\Translatable\Support\FallbackResolver;
use Pharaonic\Laravel\Translatable\Support\SchemaInspector;
use Pharaonic\Laravel\Translatable\Support\Translator;

class TranslatableServiceProvider extends ServiceProvider
{
    /**
     * Stateless services; class metadata caches only, never model or request state.
     */
    private const SINGLETONS = [
        StrategyResolver::class,
        DefinitionResolver::class,
        LocaleColumnMapper::class,
        CastResolver::class,
        SchemaInspector::class,
        TableQueryCompiler::class,
        JsonQueryCompiler::class,
        ColumnsQueryCompiler::class,
        TableStrategy::class,
        JsonStrategy::class,
        ColumnsStrategy::class,
        Translator::class,
        TranslationScopes::class,
        Canonical::class,
        TableAdapter::class,
        JsonAdapter::class,
        ColumnsAdapter::class,
        Migrator::class,
        SchemaGenerator::class,
        Stubs::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/translatable.php', 'translatable');

        foreach (self::SINGLETONS as $service) {
            $this->app->singleton($service);
        }

        if (! $this->app->bound(FallbackPolicy::class)) {
            $this->app->singleton(FallbackPolicy::class, FallbackResolver::class);
        }
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            MakeTranslatableCommand::class,
            InspectTranslationsCommand::class,
            MakeTranslationSchemaCommand::class,
            MigrateTranslationsCommand::class,
            VerifyTranslationsCommand::class,
            RollbackTranslationMigrationCommand::class,
        ]);

        $this->publishes([
            __DIR__.'/../config/translatable.php' => $this->app->configPath('translatable.php'),
        ], ['pharaonic', 'laravel-translatable', 'translatable-config']);

        $this->publishes([
            Stubs::DIRECTORY => $this->app->basePath('stubs'.DIRECTORY_SEPARATOR.'translatable'),
        ], 'translatable-stubs');
    }
}
