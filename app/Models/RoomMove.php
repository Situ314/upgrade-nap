<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomMove extends Model
{
    public $timestamps = false;
    protected $table = 'room_moves';
    protected $primaryKey = 'rm_id';
    protected $fillable = [
        'guest_id',
        'phone',
        'current_room_no',
        'new_room_no',
        'comment',
        'hotel_id',
        'created_by',
        'created_on',
        'status',
        'completed_on',
        'updated_on',
        'updated_by',
        'active'
    ];
    protected $hidden = [];
}
