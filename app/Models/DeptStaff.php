<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeptStaff extends Model
{
    public $timestamps = false;

    protected $table = 'department_staff';

    protected $primaryKey = 'dept_staff_id';

    protected $fillable = [
        'staff_id',
        'dept_id',
        'created_on',
        'created_by',
        'updated_on',
        'updated_by',
        'status',
    ];

    protected $hidden = [
        'created_on',
        'created_by',
        'updated_on',
        'updated_by',
        'status',
        'staff_id',
        'dept_id',
    ];

    public function staff()
    {
        return $this->hasOne('App\User', 'staff_id', 'staff_id');
    }

    public function departament()
    {
        return $this->hasOne('App\Models\Departament', 'dept_id', 'dept_id');
    }
}
