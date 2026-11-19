<?php

namespace Pharaonic\Laravel\Translatable\Console\Concerns;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Pharaonic\Laravel\Translatable\Support\Locales;

/**
 * @internal
 */
trait ReadsLocales
{
    /**
     * The --locales option, else app.locale and app.fallback_locale.
     *
     * @return list<string>
     */
    protected function localesOption(): array
    {
        $option = $this->option('locales');
        $locales = is_string($option) && trim($option) !== ''
            ? array_map('trim', explode(',', $option))
            : [Config::get('app.locale'), Config::get('app.fallback_locale')];

        $valid = [];

        foreach ($locales as $locale) {
            if (! is_string($locale) || $locale === '') {
                continue;
            }

            if (! Locales::isWellFormed($locale)) {
                throw new InvalidArgumentException("\"{$locale}\" is not a valid locale.");
            }

            $valid[$locale] = true;
        }

        if ($valid === []) {
            throw new InvalidArgumentException('Pass the locales with --locales=en,ar.');
        }

        return array_map('strval', array_keys($valid));
    }
}
