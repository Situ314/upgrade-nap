<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationSuitesRoom extends Model
{
    public $timestamps = false;
    protected $table = 'integration_suites_rooms';
    protected $primaryKey = 'integration_suite_id';
    protected $fillable = [
         'suite_id',
         'hotel_id',
         'room_id',
         'is_active',
         'created_at'
    ];

    protected $hidden = [];
}
