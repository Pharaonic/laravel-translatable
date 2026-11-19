<?php

namespace Pharaonic\Laravel\Translatable\Strategies\Table;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Translation records eager loaded for some locales only.
 *
 * @internal
 */
class PartialTranslations extends Collection
{
    /** @var list<string> */
    public array $loadedLocales;

    /**
     * @param  array<array-key, Model>  $items
     * @param  list<string>  $locales
     */
    public function __construct($items = [], array $locales = [])
    {
        parent::__construct($items);

        $this->loadedLocales = $locales;
    }

    public function covers(string $locale): bool
    {
        return in_array($locale, $this->loadedLocales, true);
    }
}
