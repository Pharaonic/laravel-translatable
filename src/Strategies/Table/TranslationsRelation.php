<?php

namespace Pharaonic\Laravel\Translatable\Strategies\Table;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Pharaonic\Laravel\Translatable\Strategies\TableStrategy;

/**
 * The translations relation of the table strategy. Eager loading it records
 * on each model which locales it loaded (all of them, or some), so a read of
 * a locale outside a limited load loads the rest instead of reading it as missing.
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
            if ($model instanceof Model) {
                TableStrategy::markLoadedLocales($model, $this->locales);
                $matched[$key] = $model;
            }
        }

        return $matched;
    }
}
