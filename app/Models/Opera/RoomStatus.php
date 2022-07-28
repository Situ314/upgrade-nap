<?php

namespace App\Models\Opera;

use Illuminate\Database\Eloquent\Model;

class RoomStatus extends Model
{
    protected $connection = 'opera';

    public $timestamps = false;

    protected $table = 'RoomStatus';

    protected $primaryKey = 'ID';

    protected $fillable = [
        'ResortID',
        'OldRoomNO',
        'NewRoomNO',
        'OldRoomStatus',
        'NewRoomStatus',
        'OldRoomType',
        'NewRoomType',
    ];

    protected $hidden = [];
}
