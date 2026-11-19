<?php

namespace Pharaonic\Laravel\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Translatable;

class Post extends Model
{
    use Translatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['published', 'locale'];

    /**
     * Translatable attributes names.
     *
     * @var array<int, string>
     */
    protected $translatables = ['title', 'content', 'description', 'keywords'];

    /**
     * Casting attributes
     *
     * @var array<string, string>
     */
    protected $casts = ['published' => 'boolean'];
}
