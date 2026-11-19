<?php

namespace Pharaonic\Laravel\Translatable\Contracts;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Data\TranslationPatchSet;

/**
 * Persistence of one strategy. Implementations are stateless; pending changes
 * live on the model.
 *
 * @internal
 */
interface TranslationStrategy
{
    /**
     * "table", "json" or "columns".
     */
    public function name(): string;

    /**
     * The key this strategy stores a locale under. The columns strategy has one
     * column per locale, so "pt-BR" and "pt_br" are the same locale there.
     */
    public function normalizeLocale(string $locale): string;

    /**
     * Stored values, exactly as persisted (no fallback, no pending changes, no casts).
     * With $locale, only that locale's values must be complete; a strategy may
     * then skip loading the other locales.
     *
     * @return array<string, array<string, mixed>> field => locale => value
     */
    public function stored(Model $model, ?string $locale = null): array;

    /**
     * Values as last read from the database, before any unsaved change.
     *
     * @return array<string, array<string, mixed>> field => locale => value
     */
    public function original(Model $model): array;

    /**
     * Mirror staged changes where the strategy exposes them before saving
     * (the table strategy's translation records). Runs after every change.
     */
    public function stage(Model $model, TranslationPatchSet $changes): void;

    /**
     * Runs inside the save transaction before the model row is written.
     */
    public function prepare(Model $model, TranslationPatchSet $changes): void;

    /**
     * Runs inside the save transaction after the model row is written.
     */
    public function persist(Model $model, TranslationPatchSet $changes): void;

    /**
     * Runs after the model is deleted, unless it was only soft deleted.
     */
    public function delete(Model $model): void;

    /**
     * State to restore on the model and its translation records when the save fails.
     */
    public function snapshot(Model $model): mixed;

    public function restore(Model $model, mixed $snapshot): void;

    /**
     * Forget values read from storage after a save or a refresh.
     */
    public function flush(Model $model): void;

    /**
     * Whether the model already holds what the strategy reads, so serializing
     * it never runs a query (a loaded translations relation, selected columns).
     */
    public function isLoaded(Model $model): bool;

    /**
     * Physical model attributes that hold translations.
     *
     * @return list<string>
     */
    public function storageAttributes(Model $model): array;

    public function query(): TranslationQueryCompiler;
}
