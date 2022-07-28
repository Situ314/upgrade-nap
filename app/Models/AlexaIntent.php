<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlexaIntent extends Model
{
    public $timestamps = false;

    protected $table = 'alexa_intents';

    protected $fillable = [
        'intent_name',
        'name',
        'description',
    ];

    protected $hidden = [];
}
