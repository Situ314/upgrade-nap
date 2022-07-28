<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceRecordsItems extends Model
{
    public $timestamps = false;

    protected $table = 'maintenance_records_items';

    protected $primaryKey = 'maintenance_record_item_id';

    protected $fillable = [

        'maintenance_record_id',
        'item_id',

        'hotel_id',
        'is_active',
        'created_on',
        'created_by',
        'updated_on',
        'updated_by',
        'deleted_on',
        'deleted_by',
    ];

    protected $hidden = [
    ];
}
