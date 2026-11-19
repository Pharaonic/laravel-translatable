<?php

namespace Pharaonic\Laravel\Translatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class UuidNoteTranslation extends Model
{
    /**
     * @var string
     */
    protected $table = 'uuid_note_translations';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
