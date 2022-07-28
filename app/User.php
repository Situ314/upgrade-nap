<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    public $timestamps = false;

    public $remember_token = false;

    protected $table = 'staff';

    protected $primaryKey = 'staff_id';

    //protected $appends = ['housekeeper_id'];
    protected $fillable = [
        'username',
        'firstname',
        'lastname',
        'password',
        'email',
        'access_code',
        'phone_number',
        'description',
        'is_super_admin',
        'staff_img',
        'staff_img_size',
        'staff_img_type',
        'is_active',
        'created_on',
        'created_by',
        'updated_on',
        'updated_by',
        'staff_img_name',
        'id_user_nuvola32',
        'push_key',
        'push_android',
        'executive',
        'email_executive',
        'show_tuto',
        'badge',
        'is_temporal',
        'tutorial',
        'badge_notifications_ids',
        'is_on_off',
        'is_api',
    ];

    protected $hidden = [
        'api_key',
        'api_id',
        'user_api',
        'indicative',
        'description',
        'password',
        'access_code',
        'is_super_admin',
        'staff_img',
        'staff_img_size',
        'staff_img_type',
        'created_on',
        'created_by',
        'updated_on',
        'updated_by',
        'staff_img_name',
        'id_user_nuvola32',
        'push_key',
        'push_android',
        'executive',
        'email_executive',
        'show_tuto',
        'badge',
        'is_temporal',
        'tutorial',
        'phone_number',
        'is_on_off',
        'badge_notifications_ids',
        'is_api',
    ];

    // public function getHousekeeperIdAttribute(){
    //     return $this->attributes["staff_id"];
    // }

    public function findForPassport($identifier)
    {
        return $this->orWhere('email', $identifier)->orWhere('username', $identifier)->where('is_active', [1, 3, 4])->first();
    }

    public function setPasswordAttribute($password)
    {
        $this->attributes['password'] = md5($password);
    }

    public function staffHotels()
    {
        return $this->hasMany(\App\Models\StaffHotel::class, 'staff_id');
    }

    public function Housekeeper()
    {
        return $this->hasOne(\App\Models\HousekeepingStaff::class, 'staff_id', 'staff_id');
    }
}
