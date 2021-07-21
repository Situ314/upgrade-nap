<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HousekeepingStaff extends Model
{
    public $timestamps = false;
    protected $table = 'housekeeping_staff';
    protected $primaryKey = 'hk_staff_id';
    
    protected $fillable = [
        'hotel_id',
        'staff_id',
        'is_housekeeper',
        'is_active',
        'created_by',
        'created_on',
        'updated_by',
        'updated_on',
        'deleted_by',
        'deleted_on'
    ];

    protected $hidden = [
        'created_by',
        'created_on',
        'updated_by',
        'updated_on',
        'deleted_by',
        'deleted_on'
    ];
}
