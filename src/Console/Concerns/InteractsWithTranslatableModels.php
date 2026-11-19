<?php

namespace Pharaonic\Laravel\Translatable\Console\Concerns;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Pharaonic\Laravel\Translatable\Data\MigrationReport;
use Pharaonic\Laravel\Translatable\Migration\Journal;
use Pharaonic\Laravel\Translatable\Resolution\StrategyResolver;

/**
 * @internal
 */
trait InteractsWithTranslatableModels
{
    protected function translatableModel(): Model
    {
        $class = $this->argument('model');

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            throw new InvalidArgumentException('The model argument must be an Eloquent model class, for example "App\\Models\\Post".');
        }

        $model = new $class;

        if (! method_exists($model, 'translationPatch')) {
            throw new InvalidArgumentException("[{$class}] does not use the Translatable trait.");
        }

        return $model;
    }

    protected function layout(string $option): string
    {
        $value = $this->option($option);

        if (! is_string($value) || ! in_array($value, StrategyResolver::STRATEGIES, true)) {
            throw new InvalidArgumentException(sprintf('--%s must be one of %s.', $option, implode(', ', StrategyResolver::STRATEGIES)));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    protected function listOption(string $option): array
    {
        $value = $this->option($option);

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $item): bool => $item !== ''));
    }

    protected function journal(): Journal
    {
        return new Journal($this->laravel->storagePath().DIRECTORY_SEPARATOR.'translatable'.DIRECTORY_SEPARATOR.'migrations');
    }

    protected function printReport(MigrationReport $report): int
    {
        if ($this->option('json') === true) {
            $this->line((string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['', ''], [
                ['Model', $report->model],
                ['From → to', $report->from.' → '.$report->to],
                ['Status', $report->status],
                ['Migration id', $report->migrationId ?? '-'],
                ['Records', (string) $report->records],
                ['Values', (string) $report->values],
                ['Changed', (string) $report->changed],
                ['Mismatches', (string) $report->mismatches],
            ]);

            foreach ($report->issues as $issue) {
                $this->error($issue);
            }

            foreach ($report->samples as $sample) {
                $this->line((string) json_encode($sample, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        return $report->successful() ? 0 : 1;
    }
}
