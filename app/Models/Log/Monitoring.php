<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class monitoring extends Model
{
    protected $connection = 'integrationsLogs';

    protected $table = 'monitoring';

    public $timestamps = false;

    protected $casts = ['detail_json' => 'json'];

    protected $fillable = [
        'Hotel_id',
        'date',
        'time',
        'total',
        'detail_json',
        'int_id',
    ];
}
