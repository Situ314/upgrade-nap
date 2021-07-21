<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaestroPmsSalt extends Model
{
    public $timestamps = false;

    //protected $connection= 'developer_db_connection';
    protected $table = 'maestro_pms_salt';
    
    protected $primaryKey = 'id';
    protected $fillable = [
        'hotel_id',
        'salt',
        'created_on',
    ];
    protected $hidden = [
    ];
}