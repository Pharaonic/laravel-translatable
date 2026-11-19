<?php

namespace Pharaonic\Laravel\Translatable\Strategies\Table;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The translations relation of the table strategy. When it is eager loaded for
 * some locales only, each model remembers which ones, so a read of another
 * locale loads the rest instead of reading it as missing.
 *
 * @internal
 */
class TranslationsRelation extends HasMany
{
    /** @var list<string>|null */
    private ?array $locales = null;

    /**
     * @param  list<string>  $locales
     * @return $this
     */
    public function onlyLocales(array $locales, string $column): static
    {
        $this->locales = $locales;
        $this->getQuery()->whereIn($this->getRelated()->qualifyColumn($column), $locales);

        return $this;
    }

    /**
     * @param  array<int, Model>  $models
     * @param  Collection<array-key, Model>  $results
     * @param  string  $relation
     * @return array<array-key, Model>
     */
    public function match(array $models, Collection $results, $relation)
    {
        $matched = [];

        foreach (parent::match($models, $results, $relation) as $key => $model) {
            if (! $model instanceof Model) {
                continue;
            }

            if ($this->locales !== null) {
                $loaded = $model->getRelation($relation);
                $records = $loaded instanceof Collection ? array_filter($loaded->all(), fn ($record): bool => $record instanceof Model) : [];
                $model->setRelation($relation, new PartialTranslations($records, $this->locales));
            }

            $matched[$key] = $model;
        }

        return $matched;
    }
}
