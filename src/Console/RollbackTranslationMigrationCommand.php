<?php

namespace Pharaonic\Laravel\Translatable\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Pharaonic\Laravel\Translatable\Console\Concerns\InteractsWithTranslatableModels;
use Pharaonic\Laravel\Translatable\Migration\Migrator;

class RollbackTranslationMigrationCommand extends Command
{
    use InteractsWithTranslatableModels;

    /**
     * @var string
     */
    protected $signature = 'translatable:rollback
        {model : The translatable model class}
        {--migration-id= : The migration to undo}
        {--apply : Write the target back; without it the command only reports}
        {--dry-run : Report only (the default)}
        {--json : Print the report as JSON}';

    /**
     * @var string
     */
    protected $description = 'Restore the target values a translatable:migrate run replaced';

    public function handle(Migrator $migrator): int
    {
        $id = $this->option('migration-id');

        if (! is_string($id) || $id === '') {
            $this->error('--migration-id is required.');

            return 1;
        }

        try {
            $report = $migrator->rollback($this->translatableModel(), $this->journal(), $id, $this->option('apply') === true && $this->option('dry-run') !== true);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        if ($report->status === 'dry-run') {
            $this->comment('Dry run: nothing was written. Run again with --apply to restore.');
        }

        $this->comment('Only the values in the journal are restored; target changes made after the migration are overwritten, and schema changes are not undone.');

        return $this->printReport($report);
    }
}
