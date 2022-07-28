<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    public $timestamps = false;

    protected $table = 'events';

    protected $primaryKey = 'event_id';

    protected $fillable = [
        'hotel_id',
        'guest_id',
        'issue',
        'room_id', //hotel_rooms
        'dept_tag_id',
        'status',
        'date',
        'priority',
        'time',
        'onhold',
        'created_by',
        'created_on',
        'closed_by',
        'closed_on',
        'updated_by',
        'updated_on',
        'deleted_by',
        'deleted_on',
        'owner',
        'pending_on',
        'pending_by',
        'completed_on',
        'completed_by',
        'active',
        'is_guest',
        'is_contact',
        'is_recurring',
        'recurring_from',
        'recurring_to',
        'recurring_no_of_days',
        'recurring_time',
        'recurring_months',
        'recurring_weeks',
        'recurring_dates',
        'recurring_status',
        'second_notification_stat',
        'third_notification_stat',
        'child_recurr',
        'count_by_hotel_id',
        'in_shift',
        'cancel_by_guest',
        'requested_by',
    ];

    protected $hidden = [
        'dept_tag_id',
        'room_id',
        'hotel_id',
        'event_id',
        'guest_id',
        // 'date',
        'priority',
        // 'time',
        'onhold',
        'created_by',
        'created_on',
        'closed_by',
        'closed_on',
        'updated_by',
        'updated_on',
        'deleted_by',
        'deleted_on',
        'owner',
        'pending_on',
        'pending_by',
        //'completed_on',
        'completed_by',
        'active',
        'is_guest',
        'is_contact',
        'is_recurring',
        'recurring_from',
        'recurring_to',
        'recurring_no_of_days',
        'recurring_time',
        'recurring_months',
        'recurring_weeks',
        'recurring_dates',
        'recurring_status',
        'second_notification_stat',
        'third_notification_stat',
        'child_recurr',
        'count_by_hotel_id',
        'in_shift',
        'cancel_by_guest',
        'requested_by',
    ];

    public function Guest()
    {
        return $this->hasOne(\App\Models\GuestRegistration::class, 'guest_id', 'guest_id');
    }

    public function Room()
    {
        return $this->hasOne(\App\Models\HotelRoom::class, 'room_id', 'room_id');
    }

    public function DepTag()
    {
        return $this->hasOne(\App\Models\DeptTag::class, 'dept_tag_id', 'dept_tag_id');
    }

    public function StaffCompleted()
    {
        return $this->hasOne(\App\Models\Staff::class, 'staff_id', 'completed_by');
    }
}
