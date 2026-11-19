<?php

namespace Pharaonic\Laravel\Translatable\Console;

use Illuminate\Console\Command;
use Pharaonic\Laravel\Translatable\Console\Concerns\InteractsWithTranslatableModels;
use Pharaonic\Laravel\Translatable\Migration\Migrator;
use Pharaonic\Laravel\Translatable\Migration\TableAdapter;
use Pharaonic\Laravel\Translatable\Resolution\DefinitionResolver;
use Pharaonic\Laravel\Translatable\Resolution\StrategyResolver;
use Throwable;

class InspectTranslationsCommand extends Command
{
    use InteractsWithTranslatableModels;

    /**
     * @var string
     */
    protected $signature = 'translatable:inspect
        {model : The translatable model class}
        {--json : Print the report as JSON}';

    /**
     * @var string
     */
    protected $description = 'Show the strategy, fields and storage of a translatable model, and which layouts hold data';

    public function handle(StrategyResolver $strategies, DefinitionResolver $definitions, Migrator $migrator): int
    {
        try {
            $model = $this->translatableModel();
            $strategy = $strategies->name($model);
            $source = $strategies->source($model);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        $fields = [];

        foreach ($definitions->for($model)->all() as $field => $definition) {
            $fields[$field] = ['type' => $definition->type ?? 'untyped', 'required' => $definition->required];
        }

        $layouts = [];

        foreach (StrategyResolver::STRATEGIES as $name) {
            $adapter = $migrator->adapter($name);
            $issues = $adapter->preflight($model, $name !== $strategy);
            $records = null;

            if ($issues === []) {
                try {
                    $keys = [];

                    foreach ($model->getConnection()->table($model->getTable())->pluck($model->getKeyName()) as $key) {
                        if (is_int($key) || is_string($key)) {
                            $keys[] = $key;
                        }
                    }

                    $records = 0;

                    for ($offset = 0; $offset < count($keys); $offset += 500) {
                        $records += count($adapter->read($model, array_slice($keys, $offset, 500)));
                    }
                } catch (Throwable $exception) {
                    $issues[] = $exception->getMessage();
                }
            }

            $layouts[$name] = [
                'active' => $name === $strategy,
                'schema' => $issues === [] ? 'ok' : 'missing',
                'records_with_data' => $records,
                'issues' => $issues,
                'extra_columns' => $adapter instanceof TableAdapter && $issues === [] ? $adapter->extraColumns($model) : [],
            ];
        }

        $warnings = [];

        if (($layouts[$strategy]['records_with_data'] ?? 0) === 0) {
            foreach ($layouts as $name => $layout) {
                if ($name !== $strategy && ($layout['records_with_data'] ?? 0) > 0) {
                    $warnings[] = "The active {$strategy} layout holds no translations but the {$name} layout does. Run translatable:migrate --from={$name} --to={$strategy}, or keep the {$name} strategy until you do.";
                }
            }
        }

        $report = [
            'model' => get_class($model),
            'table' => $model->getTable(),
            'connection' => $model->getConnectionName() ?? $model->getConnection()->getName(),
            'key' => ['name' => $model->getKeyName(), 'type' => $model->getKeyType()],
            'strategy' => $strategy,
            'strategy_source' => $source,
            'fields' => $fields,
            'layouts' => $layouts,
            'warnings' => $warnings,
        ];

        if ($this->option('json') === true) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info("{$report['model']} ({$report['table']}, connection {$report['connection']})");
            $this->line("Strategy: {$strategy} (from {$source})");
            $this->table(['Field', 'Type', 'Required'], array_map(fn (string $field, array $definition): array => [$field, $definition['type'], $definition['required'] ? 'yes' : 'no'], array_keys($fields), $fields));
            $this->table(['Layout', 'Active', 'Schema', 'Records with data'], array_map(fn (string $name, array $layout): array => [$name, $layout['active'] ? 'yes' : '', $layout['schema'], $layout['records_with_data'] ?? '-'], array_keys($layouts), $layouts));

            foreach ($warnings as $warning) {
                $this->warn($warning);
            }
        }

        return $warnings === [] && $layouts[$strategy]['issues'] === [] ? 0 : 1;
    }
}
