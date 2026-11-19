<?php

namespace Pharaonic\Laravel\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Contracts\ColumnsTranslatable;
use Pharaonic\Laravel\Translatable\Translatable;

class Category extends Model implements ColumnsTranslatable
{
    use Translatable;

    /**
     * @var array<int, string>
     */
    protected $fillable = ['name', 'summary'];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function translatable(): array
    {
        return [
            'name' => ['type' => 'string', 'required' => true],
            'summary' => ['type' => 'string'],
        ];
    }
}
