<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffHotel extends Model
{
    public $timestamps = false;
    protected $table = 'staff_hotels';
    protected $primaryKey = 'sno';
    protected $fillable = [
        'staff_id',
        'hotel_id',
        'role_id',
        'department_id',
        'tag_id',
        'shift_id',
        'is_active',
        'created_on',
        'created_by',
        'updated_on',
        'updated_by',
        'mod_report',
        'high_priority',
        'recurring',
        'inactivity',
        'pending_report',
        'completed_report',
        'closed_report',
        'daily_report',
        'weekly_report',
        'monthly_report',
        'wake_up_calls',
        'on_hold',
        'refresh_dashboard',
        'beta_hotel',
        'update_beta_by',
        'chat_angel',
        'passon_reports',
        'pending_report_sms',
        'completed_report_sms',
        'closed_report_sms'
    ];
    protected $hidden = [
   
        'staff_id',
        'hotel_id',
        'role_id',        
        'tag_id',
        'shift_id',
        'is_active',
        'created_on',
        'created_by',
        'updated_on',
        'updated_by',
        'mod_report',
        'high_priority',
        'recurring',
        'inactivity',
        'pending_report',
        'completed_report',
        'closed_report',
        'daily_report',
        'weekly_report',
        'monthly_report',
        'wake_up_calls',
        'on_hold',
        'refresh_dashboard',
        'beta_hotel',
        'update_beta_by',
        'chat_angel',
        'passon_reports',
        'pending_report_sms',
        'completed_report_sms',
        'closed_report_sms'
    ];

    public function role()
    {
        return $this->hasOne('App\Models\Role','role_id','role_id');
    }
}
