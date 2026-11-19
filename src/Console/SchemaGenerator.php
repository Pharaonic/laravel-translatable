<?php

namespace Pharaonic\Laravel\Translatable\Console;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Translatable\Data\TranslationDefinition;
use Pharaonic\Laravel\Translatable\Resolution\DefinitionResolver;
use Pharaonic\Laravel\Translatable\Resolution\LocaleColumnMapper;
use Pharaonic\Laravel\Translatable\Strategies\TableStrategy;
use Pharaonic\Laravel\Translatable\Support\SchemaInspector;

/**
 * Migrations for the storage of one strategy, rendered from the stubs. Every
 * identifier comes from the validated field registry, the model, or a validated locale.
 *
 * @internal
 */
final class SchemaGenerator
{
    public function __construct(
        private DefinitionResolver $definitions,
        private LocaleColumnMapper $columns,
        private TableStrategy $table,
        private Stubs $stubs,
        private SchemaInspector $schema,
    ) {}

    public function className(Model $model, string $strategy, string $suffix): string
    {
        return 'Add'.Str::studly($strategy).'TranslationsTo'.Str::studly($model->getTable()).'Table'.$suffix;
    }

    /**
     * @param  list<string>  $locales  The locales to create columns for (columns strategy).
     */
    public function migration(Model $model, string $strategy, string $class, array $locales = []): string
    {
        if ($strategy === 'table') {
            $translation = $this->table->translationModel($model);
            $foreignKey = $this->table->foreignKey($model);

            $keyColumn = $this->parentKeyColumn($model, $foreignKey) ?? self::defaultKeyColumn($foreignKey, $model->getKeyType() === 'string');

            return $this->translationsTable($class, (new $translation)->getTable(), $model->getTable(), $foreignKey, $model->getKeyName(), $keyColumn, $this->definitions->for($model)->all());
        }

        $lines = [];
        $columns = [];

        foreach ($this->definitions->for($model)->all() as $field => $definition) {
            if ($strategy === 'json') {
                $column = $this->columns->jsonColumn($model, $field);
                $lines[] = "\$table->json('{$column}')->nullable();";
                $columns[] = $column;

                continue;
            }

            foreach ($locales as $locale) {
                $column = $this->columns->localeColumn($model, $field, $locale);
                $lines[] = self::column($definition, $column).'->nullable();';
                $columns[] = $column;
            }
        }

        return $this->stubs->render('migration.add', [
            'migration' => $class,
            'strategy' => $strategy,
            'table' => $model->getTable(),
            'columns' => Stubs::lines($lines),
            'dropColumns' => "'".implode("', '", $columns)."'",
        ]);
    }

    /**
     * @param  array<string, TranslationDefinition>  $fields
     */
    public function translationsTable(string $class, string $table, string $parent, string $foreignKey, string $parentKey, string $keyColumn, array $fields): string
    {
        $lines = [
            "\$table->bigIncrements('id');",
            $keyColumn.';',
            "\$table->string('".TableStrategy::LOCALE_COLUMN."', 35);",
        ];

        foreach ($fields as $field => $definition) {
            $lines[] = self::column($definition, $field).'->nullable();';
        }

        $lines[] = "\$table->unique(['{$foreignKey}', '".TableStrategy::LOCALE_COLUMN."']);";
        $lines[] = "\$table->foreign('{$foreignKey}')->references('{$parentKey}')->on('{$parent}')->onDelete('cascade');";

        return $this->stubs->render('migration.create', ['migration' => $class, 'table' => $table, 'columns' => Stubs::lines($lines)]);
    }

    /**
     * The foreign key column with the exact type of the model's key column, so the
     * foreign key is accepted by MySQL and PostgreSQL. Null when the type is unknown.
     */
    public function parentKeyColumn(Model $model, string $foreignKey): ?string
    {
        $type = $this->schema->columnType($model->getConnection(), $model->getTable(), $model->getKeyName());

        return $type === null ? null : self::keyColumnFor($type, $foreignKey, $model->getConnection()->getDriverName());
    }

    /**
     * The foreign key column for a key type as SchemaInspector::columnType() returns it.
     */
    public static function keyColumnFor(string $type, string $foreignKey, string $driver): ?string
    {
        if (preg_match('/^(tiny|small|medium|big)?int(eger)?\b/', $type, $match) === 1) {
            if ($driver === 'sqlite') {
                // SQLite declares every auto-incrementing key "integer"; Laravel's key is a big integer.
                return "\$table->unsignedBigInteger('{$foreignKey}')";
            }

            $method = ($match[1] ?? '') === '' ? 'integer' : $match[1].'Integer';

            if (str_contains($type, 'unsigned')) {
                $method = 'unsigned'.ucfirst($method);
            }

            return "\$table->{$method}('{$foreignKey}')";
        }

        if ($type === 'uuid' || $type === 'uniqueidentifier') {
            return "\$table->uuid('{$foreignKey}')";
        }

        if (preg_match('/^(n?char|character|bpchar)\((\d+)\)$/', $type, $match) === 1) {
            return "\$table->char('{$foreignKey}', {$match[2]})";
        }

        if (preg_match('/^(n?varchar|varchar2|character varying)\((\d+)\)$/', $type, $match) === 1) {
            return "\$table->string('{$foreignKey}', {$match[2]})";
        }

        if (preg_match('/^(n?varchar|text|character varying)$/', $type) === 1) {
            return "\$table->string('{$foreignKey}')";
        }

        return null;
    }

    public static function defaultKeyColumn(string $foreignKey, bool $stringKey): string
    {
        return $stringKey ? "\$table->string('{$foreignKey}')" : "\$table->unsignedBigInteger('{$foreignKey}')";
    }

    public static function column(TranslationDefinition $definition, string $column): string
    {
        $method = match ($definition->type) {
            'string' => 'string',
            'int' => 'integer',
            'float' => 'double',
            'bool' => 'boolean',
            'array', 'object' => 'json',
            'date' => 'date',
            'datetime' => 'dateTime',
            default => 'text',
        };

        return "\$table->{$method}('{$column}')";
    }
}
