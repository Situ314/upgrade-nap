<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyIntegration extends Model
{
    public $timestamps = false;

    protected $table = 'integrations_active';

    protected $casts = [
        'config' => 'json',
    ];

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
    ];

    protected $hidden = [
        'created_by',
        'created_on',
        'updated_by',
        'updated_on',
        'deleted_by',
        'deleted_on',
    ];
}
