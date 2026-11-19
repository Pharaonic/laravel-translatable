<?php

namespace Pharaonic\Laravel\Translatable\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Pharaonic\Laravel\Translatable\Console\Concerns\InteractsWithTranslatableModels;
use Pharaonic\Laravel\Translatable\Migration\Migrator;

class MigrateTranslationsCommand extends Command
{
    use InteractsWithTranslatableModels;

    /**
     * @var string
     */
    protected $signature = 'translatable:migrate
        {model : The translatable model class}
        {--from= : The layout to copy from: table, json or columns}
        {--to= : The layout to copy to}
        {--apply : Write the target; without it the command only reports}
        {--dry-run : Report only (the default)}
        {--chunk=500 : Records per transaction}
        {--resume : Continue the latest unfinished migration of the model between the two layouts}
        {--migration-id= : Continue this migration}
        {--fields= : Comma-separated fields to copy (partial backfill)}
        {--ignore-columns= : Comma-separated extra translation-table columns whose data may stay behind}
        {--without-scopes : Include records hidden by global scopes}
        {--json : Print the report as JSON}';

    /**
     * @var string
     */
    protected $description = 'Copy translations from one storage layout to another, without changing the source';

    public function handle(Migrator $migrator): int
    {
        try {
            $model = $this->translatableModel();
            $apply = $this->option('apply') === true && $this->option('dry-run') !== true;
            $chunk = $this->option('chunk');

            $report = $migrator->migrate($model, $this->layout('from'), $this->layout('to'), $this->journal(), [
                'apply' => $apply,
                'chunk' => is_numeric($chunk) ? (int) $chunk : 500,
                'resume' => $this->option('resume') === true,
                'migration_id' => is_string($this->option('migration-id')) ? $this->option('migration-id') : null,
                'fields' => $this->listOption('fields'),
                'ignore_columns' => $this->listOption('ignore-columns'),
                'without_scopes' => $this->option('without-scopes') === true,
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        if (! $apply && $report->status === 'dry-run') {
            $this->comment('Dry run: nothing was written. Run again with --apply to copy.');
        }

        return $this->printReport($report);
    }
}
