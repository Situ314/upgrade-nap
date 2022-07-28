<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    public $timestamps = false;

    protected $table = 'staff';

    protected $primaryKey = 'staff_id';

    protected $fillable = [
        'firstname',
        'lastname',
        'username',
        'email',
    ];

    protected $visible = ['firstname', 'lastname', 'username', 'email'];
}
