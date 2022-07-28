<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public $timestamps = false;

    protected $table = 'roles';

    protected $primaryKey = 'role_id';

    protected $fillable = [
        'hotel_id',
        'role_name',
        'default_role',
        'is_active',
    ];

    protected $hidden = [
        'hotel_id',
        'default_role',
        'is_active',
    ];
}
