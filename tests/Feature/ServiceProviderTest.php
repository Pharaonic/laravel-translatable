<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\Translatable\TranslatableServiceProvider;

$publishTags = (static function (): array {
    return [
        'pharaonic' => ['pharaonic'],
        'laravel-translatable' => ['laravel-translatable'],
    ];
})();

it('registers the service provider', function () {
    $this->assertNotEmpty($this->app->getProviders(TranslatableServiceProvider::class));
});

it('registers the make translatable command in the console', function () {
    $this->assertArrayHasKey('make:translatable', Artisan::all());
});

it('publishes the configuration under the package tags', function (string $tag) {
    $paths = ServiceProvider::pathsToPublish(TranslatableServiceProvider::class, $tag);

    $this->assertCount(1, $paths);
    $this->assertSame($this->app->configPath('translatable.php'), array_values($paths)[0]);
})->with($publishTags);
