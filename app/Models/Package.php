<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    public $timestamps = false;
    protected $table = 'packages';
    protected $primaryKey = 'pkg_no';
    protected $fillable = [
        'hotel_id',
        'created_on',
        'created_by',
        'guest_id',
        'phone_no',
        'item_name',
        'room_id',
        'comment',
        'status',
        'confirmed_by',
        'signature_type',
        'delivered_by',
        'delivered_on',
        'updated_on',
        'updated_by',
        'confirmed_person',
        'reference_number',
        'active',
        'consecutive',
        'delivered_name',
        'filename',
        'courier'
    ];
    protected $hidden = [
        'hotel_id',
        'created_on',
        'created_by',
        'status',
        'confirmed_by',
        'signature_type',
        'delivered_by',
        'delivered_on',
        'updated_on',
        'updated_by',
        'confirmed_person',
        'reference_number',
        'active',
        'consecutive',
        'delivered_name',
        'filename',
        'courier'
    ];
}
