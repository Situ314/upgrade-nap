<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationsGuestInformation extends Model
{
    public $timestamps = false;

    protected $table = 'integrations_guest_information';

    protected $fillable = [
        'hotel_id',
        'guest_id',
        'guest_number',
    ];

    protected $hidden = [];

    public function GuestRegistration()
    {
        return $this->hasOne('App\Models\GuestRegistration', 'guest_id', 'guest_id');
    }
}
