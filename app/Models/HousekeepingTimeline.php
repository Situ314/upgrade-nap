<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HousekeepingTimeline extends Model
{
    public $timestamps = false;

    protected $table = 'housekeeping_timeline';

    protected $primaryKey = 'timeline_id';

    protected $fillable = [
        'item_id',
        'hotel_id',
        'action',
        'changed_field',
        'previous_value',
        'value',
        'is_active',
        'changed_by',
        'changed_on',
        'platform',
    ];
}
