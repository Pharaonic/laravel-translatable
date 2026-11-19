<?php

namespace Pharaonic\Laravel\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Contracts\ColumnsTranslatable;
use Pharaonic\Laravel\Translatable\Integrations\Sluggable\HasTranslatedSlugs;
use Pharaonic\Laravel\Translatable\Translatable;

class SluggedColumnsPost extends Model implements ColumnsTranslatable
{
    use HasTranslatedSlugs;
    use Translatable;

    /**
     * @var string
     */
    protected $table = 'slugged_columns_posts';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function translatable(): array
    {
        return [
            'title' => ['type' => 'string', 'required' => true],
            'description' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function translatedSlugs(): array
    {
        return ['slug' => 'title'];
    }

    /**
     * @return string
     */
    public function getTranslatableField()
    {
        return 'post_id';
    }

    /**
     * @return string
     */
    public function getTranslatableModel()
    {
        return SluggedPostTranslation::class;
    }
}
