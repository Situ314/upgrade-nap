<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsChat extends Model
{
    public $timestamps = false;

    protected $table = 'sms_chat';

    protected $primaryKey = 'sms_chat_id';

    protected $fillable = ['hotel_id', 'room_no', 'phone_no', 'guest_id', 'staff_id', 'text', 'img_sms', 'type', 'created_on', 'read', 'read_on', 'closed', 'parent_id'];
    // protected $hidden = [];
}
