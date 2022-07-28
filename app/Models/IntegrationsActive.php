<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationsActive extends Model
{
    public $timestamps = false;

    protected $table = 'integrations_active';

    protected $casts = ['config' => 'json'];

    protected $fillable = [
        'int_id',
        'hotel_id',
        'config',
        'state',
        'created_by',
        'created_on',
        'updated_by',
        'updated_on',
        'deleted_by',
        'deleted_on',
        'pms_hotel_id',
        'sms_angel_active',
    ];

    protected $hidden = [
        'created_by',
        'created_on',
        'updated_by',
        'updated_on',
        'deleted_by',
        'deleted_on',
    ];

    public function integration()
    {
        return $this->hasOne(\App\Models\Integrations::class, 'id', 'int_id');
    }
}
