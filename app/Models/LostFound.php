<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LostFound extends Model
{
    public $timestamps = false;
    protected $table = 'lost_found';
    protected $primaryKey = 'lst_fnd_no';
    protected $fillable = [
        'hotel_id',
        'guest_id',
        'phone_no',
        'item_name',
        'room_id',
        'status',
        'comment',
        'created_on',
        'created_by',
        'confirmed_by',
        'signature_type',
        'delivered_by',
        'updated_on',
        'delivered_on',
        'updated_by',
        'delivered_person',
        'reference_number',
        'confirmed_person',
        'active',
        'consecutive',
        'delivered_name',
        'filename'
    ];
    protected $hidden = [
        'hotel_id',
        'status',
        'created_on',
        'created_by',
        'confirmed_by',
        'signature_type',
        'delivered_by',
        'updated_on',
        'delivered_on',
        'updated_by',
        'delivered_person',
        'reference_number',
        'confirmed_person',
        'active',
        'consecutive',
        'delivered_name',
        'filename'
    ];
}
