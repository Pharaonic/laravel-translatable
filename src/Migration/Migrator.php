<?php

namespace Pharaonic\Laravel\Translatable\Migration;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Pharaonic\Laravel\Translatable\Data\MigrationReport;
use Pharaonic\Laravel\Translatable\Events\TranslationsMigrated;
use Pharaonic\Laravel\Translatable\Resolution\DefinitionResolver;
use Pharaonic\Laravel\Translatable\Resolution\StrategyResolver;
use Throwable;

/**
 * Copies translations between two layouts of one model, chunk by chunk.
 *
 * The source is read and never changed. Each chunk is one transaction on the
 * model's connection: target values before the write go to the journal, then
 * the target is written and the checkpoint advances. Records are visited by
 * ascending primary key (keyset pagination), so a resumed run continues after
 * the last committed key and writing a record twice gives the same result.
 *
 * @internal
 */
final class Migrator
{
    public const SAMPLES = 10;

    public function __construct(
        private TableAdapter $table,
        private JsonAdapter $json,
        private ColumnsAdapter $columns,
        private DefinitionResolver $definitions,
    ) {
    }

    public function adapter(string $name): StorageAdapter
    {
        return match ($name) {
            'table' => $this->table,
            'json' => $this->json,
            'columns' => $this->columns,
            default => throw new InvalidArgumentException(sprintf('Unknown layout "%s". Valid: %s.', $name, implode(', ', StrategyResolver::STRATEGIES))),
        };
    }

    /**
     * @param  array{apply?: bool, chunk?: int, resume?: bool, migration_id?: string|null, fields?: list<string>|null, ignore_columns?: list<string>, without_scopes?: bool}  $options
     */
    public function migrate(Model $model, string $from, string $to, Journal $journal, array $options = []): MigrationReport
    {
        $source = $this->adapter($from);
        $target = $this->adapter($to);
        $fields = $this->fields($model, $options['fields'] ?? null);
        $apply = $options['apply'] ?? false;
        $chunk = max(1, $options['chunk'] ?? 500);
        $class = get_class($model);

        $issues = $this->preflight($model, $source, $target, $options['ignore_columns'] ?? []);
        $report = new MigrationReport($class, $from, $to, $apply ? 'completed' : 'dry-run');
        $columns = $from === 'columns' || $to === 'columns';

        $state = null;
        $last = null;

        if ($apply && $issues === []) {
            $state = $this->resumable($journal, $class, $from, $to, $options);

            if ($state !== null && ($state['fields'] ?? null) !== $fields) {
                $issues[] = 'The resumed migration copies other fields; start a new one instead.';
            }

            $state ??= $journal->start($class, $from, $to, $fields);
            $last = $state['last_key'] ?? null;
            $report->migrationId = is_string($state['id'] ?? null) ? $state['id'] : null;

            if ($report->migrationId === null) {
                $issues[] = 'The migration journal holds no id.';
            }
        }

        if ($issues !== []) {
            $report->status = 'blocked';
            $report->issues = $issues;

            return $report;
        }

        while (($keys = $this->keys($model, $last, $chunk, $options['without_scopes'] ?? false)) !== []) {
            $sourceValues = $source->read($model, $keys);
            $targetValues = $target->read($model, $keys);

            $unsupported = $target->unsupportedLocales($model, $sourceValues);

            if ($unsupported !== []) {
                $report->status = 'blocked';
                $report->issues[] = sprintf('The %s layout cannot store the locale(s) %s found in the source.', $to, implode(', ', $unsupported));

                return $report;
            }

            $changes = [];

            foreach ($keys as $key) {
                $wanted = Canonical::normalize($sourceValues[$key] ?? [], $fields, $columns);
                $current = Canonical::normalize($targetValues[$key] ?? [], $fields, $columns);

                $report->records++;
                $report->values += array_sum(array_map('count', $wanted));

                if ($wanted !== $current) {
                    $report->changed++;
                    $changes[$key] = [$wanted, $current];

                    if (count($report->samples) < self::SAMPLES) {
                        $report->samples[] = ['key' => $key, 'source' => $wanted, 'target' => $current];
                    }
                }
            }

            if ($apply && $state !== null && $report->migrationId !== null) {
                $id = $report->migrationId;
                $model->getConnection()->transaction(function () use ($model, $target, $journal, $state, $changes, $fields, $keys, $id) {
                    foreach ($changes as $key => [$wanted, $current]) {
                        $journal->remember($id, $key, $current);
                        $target->write($model, $key, $wanted, $fields);
                    }

                    $state['last_key'] = end($keys);
                    $state['processed'] = (is_int($state['processed'] ?? null) ? $state['processed'] : 0) + count($keys);
                    $journal->save($state);
                });

                $state['last_key'] = end($keys);
                $state['processed'] = (is_int($state['processed'] ?? null) ? $state['processed'] : 0) + count($keys);
            }

            $last = end($keys);
        }

        if ($apply && $state !== null && $report->migrationId !== null) {
            $state['status'] = 'completed';
            $state['finished_at'] = date(DATE_ATOM);
            $journal->save($state);

            Event::dispatch(new TranslationsMigrated($class, $from, $to, $report->migrationId, $report->records));
        }

        return $report;
    }

    /**
     * Compare every record's values in both layouts.
     *
     * @param  array{fields?: list<string>|null, without_scopes?: bool, chunk?: int}  $options
     */
    public function verify(Model $model, string $from, string $to, array $options = []): MigrationReport
    {
        $source = $this->adapter($from);
        $target = $this->adapter($to);
        $fields = $this->fields($model, $options['fields'] ?? null);
        $report = new MigrationReport(get_class($model), $from, $to, 'verified');
        $columns = $from === 'columns' || $to === 'columns';
        $last = null;

        $issues = [...$source->preflight($model, false), ...$target->preflight($model, true)];

        if ($issues !== []) {
            $report->status = 'blocked';
            $report->issues = $issues;

            return $report;
        }

        while (($keys = $this->keys($model, $last, max(1, $options['chunk'] ?? 500), $options['without_scopes'] ?? false)) !== []) {
            $sourceValues = $source->read($model, $keys);
            $targetValues = $target->read($model, $keys);

            foreach ($keys as $key) {
                $report->records++;
                $wanted = $sourceValues[$key] ?? [];
                $current = $targetValues[$key] ?? [];
                $report->values += array_sum(array_map('count', Canonical::normalize($wanted, $fields, $columns)));

                if (Canonical::hash($wanted, $fields, $columns) !== Canonical::hash($current, $fields, $columns)) {
                    $report->mismatches++;

                    if (count($report->samples) < self::SAMPLES) {
                        $report->samples[] = ['key' => $key, 'source' => Canonical::normalize($wanted, $fields, $columns), 'target' => Canonical::normalize($current, $fields, $columns)];
                    }
                }
            }

            $last = end($keys);
        }

        if ($report->mismatches > 0) {
            $report->status = 'mismatch';
        } elseif ($fields !== $this->definitions->for($model)->names()) {
            $report->issues[] = 'Only some fields were verified; verify every field before switching the strategy.';
        }

        return $report;
    }

    /**
     * Write back the target values the migration replaced. Only what the
     * journal holds can be restored: changes made after the migration are overwritten.
     */
    public function rollback(Model $model, Journal $journal, string $id, bool $apply): MigrationReport
    {
        $state = $journal->load($id);

        if ($state === null || ($state['model'] ?? null) !== get_class($model) || ! is_string($state['to'] ?? null) || ! is_string($state['from'] ?? null)) {
            return new MigrationReport(get_class($model), '?', '?', 'blocked', $id, issues: ["No migration [{$id}] of [".get_class($model).'] in the journal.']);
        }

        $target = $this->adapter($state['to']);
        $fields = is_array($state['fields'] ?? null) ? array_values(array_filter($state['fields'], 'is_string')) : [];
        $previous = $journal->previous($id);
        $report = new MigrationReport(get_class($model), $state['from'], $state['to'], $apply ? 'rolled-back' : 'dry-run', $id, records: count($previous));

        if (! $apply) {
            return $report;
        }

        $model->getConnection()->transaction(function () use ($model, $target, $previous, $fields) {
            foreach ($previous as $key => $values) {
                $target->write($model, $key, $values, $fields);
            }
        });

        $state['status'] = 'rolled-back';
        $state['finished_at'] = date(DATE_ATOM);
        $journal->save($state);

        return $report;
    }

    /**
     * @param  list<string>  $ignored
     * @return list<string>
     */
    private function preflight(Model $model, StorageAdapter $source, StorageAdapter $target, array $ignored): array
    {
        if ($source->name() === $target->name()) {
            return ['The source and target layouts are the same.'];
        }

        $issues = [...$source->preflight($model, false), ...$target->preflight($model, true)];

        if ($source instanceof TableAdapter) {
            foreach ($source->extraColumns($model) as $column => $rows) {
                if (! in_array($column, $ignored, true)) {
                    $issues[] = sprintf(
                        'The [%s] column of [%s] holds data in %d row(s) that the %s layout cannot store. Move it first, or pass --ignore-columns=%s to accept losing it in the target.',
                        $column,
                        $source->table($model),
                        $rows,
                        $target->name(),
                        $column
                    );
                }
            }
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|null
     */
    private function resumable(Journal $journal, string $class, string $from, string $to, array $options): ?array
    {
        $id = $options['migration_id'] ?? null;

        if (is_string($id) && $id !== '') {
            $state = $journal->load($id);

            if ($state === null || ($state['model'] ?? null) !== $class || ($state['from'] ?? null) !== $from || ($state['to'] ?? null) !== $to) {
                throw new InvalidArgumentException("No resumable migration [{$id}] of [{$class}] from {$from} to {$to}.");
            }

            return $state;
        }

        return ($options['resume'] ?? false) ? $journal->latestRunning($class, $from, $to) : null;
    }

    /**
     * @param  list<string>|null  $fields
     * @return list<string>
     */
    private function fields(Model $model, ?array $fields): array
    {
        $all = $this->definitions->for($model)->names();

        if ($fields === null || $fields === []) {
            return $all;
        }

        foreach ($fields as $field) {
            if (! in_array($field, $all, true)) {
                throw new InvalidArgumentException("[{$field}] is not a translatable field of [".get_class($model).'].');
            }
        }

        return array_values(array_intersect($all, $fields));
    }

    /**
     * @return list<int|string>
     */
    private function keys(Model $model, mixed $after, int $chunk, bool $withoutScopes): array
    {
        $query = $withoutScopes ? $model->newModelQuery() : $model->newQuery();

        if (! $query instanceof Builder) {
            throw new InvalidArgumentException('The records of ['.get_class($model).'] cannot be queried.');
        }

        // Soft-deleted records keep translations too; other global scopes (a tenant) stay applied.
        if (! $withoutScopes && method_exists($model, 'bootSoftDeletes')) {
            $query->withoutGlobalScope('Illuminate\\Database\\Eloquent\\SoftDeletingScope');
        }

        if ($after !== null) {
            $query->where($model->getQualifiedKeyName(), '>', $after);
        }

        $keys = [];

        try {
            foreach ($query->orderBy($model->getQualifiedKeyName())->limit($chunk)->pluck($model->getQualifiedKeyName()) as $key) {
                if (is_int($key) || is_string($key)) {
                    $keys[] = $key;
                }
            }
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('The records of ['.get_class($model).'] cannot be listed: '.$exception->getMessage(), 0, $exception);
        }

        return $keys;
    }
}
