<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceItems extends Model
{
    public $timestamps = false;
    protected $table = 'maintenance_items';
    protected $primaryKey = 'item_id';
    protected $fillable = [
        'hotel_id',
        'item_room_id',
        'type_item_id',
        'count_by_hotel_id',
        'datasheet_id',
        'name',
        'brand',
        'model',
        'supplier',
        'serial_number',
        'qr_code',
        'purchase_date',
        'guarantee',
        'status',
        'is_active',
        'created_on',
        'created_by',
        'updated_on',
        'updated_by',
        'deleted_on',
        'deleted_by'
    ];
    protected $hidden = [
        'created_on',
        'created_by',
        'updated_on',
        'updated_by',
        'deleted_on',
        'deleted_by'
    ];
}
