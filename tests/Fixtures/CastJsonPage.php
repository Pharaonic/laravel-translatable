<?php

namespace Pharaonic\Laravel\Translatable\Tests\Fixtures;

/**
 * A json-strategy model whose translated columns also carry the casts an
 * application often adds by habit.
 */
class CastJsonPage extends JsonPage
{
    /**
     * @var string
     */
    protected $table = 'json_pages';

    /**
     * @var array<string, string>
     */
    protected $casts = ['title' => 'array', 'summary' => 'json', 'rank' => 'array'];
}
