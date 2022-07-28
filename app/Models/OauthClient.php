<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class OauthClient extends Authenticatable
{
    public $timestamps = false;

    public $remember_token = false;

    protected $table = 'oauth_clients';

    protected $fillable = [
        'id',
        'name',
        'secret',
        'redirect',
    ];

    protected $hidden = [];
}
