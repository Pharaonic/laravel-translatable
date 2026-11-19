<?php

namespace Pharaonic\Laravel\Translatable\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Exceptions\TranslationSchemaException;

/**
 * Column listings per connection, database and table, read once per process.
 * The database name is part of the key, so a connection that is pointed at
 * another database (multi-tenancy) never reuses another database's columns.
 *
 * @internal
 */
final class SchemaInspector
{
    /** @var array<string, list<string>> */
    private array $columns = [];

    /**
     * The table's columns, as the database names them.
     *
     * @return list<string>
     */
    public function columns(Connection $connection, string $table): array
    {
        $key = $connection->getName().'|'.$connection->getDatabaseName().'|'.$table;

        if (! isset($this->columns[$key])) {
            $this->columns[$key] = array_values(array_filter($connection->getSchemaBuilder()->getColumnListing($table), 'is_string'));
        }

        return $this->columns[$key];
    }

    public function has(Connection $connection, string $table, string $column): bool
    {
        return in_array(strtolower($column), array_map('strtolower', $this->columns($connection, $table)), true);
    }

    /**
     * @param  list<string>  $required
     */
    public function assertColumns(Model $model, string $table, array $required): void
    {
        $missing = array_values(array_filter($required, fn (string $column): bool => ! $this->has($model->getConnection(), $table, $column)));

        if ($missing !== []) {
            throw TranslationSchemaException::missingColumns(get_class($model), $table, $missing);
        }
    }

    /**
     * The column's type as the database declares it, lowercased: "bigint unsigned",
     * "int(10) unsigned", "char(36)", "uuid", "character varying(255)", "integer".
     * Null when the table or column is unknown or the driver is not supported.
     */
    public function columnType(Connection $connection, string $table, string $column): ?string
    {
        $table = $connection->getTablePrefix().$table;

        $type = match ($connection->getDriverName()) {
            'mysql', 'mariadb' => $this->scalar($connection, 'select column_type from information_schema.columns where table_schema = database() and table_name = ? and column_name = ?', [$table, $column]),
            'pgsql' => $this->scalar($connection, "select case when character_maximum_length is null then data_type else data_type || '(' || character_maximum_length || ')' end from information_schema.columns where table_catalog = current_database() and table_schema = current_schema() and table_name = ? and column_name = ?", [$table, $column]),
            'sqlsrv' => $this->scalar($connection, "select case when character_maximum_length is null then data_type else data_type + '(' + cast(character_maximum_length as varchar) + ')' end from information_schema.columns where table_name = ? and column_name = ?", [$table, $column]),
            'sqlite' => $this->sqliteType($connection, $table, $column),
            default => null,
        };

        return $type === null || $type === '' ? null : strtolower(trim($type));
    }

    /**
     * @param  list<string>  $bindings
     */
    private function scalar(Connection $connection, string $sql, array $bindings): ?string
    {
        $row = $connection->selectOne($sql, $bindings);
        $value = is_object($row) ? array_values((array) $row)[0] ?? null : null;

        return is_string($value) ? $value : null;
    }

    private function sqliteType(Connection $connection, string $table, string $column): ?string
    {
        foreach ($connection->select('pragma table_info('.$connection->getQueryGrammar()->wrap($table).')') as $row) {
            $row = (array) $row;

            if (isset($row['name'], $row['type']) && is_string($row['name']) && is_string($row['type']) && strcasecmp($row['name'], $column) === 0) {
                return $row['type'];
            }
        }

        return null;
    }

    public function flush(): void
    {
        $this->columns = [];
    }
}
