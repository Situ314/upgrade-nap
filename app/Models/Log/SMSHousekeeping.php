<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class SMSHousekeeping extends Model{
    protected $connection   = 'integrationsLogs';
    protected $table        = 'SMS_Housekeeping';
    public $timestamps      = false;
    protected $fillable     = [
        'location',
        'status',
        "hotel_id",
        "type",
        "created_at",
    ];
}