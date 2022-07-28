<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departament extends Model
{
    public $timestamps = false;

    protected $table = 'departments';

    protected $primaryKey = 'dept_id';

    protected $fillable = [
        'hotel_id',
        'dept_name',
        'short_name',
        'dep_default',
        'created_on',
        'created_by',
        'updated_on',
        'updated_by',
        'is_active',
        'tag_type',
        'color',
        'predetermined_target_2',
        'predetermined_target_3',
        'is_api',
    ];

    protected $hidden = [
        'hotel_id',
        'dep_default',
        'created_on',
        'created_by',
        'updated_on',
        'updated_by',
        'is_active',
        'tag_type',
        'color',
        'predetermined_target_2',
        'predetermined_target_3',
        'is_api',
    ];

    public function tags()
    {
        return $this->belongsToMany('App\Models\Tag', 'dept_tag', 'dept_id', 'tag_id');
    }
}
