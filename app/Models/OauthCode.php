<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class OauthCode extends Authenticatable
{
    public $timestamps = false;
    public $remember_token=false;
    protected $table = 'oauth_auth_codes';
    protected $fillable = [
        'id',
        'user_id',
        'client_id',
        'scopes',
        'revoked',
        'expires_at'
    ];
    protected $hidden = [];
}
