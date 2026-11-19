<?php

namespace Pharaonic\Laravel\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Translatable\Translatable;

/**
 * A UUID key and soft deletes. Without a marker it uses the default (table)
 * strategy; the subclasses use the json and columns strategies on the same table.
 */
class UuidNote extends Model
{
    use SoftDeletes;
    use Translatable;

    /**
     * @var string
     */
    protected $table = 'uuid_notes';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var array<int, string>
     */
    protected $translatables = ['title'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Model $model) {
            if ($model->getKey() === null) {
                $model->setAttribute($model->getKeyName(), Str::uuid()->toString());
            }
        });
    }

    /**
     * @return string
     */
    public function getTranslatableModel()
    {
        return UuidNoteTranslation::class;
    }

    /**
     * @return string
     */
    public function getTranslatableField()
    {
        return 'uuid_note_id';
    }
}
