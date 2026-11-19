<?php

namespace Pharaonic\Laravel\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Contracts\JsonTranslatable;
use Pharaonic\Laravel\Translatable\Translatable;

class TypedItem extends Model implements JsonTranslatable
{
    use Translatable;

    /**
     * @var string
     */
    protected $table = 'typed_items';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function translatable(): array
    {
        return [
            'flag' => ['type' => 'bool'],
            'tags' => ['type' => 'array'],
            'meta' => ['type' => 'object'],
            'price' => ['type' => 'float'],
            'released_on' => ['type' => 'date'],
            'starts_at' => ['type' => 'datetime'],
        ];
    }
}
