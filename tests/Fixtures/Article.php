<?php

namespace Pharaonic\Laravel\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Contracts\JsonTranslatable;
use Pharaonic\Laravel\Translatable\Translatable;

class Article extends Model implements JsonTranslatable
{
    use Translatable;

    /**
     * @var array<int, string>
     */
    protected $fillable = ['title', 'body', 'views', 'translations_payload'];

    /**
     * @var string
     */
    protected $translationsKey = 'translations_payload';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function translatable(): array
    {
        return [
            'title' => ['type' => 'string', 'required' => true],
            'body' => ['type' => 'string'],
            'views' => ['type' => 'int'],
        ];
    }
}
