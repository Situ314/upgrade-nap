<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    public $timestamps = false;

    protected $table = 'tags';

    protected $primaryKey = 'tag_id';

    protected $fillable = [
        'hotel_id',
        'tag_name', //
        'tag_default',
        'tag_image',
        'tag_price',
        'created_on',
        'created_by',
        'updated_by',
        'tag_status',
        'tag_type',
        'is_active',
        'updated_on',
    ];

    protected $hidden = [
        'hotel_id',
        'tag_default',
        'tag_image',
        'tag_price',
        'created_on',
        'created_by',
        'updated_by',
        'tag_status',
        'tag_type',
        'is_active',
        'updated_on',
        'pivot',
    ];

    public function departments()
    {
        return $this->belongsToMany(\App\Models\Departament::class, 'dept_tag', 'tag_id', 'dept_id');
    }
}
