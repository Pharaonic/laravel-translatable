<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Pharaonic\Laravel\Translatable\Contracts\ColumnsTranslatable;
use Pharaonic\Laravel\Translatable\Contracts\JsonTranslatable;
use Pharaonic\Laravel\Translatable\Contracts\TableTranslatable;
use Pharaonic\Laravel\Translatable\Exceptions\ConflictingTranslationStrategiesException;
use Pharaonic\Laravel\Translatable\Exceptions\InvalidTranslatableDefinitionException;
use Pharaonic\Laravel\Translatable\Exceptions\InvalidTranslationStrategyException;
use Pharaonic\Laravel\Translatable\Resolution\DefinitionResolver;
use Pharaonic\Laravel\Translatable\Resolution\StrategyResolver;
use Pharaonic\Laravel\Translatable\Translatable;

class PlainModel extends Model
{
    use Translatable;

    /** @var array<int, string> */
    protected $translatables = ['title'];
}

class TableMarked extends PlainModel implements TableTranslatable
{
}

class JsonMarked extends PlainModel implements JsonTranslatable
{
}

class ColumnsMarked extends PlainModel implements ColumnsTranslatable
{
}

class InheritsJson extends JsonMarked
{
}

class TwoMarkers extends JsonMarked implements ColumnsTranslatable
{
}

$matrix = (static function (): array {
    return [
        'no marker, missing config' => [PlainModel::class, null, 'table'],
        'no marker, json' => [PlainModel::class, 'json', 'json'],
        'no marker, columns' => [PlainModel::class, 'columns', 'columns'],
        'table marker over json' => [TableMarked::class, 'json', 'table'],
        'json marker over table' => [JsonMarked::class, 'table', 'json'],
        'columns marker over json' => [ColumnsMarked::class, 'json', 'columns'],
        'inherited marker' => [InheritsJson::class, 'columns', 'json'],
    ];
})();

$invalid = (static function (): array {
    return [
        'shared' => ['shared'],
        'hybrid' => ['hybrid'],
        'json-bag' => ['json-bag'],
        'empty' => [''],
        'upper case' => ['JSON'],
        'class name' => [TableMarked::class],
        'array' => [['table']],
    ];
})();

$invalidDefinitions = (static function (): array {
    return [
        'storage option' => [['title' => ['storage' => 'json']], 'never chooses its storage'],
        'strategy option' => [['title' => ['strategy' => 'columns']], 'never chooses its storage'],
        'unknown option' => [['title' => ['translate' => true]], 'unknown option'],
        'unknown type' => [['title' => ['type' => 'money']], 'must be one of'],
        'reserved name' => [['translations'], 'reserved'],
        'unsafe name' => [['title; drop'], 'not a valid field name'],
    ];
})();

function resolutionResolve(Model $model): string
{
    return app(StrategyResolver::class)->name($model);
}

test('marker then config then table', function (string $class, mixed $configured, string $expected) {
    Config::set('translatable.strategy', $configured);

    $this->assertSame($expected, resolutionResolve(new $class));
})->with($matrix);

test('runtime config changes apply to unmarked models', function () {
    Config::set('translatable.strategy', 'json');
    $this->assertSame('json', resolutionResolve(new PlainModel));

    Config::set('translatable.strategy', 'table');
    $this->assertSame('table', resolutionResolve(new PlainModel));
    $this->assertSame('json', resolutionResolve(new JsonMarked));
});

test('invalid strategies fail with the model and choices', function (mixed $configured) {
    Config::set('translatable.strategy', $configured);

    try {
        resolutionResolve(new PlainModel);
        $this->fail('An invalid strategy must throw.');
    } catch (InvalidTranslationStrategyException $exception) {
        $this->assertStringContainsString(PlainModel::class, $exception->getMessage());
        $this->assertStringContainsString('table, json, columns', $exception->getMessage());
    }
})->with($invalid);

test('two markers conflict', function () {
    $this->expectException(ConflictingTranslationStrategiesException::class);

    resolutionResolve(new TwoMarkers);
});

test('a marker without the trait adds no behavior', function () {
    $model = new class extends Model implements JsonTranslatable
    {
    };

    $this->assertFalse(method_exists($model, 'getTranslation'));
});

test('invalid field definitions are rejected', function (array $definition, string $message) {
    $model = new class extends Model
    {
        use Translatable;

        /** @var array<mixed> */
        public static array $definition = [];

        /**
         * @return array<mixed>
         */
        public function translatable(): array
        {
            return static::$definition;
        }
    };
    $model::$definition = $definition;

    $this->expectException(InvalidTranslatableDefinitionException::class);
    $this->expectExceptionMessage($message);

    (new DefinitionResolver)->for($model);
})->with($invalidDefinitions);

test('contradictory definitions are rejected', function () {
    $model = new class extends Model
    {
        use Translatable;

        /** @var array<int, string> */
        protected $translatables = ['title'];

        /**
         * @return array<string, array<string, mixed>>
         */
        public function translatable(): array
        {
            return ['body' => []];
        }
    };

    $this->expectException(InvalidTranslatableDefinitionException::class);
    (new DefinitionResolver)->for($model);
});

test('set mutators on translated fields are rejected', function () {
    $model = new class extends Model
    {
        use Translatable;

        /** @var array<int, string> */
        protected $translatables = ['title'];

        public function setTitleAttribute(string $value): void
        {
        }
    };

    $this->expectException(InvalidTranslatableDefinitionException::class);
    (new DefinitionResolver)->for($model);
});

test('the method wins over a matching legacy list', function () {
    $model = new class extends Model
    {
        use Translatable;

        /** @var array<int, string> */
        protected $translatables = ['title'];

        /**
         * @return array<string, array<string, mixed>>
         */
        public function translatable(): array
        {
            return ['title' => ['type' => 'string', 'required' => true]];
        }
    };

    $registry = (new DefinitionResolver)->for($model);

    $this->assertSame(['title'], $registry->required());
    $this->assertSame('string', $registry->get('title')?->type);
});

test('the deprecated translatable attributes list is still read', function () {
    $model = new class extends Model
    {
        use Translatable;

        /** @var array<int, string> */
        protected $translatableAttributes = ['title', 'body'];
    };

    $this->assertSame(['title', 'body'], (new DefinitionResolver)->for($model)->names());
});
