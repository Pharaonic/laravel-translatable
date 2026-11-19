<?php

namespace Pharaonic\Laravel\Translatable\Console;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

/**
 * Renders the package stubs. A stub published to the application's
 * stubs/translatable directory (vendor:publish --tag=translatable-stubs) wins.
 *
 * @internal
 */
final class Stubs
{
    public const DIRECTORY = __DIR__.'/../../stubs';

    public function __construct(private Application $app)
    {
    }

    /**
     * @param  array<string, string>  $replacements  placeholder => value, for {{ placeholder }}
     */
    public function render(string $name, array $replacements): string
    {
        if (preg_match('/^[a-z.]+$/', $name) !== 1) {
            throw new InvalidArgumentException("Invalid stub name [{$name}].");
        }

        $published = $this->app->basePath('stubs'.DIRECTORY_SEPARATOR.'translatable'.DIRECTORY_SEPARATOR.$name.'.stub');
        $path = is_file($published) ? $published : self::DIRECTORY.DIRECTORY_SEPARATOR.$name.'.stub';
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new InvalidArgumentException("The stub [{$name}] does not exist.");
        }

        $search = [];
        $replace = [];

        foreach ($replacements as $placeholder => $value) {
            $search[] = '{{ '.$placeholder.' }}';
            $replace[] = $value;
        }

        return str_replace($search, $replace, $contents);
    }

    /**
     * Schema builder lines, indented for a Schema::create/table closure.
     *
     * @param  list<string>  $lines
     */
    public static function lines(array $lines): string
    {
        return implode("\n", array_map(fn (string $line): string => '            '.$line, $lines));
    }
}
