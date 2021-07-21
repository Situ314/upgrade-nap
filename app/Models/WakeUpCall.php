<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WakeUpCall extends Model
{
    public $timestamps = false;
    protected $table = 'wake_up_calls';
    protected $primaryKey = 'wup_id';
    protected $fillable = [
        'guest_id',
        'phone',
        'room_no',
        'wtime',
        'comment',
        'hotel_id',
        'created_by',
        'created_on',
        'status',
        'completed_on',
        'updated_by',
        'updated_on',
        'active',
        'target'
    ];
    protected $hidden = [];
}