<?php

namespace Pharaonic\Laravel\Translatable\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Pharaonic\Laravel\Translatable\Console\Concerns\InteractsWithTranslatableModels;
use Pharaonic\Laravel\Translatable\Migration\Migrator;

class VerifyTranslationsCommand extends Command
{
    use InteractsWithTranslatableModels;

    /**
     * @var string
     */
    protected $signature = 'translatable:verify
        {model : The translatable model class}
        {--from= : The source layout}
        {--to= : The target layout}
        {--fields= : Comma-separated fields to compare}
        {--chunk=500 : Records per read}
        {--without-scopes : Include records hidden by global scopes}
        {--json : Print the report as JSON}';

    /**
     * @var string
     */
    protected $description = 'Compare every translated value of two storage layouts';

    public function handle(Migrator $migrator): int
    {
        try {
            $chunk = $this->option('chunk');
            $report = $migrator->verify($this->translatableModel(), $this->layout('from'), $this->layout('to'), [
                'fields' => $this->listOption('fields'),
                'chunk' => is_numeric($chunk) ? (int) $chunk : 500,
                'without_scopes' => $this->option('without-scopes') === true,
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        return $this->printReport($report);
    }
}
