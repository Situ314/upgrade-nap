<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HousekeepingPreferences extends Model
{
    public $timestamps = false;
    protected $table = 'housekeeping_preferences';
    protected $primaryKey = 'hk_preferences_id';
    
    protected $fillable = [
        'hotel_id',
        'am_shift_start',
        'am_shift_end',
        'pm_shift_enabled',
        'pm_shift_start',
        'pm_shift_end',
        'inspected_to_clean_hours',
        'vacant_clean_to_dirty_hours',
        'default_cleaning_order',
        'default_hkstatus_order',
        'credits_limit',
        'credits_limit_old_value',
        'dnd_send_sms',
        'pending_coming',
        'clean_any_order',
        'show_oclock',
        'is_active',
        'created_by',
        'created_on',
        'updated_by',
        'updated_on',
        'sync_last_update'
    ];

    protected $hidden = [
    ];
}
