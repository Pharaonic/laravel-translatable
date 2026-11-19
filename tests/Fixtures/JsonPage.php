<?php

namespace Pharaonic\Laravel\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Contracts\JsonTranslatable;
use Pharaonic\Laravel\Translatable\Translatable;

class JsonPage extends Model implements JsonTranslatable
{
    use Translatable;

    /**
     * @var array<int, string>
     */
    protected $fillable = ['slug', 'title', 'summary', 'rank', 'locale'];

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
