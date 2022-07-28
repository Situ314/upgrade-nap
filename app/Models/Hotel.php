<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    public $timestamps = false;

    protected $table = 'hotels';

    protected $primaryKey = 'hotel_id';

    protected $fillable = [
        'account',
        'hotel_name',
        'address',
        'phone_no',
        'city',
        'zip_code',
        'page_web',
        'color_code',
        'number_of_rooms',
        'time_zone',
        'state',
        'hotel_type',
        'created_by',
        'created_on',
        'activated_on',
        'updated_by',
        'updated_on',
        'is_active',
        'country',
        'language_id',
        'shift_now',
        'closed_event',
        'is_playground',
        'color_theme',
        'image_hotel',
        'background_login',
        'background_image',
        'background_menu',
        'image_hotel_logo_dark',
        'day_auto_close_event',
        'number_hotel',
        'link_to_angel',
    ];

    protected $hidden = [];
}
