<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menus extends Model
{
    public $timestamps = false;
    protected $table = 'menus';
    protected $primaryKey = 'menu_id';
    protected $fillable = [
        'menu_name'
    ];
    protected $hidden = [];
}
