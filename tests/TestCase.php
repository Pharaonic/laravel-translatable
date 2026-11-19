<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Pharaonic\Laravel\Translatable\TranslatableServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            TranslatableServiceProvider::class,
        ];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.locale', 'en');
        $app['config']->set('app.fallback_locale', 'en');
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', self::connection());
    }

    /**
     * TRANSLATABLE_DB selects the engine: sqlite (default), mysql, mariadb or pgsql.
     * The server ones read TRANSLATABLE_DB_HOST, _PORT, _DATABASE, _USERNAME and _PASSWORD.
     *
     * @return array<string, mixed>
     */
    public static function connection(): array
    {
        $engine = getenv('TRANSLATABLE_DB') ?: 'sqlite';

        if ($engine === 'sqlite') {
            return ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true];
        }

        $env = fn (string $key, string $default): string => getenv('TRANSLATABLE_DB_'.$key) ?: $default;

        return [
            'driver' => $engine === 'mariadb' ? 'mysql' : $engine,
            'host' => $env('HOST', '127.0.0.1'),
            'port' => $env('PORT', $engine === 'pgsql' ? '5432' : '3306'),
            'database' => $env('DATABASE', 'translatable'),
            'username' => $env('USERNAME', $engine === 'pgsql' ? 'postgres' : 'root'),
            'password' => $env('PASSWORD', ''),
            'charset' => $engine === 'pgsql' ? 'utf8' : 'utf8mb4',
            'collation' => $engine === 'pgsql' ? null : 'utf8mb4_unicode_ci',
            'prefix' => '',
            'schema' => 'public',
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
