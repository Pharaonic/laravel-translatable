<?php

namespace Pharaonic\Laravel\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Translatable;

/**
 * Its table holds all three layouts, and it has no marker, so tests choose
 * its strategy with config("translatable.strategy").
 */
class Book extends Model
{
    use Translatable;

    /**
     * @var array<int, string>
     */
    protected $fillable = ['isbn'];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function translatable(): array
    {
        return [
            'title' => ['type' => 'string', 'required' => true],
            'summary' => ['type' => 'string'],
            'rank' => ['type' => 'int'],
        ];
    }
}
