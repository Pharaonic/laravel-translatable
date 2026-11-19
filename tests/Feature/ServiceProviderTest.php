<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\Translatable\Tests\TestCase;
use Pharaonic\Laravel\Translatable\TranslatableServiceProvider;

final class ServiceProviderTest extends TestCase
{
    public function test_it_registers_the_service_provider(): void
    {
        $this->assertNotEmpty($this->app->getProviders(TranslatableServiceProvider::class));
    }

    public function test_it_registers_the_make_translatable_command_in_the_console(): void
    {
        $this->assertArrayHasKey('make:translatable', Artisan::all());
    }

    /**
     * @dataProvider publishTags
     */
    public function test_it_publishes_the_configuration_under_the_package_tags(string $tag): void
    {
        $paths = ServiceProvider::pathsToPublish(TranslatableServiceProvider::class, $tag);

        $this->assertCount(1, $paths);
        $this->assertSame($this->app->configPath('translatable.php'), array_values($paths)[0]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function publishTags(): array
    {
        return [
            'pharaonic' => ['pharaonic'],
            'laravel-translatable' => ['laravel-translatable'],
        ];
    }
}
