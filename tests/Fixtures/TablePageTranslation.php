<?php

namespace Pharaonic\Laravel\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TablePageTranslation extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
