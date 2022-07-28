<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceTasksRecords extends Model
{
    public $timestamps = false;

    protected $table = 'maintenance_tasks_records';

    protected $primaryKey = 'maintenance_record_item_id';

    protected $fillable = [
        'task_id',
        'description',
        'acording_to',
        'maintenance_record_id',
        'status',
        'comment',
        'tag_item_id',
        'tasklist_id',
        'is_active',
        'hotel_id',
        'created_on',
        'created_by',
        'updated_on',
        'updated_by',
        'deleted_on',
        'deleted_by',
        'completed_by',
        'completed_on',
    ];

    protected $hidden = [
        'created_on',
        'created_by',
        'updated_on',
        'updated_by',
        'deleted_on',
        'deleted_by',
    ];
}
