<?php

namespace Pharaonic\Laravel\Translatable\Resolution;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Pharaonic\Laravel\Translatable\Contracts\ColumnsTranslatable;
use Pharaonic\Laravel\Translatable\Contracts\JsonTranslatable;
use Pharaonic\Laravel\Translatable\Contracts\TableTranslatable;
use Pharaonic\Laravel\Translatable\Contracts\TranslationStrategy;
use Pharaonic\Laravel\Translatable\Exceptions\ConflictingTranslationStrategiesException;
use Pharaonic\Laravel\Translatable\Exceptions\InvalidTranslationStrategyException;
use Pharaonic\Laravel\Translatable\Strategies\ColumnsStrategy;
use Pharaonic\Laravel\Translatable\Strategies\JsonStrategy;
use Pharaonic\Laravel\Translatable\Strategies\TableStrategy;

/**
 * Exactly one marker interface > config("translatable.strategy") > "table".
 *
 * @internal
 */
final class StrategyResolver
{
    public const STRATEGIES = ['table', 'json', 'columns'];

    private const MARKERS = [
        TableTranslatable::class => 'table',
        JsonTranslatable::class => 'json',
        ColumnsTranslatable::class => 'columns',
    ];

    private const IMPLEMENTATIONS = [
        'table' => TableStrategy::class,
        'json' => JsonStrategy::class,
        'columns' => ColumnsStrategy::class,
    ];

    /**
     * Marker per model class; class metadata only, safe across requests.
     *
     * @var array<string, string|null>
     */
    private array $markers = [];

    /** @var array<string, TranslationStrategy> */
    private array $strategies = [];

    public function name(Model $model): string
    {
        $class = get_class($model);

        if (! array_key_exists($class, $this->markers)) {
            $this->markers[$class] = $this->marker($class);
        }

        if ($this->markers[$class] !== null) {
            return $this->markers[$class];
        }

        $configured = Config::get('translatable.strategy');

        if ($configured === null) {
            return 'table';
        }

        if (! is_string($configured) || ! in_array($configured, self::STRATEGIES, true)) {
            throw InvalidTranslationStrategyException::forValue($class, $configured, self::STRATEGIES);
        }

        return $configured;
    }

    /**
     * Where the strategy comes from: "marker", "config" or "default".
     */
    public function source(Model $model): string
    {
        $this->name($model);

        if ($this->markers[get_class($model)] !== null) {
            return 'marker';
        }

        return Config::get('translatable.strategy') === null ? 'default' : 'config';
    }

    public function resolve(Model $model): TranslationStrategy
    {
        $name = $this->name($model);

        if (! isset($this->strategies[$name])) {
            $strategy = Container::getInstance()->make(self::IMPLEMENTATIONS[$name]);

            if (! $strategy instanceof TranslationStrategy) {
                throw new InvalidTranslationStrategyException("The {$name} strategy must implement TranslationStrategy.");
            }

            $this->strategies[$name] = $strategy;
        }

        return $this->strategies[$name];
    }

    private function marker(string $class): ?string
    {
        $found = [];

        foreach (self::MARKERS as $marker => $name) {
            if (is_a($class, $marker, true)) {
                $found[$marker] = $name;
            }
        }

        if (count($found) > 1) {
            throw ConflictingTranslationStrategiesException::forModel($class, array_keys($found));
        }

        return $found === [] ? null : reset($found);
    }
}
