<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceRecords extends Model
{
    public $timestamps = false;

    protected $table = 'maintenance_records';

    protected $primaryKey = 'maintenance_records_id';

    protected $fillable = [
        'maintenance_type',             //null  -> 0: item, 1: location
        'val_acording',                 //null  -> no se utiliza, se guardaba el id de la habitacion
        'room_id',                      //null
        'start_date',                   //null
        'due_date',                     //null
        'assign_to',                    //null
        'assign_on',                    //null
        'type_notify',                  //null
        'comments',                     //null
        'status',                       //1
        'priority',                     //null
        'count_by_hotel_id',            //null
        'hotel_id',                     //null
        'is_active',                    //1
        'has_occurrence',               //null
        'occurrence_type',              //null
        'occurrence_params',            //null
        'ocurrence_remaining',          //null
        'occurrence_parent',            //null
        'start_maintenance_by',         //null
        'start_maintenance',            //null
        'pause_maintenance',            //null
        'duration_maintenance',         //0
        'start_status',                 //0
        'created_on',                   //null
        'created_by',                   //null
        'updated_on',                   //null
        'updated_by',                   //null
        'deleted_on',                   //null
        'deleted_by',                   //null
        'completed_on',                 //null
        'completed_by',                 //null
        'notify_start_mail_sent',       //0
        'notify_start_sms_sent',        //0
        'notify_delayed_mail_sent',     //0
        'notify_delayed_sms_sent',      //0
        'notify_completed_sms_sent',    //0
        'notify_completed_mail_sent',    //0
    ];

    protected $hidden = [
        'created_on',                   //null
        'created_by',                   //null
        'updated_on',                   //null
        'updated_by',                   //null
        'deleted_on',                   //null
        'deleted_by',                   //null
    ];

    public function assets()
    {
        //dd($this->belongsToMany('\App\Models\MaintenanceItems','maintenance_records_items','maintenance_record_id', 'item_id')->toSql());
        return $this->belongsToMany('\App\Models\MaintenanceItems', 'maintenance_records_items', 'maintenance_record_id', 'item_id'/*,'item_id','item_id'*/);
    }
}
