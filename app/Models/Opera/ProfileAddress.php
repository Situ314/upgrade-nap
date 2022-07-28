<?php

namespace App\Models\Opera;

use Illuminate\Database\Eloquent\Model;

class ProfileAddress extends Model
{
    protected $connection = 'opera';

    public $timestamps = false;

    protected $table = 'ProfileAddress';

    protected $primaryKey = 'ID';

    protected $fillable = [
        'ProfileID',
        'ProfileUniqueID',
        'AddressType',
        'AddressLine',
        'CityName',
        'StateProv',
        'CountryCode',
        'PostalCode',
    ];

    protected $hidden = [];
}
