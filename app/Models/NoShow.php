<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoShow extends Model
{
    public $timestamps = false;
    protected $table = 'no_shows';
    protected $primaryKey = 'noshow_id';
    protected $fillable = [
        'guest_id',     // null
        'phone',        // null
        'comment',      // null
        'created_by',
        'created_on',   // null
        'hotel_id',
        'updated_by',
        'updated_on',   // null
        'status',       // 0
        'completed_on', // null
        'active'        // 1
    ];
    protected $hidden = [
    ];
}
