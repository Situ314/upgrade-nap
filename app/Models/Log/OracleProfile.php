<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class OracleProfile extends Model{
    protected $connection   = 'integrationsLogs';
    protected $table        = 'Oracle_profile';
    public $timestamps      = false;
    protected $fillable     = [
        'resortId',
        'FirstName',
        'LastName',
        'EMAIL',
        'MOBILE',
        'AddressLine',
        'CityName',
        'PostalCode',
        'CountryCode',
        'state',
        'UniqueID',
        'birthDate',
        'created_at'
    ];
}