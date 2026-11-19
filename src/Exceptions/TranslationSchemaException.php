<?php

namespace Pharaonic\Laravel\Translatable\Exceptions;

use RuntimeException;

/**
 * The database schema cannot hold the translations a model writes.
 */
final class TranslationSchemaException extends RuntimeException implements TranslatableException
{
    /**
     * @param  list<string>  $columns
     */
    public static function missingColumns(string $model, string $table, array $columns): self
    {
        return new self(sprintf(
            '[%s] needs the column(s) %s on the [%s] table. Create them with a migration (php artisan translatable:make-schema).',
            $model,
            implode(', ', $columns),
            $table
        ));
    }
}
