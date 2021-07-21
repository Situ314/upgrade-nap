<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlexaIntentActiveInhotel extends Model
{
    public $timestamps = false;
    protected $table = 'alexa_intents_active_in_hotels';
    protected $fillable = [
        'alexa_intents_id',
        'is_active',
        'time',
        'tag_id',
        'hotel_id',
        'uses_dept_tag',
        'status'
    ];
    protected $hidden = [];
}