<?php

namespace Pharaonic\Laravel\Translatable\Data;

/**
 * The outcome of a migration, verification or rollback run.
 */
final class MigrationReport
{
    /**
     * @param  string  $status  "dry-run", "completed", "blocked", "verified", "mismatch" or "rolled-back"
     * @param  list<string>  $issues
     * @param  list<array<string, mixed>>  $samples
     */
    public function __construct(
        public string $model,
        public string $from,
        public string $to,
        public string $status,
        public ?string $migrationId = null,
        public int $records = 0,
        public int $values = 0,
        public int $changed = 0,
        public int $mismatches = 0,
        public array $issues = [],
        public array $samples = [],
    ) {}

    public function successful(): bool
    {
        return in_array($this->status, ['dry-run', 'completed', 'verified', 'rolled-back'], true) && $this->issues === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'from' => $this->from,
            'to' => $this->to,
            'status' => $this->status,
            'migrationId' => $this->migrationId,
            'records' => $this->records,
            'values' => $this->values,
            'changed' => $this->changed,
            'mismatches' => $this->mismatches,
            'issues' => $this->issues,
            'samples' => $this->samples,
        ];
    }
}
