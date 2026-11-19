<?php

namespace Pharaonic\Laravel\Translatable\Exceptions;

use LogicException;

final class ConflictingTranslationStrategiesException extends LogicException implements TranslatableException
{
    /**
     * @param  list<string>  $markers
     */
    public static function forModel(string $model, array $markers): self
    {
        return new self(sprintf(
            '[%s] implements more than one translation strategy marker: %s. Implement exactly one, or none to use config("translatable.strategy").',
            $model,
            implode(', ', $markers)
        ));
    }
}
