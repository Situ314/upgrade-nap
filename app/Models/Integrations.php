<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Integrations extends Model
{
    public $timestamps = false;
    protected $table = 'integrations';
    protected $fillable = [
        'name',
        'img',
        'released',
        'title',
        'have_config'
    ];
}