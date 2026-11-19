<?php

namespace Pharaonic\Laravel\Translatable\Migration;

use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Checkpoints and pre-migration target values of each migration, as files
 * under one directory: {id}.json (state) and {id}.previous.jsonl (one line per
 * written entity, its target values before the write).
 *
 * @internal
 */
final class Journal
{
    public function __construct(private string $directory) {}

    /**
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public function start(string $model, string $from, string $to, array $fields): array
    {
        $state = [
            'id' => date('YmdHis').'_'.Str::random(8),
            'model' => $model,
            'from' => $from,
            'to' => $to,
            'fields' => $fields,
            'status' => 'running',
            'last_key' => null,
            'processed' => 0,
            'started_at' => date(DATE_ATOM),
            'finished_at' => null,
        ];

        $this->save($state);

        return $state;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $id): ?array
    {
        $path = $this->path($id, '.json');

        if (! is_file($path)) {
            return null;
        }

        return self::state(json_decode((string) file_get_contents($path), true));
    }

    /**
     * The latest unfinished migration of the model between the two layouts.
     *
     * @return array<string, mixed>|null
     */
    public function latestRunning(string $model, string $from, string $to): ?array
    {
        $files = glob($this->directory.DIRECTORY_SEPARATOR.'*.json') ?: [];
        rsort($files);

        foreach ($files as $file) {
            $state = self::state(json_decode((string) file_get_contents($file), true));

            if ($state !== null && ($state['model'] ?? null) === $model && ($state['from'] ?? null) === $from && ($state['to'] ?? null) === $to && ($state['status'] ?? null) === 'running') {
                return $state;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function save(array $state): void
    {
        $id = $state['id'] ?? null;

        if (! is_string($id)) {
            throw new InvalidArgumentException('A journal state needs an id.');
        }

        $this->write($this->path($id, '.json'), (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), false);
    }

    /**
     * @param  array<string, array<string, mixed>>  $values
     */
    public function remember(string $id, int|string $key, array $values): void
    {
        $line = json_encode(['key' => $key, 'values' => $values], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        $this->write($this->path($id, '.previous.jsonl'), $line."\n", true);
    }

    /**
     * Pre-migration target values; for an entity written twice (a resumed run), the first ones.
     *
     * @return array<int|string, array<string, array<string, mixed>>>
     */
    public function previous(string $id): array
    {
        $path = $this->path($id, '.previous.jsonl');
        $previous = [];

        if (! is_file($path)) {
            return $previous;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $entry = json_decode($line, true);

            if (! is_array($entry) || ! is_array($entry['values'] ?? null)) {
                continue;
            }

            $key = $entry['key'] ?? null;

            if ((! is_int($key) && ! is_string($key)) || array_key_exists($key, $previous)) {
                continue;
            }

            $values = [];

            foreach ($entry['values'] as $field => $locales) {
                if (! is_string($field) || ! is_array($locales)) {
                    continue;
                }

                foreach ($locales as $locale => $value) {
                    $values[$field][(string) $locale] = $value;
                }
            }

            $previous[$key] = $values;
        }

        return $previous;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function state(mixed $decoded): ?array
    {
        if (! is_array($decoded)) {
            return null;
        }

        $state = [];

        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $state[$key] = $value;
            }
        }

        return $state;
    }

    private function path(string $id, string $suffix): string
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $id) !== 1) {
            throw new InvalidArgumentException("Invalid migration id [{$id}].");
        }

        return $this->directory.DIRECTORY_SEPARATOR.$id.$suffix;
    }

    private function write(string $path, string $contents, bool $append): void
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0755, true) && ! is_dir($this->directory)) {
            throw new RuntimeException("The migration journal directory [{$this->directory}] cannot be created.");
        }

        if (file_put_contents($path, $contents, $append ? FILE_APPEND | LOCK_EX : LOCK_EX) === false) {
            throw new RuntimeException("The migration journal [{$path}] cannot be written.");
        }
    }
}
