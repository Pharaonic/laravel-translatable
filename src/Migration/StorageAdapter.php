<?php

namespace Pharaonic\Laravel\Translatable\Migration;

use Illuminate\Database\Eloquent\Model;

/**
 * Bulk reads and writes of one storage layout, used only by the migration
 * engine. It works on the layout it is asked for, whatever strategy the model
 * currently uses, and never goes through the model's save lifecycle.
 *
 * Values are canonical: what the json strategy would store (native scalars and arrays).
 *
 * @internal
 */
interface StorageAdapter
{
    public function name(): string;

    /**
     * Problems that stop a migration from or to this layout.
     *
     * @return list<string>
     */
    public function preflight(Model $model, bool $target): array;

    /**
     * @param  list<int|string>  $keys
     * @return array<int|string, array<string, array<string, mixed>>> key => field => locale => value
     */
    public function read(Model $model, array $keys): array;

    /**
     * Make the stored values of the given fields exactly $values; other fields stay as stored.
     *
     * @param  array<string, array<string, mixed>>  $values  field => locale => value
     * @param  list<string>  $fields
     */
    public function write(Model $model, int|string $key, array $values, array $fields): void;

    /**
     * Locales in the entities that this layout cannot store.
     *
     * @param  array<int|string, array<string, array<string, mixed>>>  $entities
     * @return list<string>
     */
    public function unsupportedLocales(Model $model, array $entities): array;
}
