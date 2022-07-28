<?php

namespace App\Models\Opera;

use Illuminate\Database\Eloquent\Model;

class ProfilePhone extends Model
{
    protected $connection = 'opera';

    public $timestamps = false;

    protected $table = 'ProfilePhone';

    protected $primaryKey = 'ID';

    protected $fillable = [
        'ProfileID',
        'ProfileUniqueID',
        'PhoneType',
        'PhoneRole',
        'PhoneNumber',
    ];

    protected $hidden = [];
}
