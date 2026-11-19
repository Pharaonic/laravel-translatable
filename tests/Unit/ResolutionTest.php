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
use Pharaonic\Laravel\Translatable\Tests\TestCase;
use Pharaonic\Laravel\Translatable\Translatable;
use PHPUnit\Framework\Attributes\DataProvider;

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

final class ResolutionTest extends TestCase
{
    private function resolve(Model $model): string
    {
        return $this->app->make(StrategyResolver::class)->name($model);
    }

    /**
     * @return array<string, array{class-string<Model>, mixed, string}>
     */
    public static function matrix(): array
    {
        return [
            'no marker, missing config' => [PlainModel::class, null, 'table'],
            'no marker, json' => [PlainModel::class, 'json', 'json'],
            'no marker, columns' => [PlainModel::class, 'columns', 'columns'],
            'table marker over json' => [TableMarked::class, 'json', 'table'],
            'json marker over table' => [JsonMarked::class, 'table', 'json'],
            'columns marker over json' => [ColumnsMarked::class, 'json', 'columns'],
            'inherited marker' => [InheritsJson::class, 'columns', 'json'],
        ];
    }

    /**
     * @dataProvider matrix
     *
     * @param  class-string<Model>  $class
     */
    #[DataProvider('matrix')]
    public function test_marker_then_config_then_table(string $class, mixed $configured, string $expected): void
    {
        Config::set('translatable.strategy', $configured);

        $this->assertSame($expected, $this->resolve(new $class));
    }

    public function test_runtime_config_changes_apply_to_unmarked_models(): void
    {
        Config::set('translatable.strategy', 'json');
        $this->assertSame('json', $this->resolve(new PlainModel));

        Config::set('translatable.strategy', 'table');
        $this->assertSame('table', $this->resolve(new PlainModel));
        $this->assertSame('json', $this->resolve(new JsonMarked));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalid(): array
    {
        return [
            'shared' => ['shared'],
            'hybrid' => ['hybrid'],
            'json-bag' => ['json-bag'],
            'empty' => [''],
            'upper case' => ['JSON'],
            'class name' => [TableMarked::class],
            'array' => [['table']],
        ];
    }

    /**
     * @dataProvider invalid
     */
    #[DataProvider('invalid')]
    public function test_invalid_strategies_fail_with_the_model_and_choices(mixed $configured): void
    {
        Config::set('translatable.strategy', $configured);

        try {
            $this->resolve(new PlainModel);
            $this->fail('An invalid strategy must throw.');
        } catch (InvalidTranslationStrategyException $exception) {
            $this->assertStringContainsString(PlainModel::class, $exception->getMessage());
            $this->assertStringContainsString('table, json, columns', $exception->getMessage());
        }
    }

    public function test_two_markers_conflict(): void
    {
        $this->expectException(ConflictingTranslationStrategiesException::class);

        $this->resolve(new TwoMarkers);
    }

    public function test_a_marker_without_the_trait_adds_no_behavior(): void
    {
        $model = new class extends Model implements JsonTranslatable
        {
        };

        $this->assertFalse(method_exists($model, 'getTranslation'));
    }

    /**
     * @return array<string, array{array<mixed>, string}>
     */
    public static function invalidDefinitions(): array
    {
        return [
            'storage option' => [['title' => ['storage' => 'json']], 'never chooses its storage'],
            'strategy option' => [['title' => ['strategy' => 'columns']], 'never chooses its storage'],
            'unknown option' => [['title' => ['translate' => true]], 'unknown option'],
            'unknown type' => [['title' => ['type' => 'money']], 'must be one of'],
            'reserved name' => [['translations'], 'reserved'],
            'unsafe name' => [['title; drop'], 'not a valid field name'],
        ];
    }

    /**
     * @dataProvider invalidDefinitions
     *
     * @param  array<mixed>  $definition
     */
    #[DataProvider('invalidDefinitions')]
    public function test_invalid_field_definitions_are_rejected(array $definition, string $message): void
    {
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
    }

    public function test_contradictory_definitions_are_rejected(): void
    {
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
    }

    public function test_set_mutators_on_translated_fields_are_rejected(): void
    {
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
    }

    public function test_the_method_wins_over_a_matching_legacy_list(): void
    {
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
    }

    public function test_the_deprecated_translatable_attributes_list_is_still_read(): void
    {
        $model = new class extends Model
        {
            use Translatable;

            /** @var array<int, string> */
            protected $translatableAttributes = ['title', 'body'];
        };

        $this->assertSame(['title', 'body'], (new DefinitionResolver)->for($model)->names());
    }
}
