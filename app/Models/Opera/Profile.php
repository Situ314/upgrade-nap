<?php

namespace App\Models\Opera;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $connection = 'opera';

    public $timestamps = false;

    protected $table = 'Profile';

    protected $primaryKey = 'ID';

    protected $fillable = [
        'UniqueID',
        'ProfileNameType',
        'ProfileVipCode',
        'BrithDate',
        'FristName',
        'LastName',
        'ResortID',
        'RequestType',
    ];

    protected $hidden = [];

    public function ProfileAddress()
    {
        return $this->hasOne('App\Models\Opera\ProfileAddress', 'ProfileID', 'ID');
    }

    public function ProfilePhone()
    {
        return $this->hasMany('App\Models\Opera\ProfilePhone', 'ProfileID', 'ID');
    }
}
