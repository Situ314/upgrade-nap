<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestRegistration extends Model
{
    public $timestamps = false;

    protected $table = 'guest_registration';

    protected $primaryKey = 'guest_id';

    protected $fillable = [
        'hotel_id',
        'firstname',
        'lastname',
        'email_address',
        'phone_no',
        'address',
        'state',
        'zipcode',
        'language',
        'comment',
        'angel_status',
        'city',
        'dob',              //default null
        'mobile_status',    //default 0
        'is_active',        //default 1
        'created_on',       // default null
        'created_by',       // default null
        'updated_on',       // default null
        'updated_by',       // default null
        'imported',         // default 0
        'id_device',        // default null
        'guest_tutorial',   // default 1
        'sms',              // default 1
        'custom_field_1',   // default null
        'custom_value_1',   // default null
        'custom_field_2',   // default null
        'custom_value_2',   // default null
        'custom_field_3',   // default null
        'custom_value_3',   // default null
        'custom_field_4',   // default null
        'custom_value_4',   // default null
        'custom_field_5',   // default null
        'custom_value_5',   // default null
        'custom_field_6',   // default null
        'custom_value_6',   // default null
        'custom_field_7',   // default null
        'custom_value_7',   // default null
        'custom_field_8',   // default null
        'custom_value_8',   // default null
        'custom_field_9',   // default null
        'custom_value_9',   // default null
        'custom_field_10',  // default null
        'custom_value_10',  // default null
        'category',         // default 0
        'pms_unique_id',    // default null
    ];

    protected $hidden = [
        'hotel_id',
        'state',
        'mobile_status',
        'is_active',
        'angel_status',
        'created_on',
        'created_by',
        'updated_on',
        'updated_by',
        'id_device',
        'custom_field_1', // default null
        'custom_value_1', // default null
        'custom_field_2', // default null
        'custom_value_2', // default null
        'custom_field_3', // default null
        'custom_value_3', // default null
        'custom_field_4', // default null
        'custom_value_4', // default null
        'custom_field_5', // default null
        'custom_value_5', // default null
        'custom_field_6', // default null
        'custom_value_6', // default null
        'custom_field_7', // default null
        'custom_value_7', // default null
        'custom_field_8', // default null
        'custom_value_8', // default null
        'custom_field_9', // default null
        'custom_value_9', // default null
        'custom_field_10', // default null
        'custom_value_10', // default null
    ];

    public function GuestCheckingDetail()
    {
        return $this->hasMany(\App\Models\GuestCheckinDetails::class, 'guest_id', 'guest_id');
    }

    public function Checking()
    {
        return $this->hasMany(\App\Models\GuestCheckinDetails::class, 'guest_id', 'guest_id');
    }

    /**
     * Usado en el controlador \v2\_1\GuestController
     */
    public function GuestCheckinDetails()
    {
        return $this->hasMany(\App\Models\GuestCheckinDetails::class, 'guest_id', 'guest_id');
    }

    /**
     * public function staff() { return $this->hasOne('App\User', 'staff_id', 'staff_id'); }
     * public function departament() { return $this->hasOne('App\Models\Departament','dept_id','dept_id'); }
     */
    public function IntegrationsGuestInformation()
    {
        return $this->hasOne(\App\Models\IntegrationsGuestInformation::class, 'guest_id', 'guest_id');
    }
}
