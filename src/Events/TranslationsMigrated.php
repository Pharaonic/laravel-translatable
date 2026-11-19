<?php

namespace Pharaonic\Laravel\Translatable\Events;

/**
 * Dispatched once by translatable:migrate when a migration run finishes.
 */
final class TranslationsMigrated
{
    public function __construct(
        public string $model,
        public string $from,
        public string $to,
        public string $migrationId,
        public int $records,
    ) {
    }
}
