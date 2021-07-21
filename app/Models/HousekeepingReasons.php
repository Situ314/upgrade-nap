<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HousekeepingReasons extends Model
{
    public $timestamps = false;
    protected $table = 'housekeeping_reasons';
    protected $primaryKey = 'reason_id';
    
    protected $fillable = [
        'hotel_id',
        'reason_type',
        'reason',
        'is_default',
        'is_active',
        'created_by',
        'created_on',        
        'updated_by',
        'updated_on'
    ];

    protected $hidden = [
        'created_by',
        'created_on',        
        'updated_by',
        'updated_on'
    ];
}
