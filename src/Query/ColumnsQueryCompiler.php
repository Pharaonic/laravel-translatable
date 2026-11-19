<?php

namespace Pharaonic\Laravel\Translatable\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Resolution\DefinitionResolver;
use Pharaonic\Laravel\Translatable\Resolution\LocaleColumnMapper;

/**
 * Plain columns: title_en, title_ar.
 *
 * @internal
 */
final class ColumnsQueryCompiler extends RowQueryCompiler
{
    public function __construct(DefinitionResolver $definitions, private LocaleColumnMapper $columns)
    {
        parent::__construct($definitions);
    }

    protected function expression(Builder $query, Model $model, string $field, string $locale): string
    {
        return $this->wrap($query, $model->qualifyColumn($this->columns->localeColumn($model, $field, $locale)));
    }

    protected function locales(Model $model, array $locales): array
    {
        return $locales !== [] ? array_map([LocaleColumnMapper::class, 'suffix'], $locales) : $this->columns->locales($model);
    }
}
