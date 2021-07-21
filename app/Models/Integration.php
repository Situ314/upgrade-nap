<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    public $timestamps = false;
    protected $table = 'integration';
    protected $primaryKey = 'integration_id';
    protected $fillable = [
        'nuvola_property_id',
        'behive_property_id',
        'contact_sync_enabled',
        'task_sync_enabled',
        'active',
        'created_by',
        'created_on',
        'updated_by',
        'updated_on',
    ];
    protected $hidden = [
        'active',
        'created_by',
        'created_on',
        'updated_by',
        'updated_on'        
    ];
}