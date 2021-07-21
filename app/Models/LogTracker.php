<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogTracker extends Model
{
    public $timestamps = false;
    protected $table = 'log_tracker';
    protected $primaryKey = 'track_id';
    protected $fillable = [
        'module_id',
        'action',
        'prim_id',
        'staff_id',
        'date_time',
        'comments',
        'hotel_id',
        'type'
    ];
    protected $hidden = [];
}
