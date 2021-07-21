<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeptTag extends Model
{
    public $timestamps = false;
    protected $table = 'dept_tag';
    protected $primaryKey = 'dept_tag_id';
    protected $fillable = [
        'hotel_id',
        'dept_id',
        'tag_id',
        'is_active',
        'first_email_notification',
        'second_email_notification',
        'third_email_notification',
        'first_tag_time',
        'second_tag_time',
        'dept_tag_id_32',
        'sms_first_notification',
        'first_indicative',
        'second_indicative',
        'third_indicative',
        'first_number_phone',
        'second_number_phone',
        'third_number_phone',
        'first_push',
        'second_push',
        'third_push',
        'third_user',
        'second_user',
        'first_user',
        'category',
        'time_ini',
        'time_fin',
        'status',
        'code',
        'id_staff_autoassign',
        'name_staff_autoassign'
    ];
    protected $hidden = [
        'hotel_id',
        'dept_id',
        'tag_id',
        'is_active',
        'first_email_notification',
        'second_email_notification',
        'third_email_notification',
        // 'first_tag_time',
        // 'second_tag_time',
        'dept_tag_id_32',
        'sms_first_notification',
        'first_indicative',
        'second_indicative',
        'third_indicative',
        'first_number_phone',
        'second_number_phone',
        'third_number_phone',
        'first_push',
        'second_push',
        'third_push',
        'third_user',
        'second_user',
        'first_user',
        'category',
        'time_ini',
        'time_fin',
        'status',
        'code',
        'id_staff_autoassign',
        'name_staff_autoassign'
    ];

    public function departament()
    {
        return $this->hasOne('App\Models\Departament','dept_id','dept_id');
    }

    public function tag(){
        return $this->hasOne('App\Models\Tag', 'tag_id', 'tag_id');
    }


}
