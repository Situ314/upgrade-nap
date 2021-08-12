<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Authorization, X-Auth-Token,x-xsrf-token');

use App\Models\GuestCheckinDetails;
use App\Models\GuestRegistration;
use App\Models\HousekeepingCleanings;
use App\Models\Integrations;
use App\Models\HotelRoom;
use App\Models\IntegrationsActive;
use App\Models\IntegrationsGuestInformation;
use App\Models\IntegrationSuitesRoom;
use App\Models\SmsChat;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| EL PREFIX API SE LE AGREGA A LAS URLs YA QUE SE QUITO DEL ARCHIVO
| RouteServiceProVider.php
|--------------------------------------------------------------------------
*/

//V1
Route::group(['middleware' => ['auth:api'], 'prefix' => 'api'], function () {
    Route::get('/user', function (Request $request) {
        //get hotel_id
        $hotel_id = $request->hotel_id;
        //get user
        $user = $request->user();
        //get hotel information by hotel_id
        $user->load(['staffHotels' => function ($query) use ($hotel_id) {
            $query->where('hotel_id', '=', $hotel_id);
        }]);
        //load roles
        $user->staffHotels->load('role');

        return  $user;
    });

    Route::get('/hotels', 'HotelsController@index')->name('hotels');

    Route::group(['prefix' => 'v1'], function () {

        //HOTELS
        Route::get('/hotels', 'HotelsController@index')->name('hotels');

        //ROOMS
        Route::resource('hotel-room',       'v1\HotelRoomsController');
        Route::get('hotel-room-available',  'v1\HotelRoomsController@room_available');
        Route::get('hotel-room-occupied',   'v1\HotelRoomsController@room_occupied');

        //GUEST
        Route::resource('guest',                                        'v1\GuestController');
        Route::get('close-guest-checkin',                               'v1\GuestController@closeGuestCheckinDetails');
        Route::get('guest/validate/email/{hotel_id}/{email}',           'v1\GuestController@validateEmail');
        Route::get('guest/validate/phone/{hotel_id}/{phone_number}',    'v1\GuestController@validatePhoneNumber');

        //ROLE
        Route::resource('role', 'v1\RoleController');

        //STAFF
        Route::resource('staff', 'v1\StaffController');

        //DEPT AND TAG
        Route::resource('dept-tag', 'v1\DeptTagController');

        //EVENT
        Route::resource('event', 'v1\EventsController');

        //PACKAGES
        Route::resource('package', 'v1\PackagesController');

        //LOST FAOUND
        Route::resource('lost-found', 'v1\LostFoundController');

        //COMPANY INTEGRATION
        Route::resource('company-integration', 'v1\CompanyIntegrationController');
    });
});


//V2
Route::group(['middleware' => ['auth:api'], 'prefix' => 'v2'], function () {

    //pruebas cristian
    Route::get('eventTest', 'v2\EventsController@eventTest');

    //ROOM
    Route::resource('hotel-room',       'v2\HotelRoomsController');
    Route::resource('role', 'v1\RoleController');
    Route::resource('staff', 'v1\StaffController');
    Route::group(['prefix' => 'partner'], function () {
        Route::get('guest/{guest_number}', 'v2\GuestController@show2');
    });
    //GUEST
    Route::resource('guest', 'v2\GuestController');
    Route::post('guest/multiple-reservations', 'v2\GuestController@storeMultipe');
    Route::get('close-guest-checkin',                               'v2\GuestController@closeGuestCheckinDetails');
    Route::get('guest/validate/email/{hotel_id}/{email}',           'v2\GuestController@validateEmail');
    Route::get('guest/validate/phone/{hotel_id}/{phone_number}',    'v2\GuestController@validatePhoneNumber');
    Route::get('checkout-guest/{hotel_id}/{guest_id}/{room_id}',    'v2\GuestController@checkoutGuest');
    Route::get('checkout-room/{hotel_id}/{room_id}',                'v2\GuestController@checkoutRoom');
    //DEPARTMENT
    Route::resource('department', 'v2\DeparmentController');
    //EVENTS
    Route::get('event/guest/{guest_id}', 'v2\EventsController@indexByGuest');
    Route::resource('event', 'v2\EventsController');
    //COMTROL
    Route::group(['prefix' => 'comtrol'], function () {
        Route::post('check-in-guest',               'v2\ComtrolController@checkInGuest');
        Route::post('check-in-room',                'v2\ComtrolController@checkInRoom');
        Route::post('check-out-guest',              'v2\ComtrolController@checkOutGuest');
        Route::post('check-out-room',               'v2\ComtrolController@checkOutRoom');
        Route::post('express-checkout',             'v2\ComtrolController@expressCheckut');
        Route::post('room-move',                    'v2\ComtrolController@roomMove');
        Route::post('wake-up-request',              'v2\ComtrolController@wakeUpRequest');
        Route::post('wake-up-information-request',  'v2\ComtrolController@wakeUpInformationRequest');
    });
    //Housekeeping Cleaning
    Route::get('hsk',           'v2\HousekeepingController@hskList');
    Route::get('hsk/{id}',      'v2\HousekeepingController@show');
    Route::get('housekeeper',   'v2\HousekeepingController@housekeeperList');
    Route::post('hsk',          'v2\HousekeepingController@createHsk');
    Route::put('hsk/{id}',           'v2\HousekeepingController@updateHsk');
    //MAINTENANCE
    Route::resource('maintenance', 'v2\MaintenanceController');
});


Route::group(['prefix' => 'v2'], function () {
    Route::get('opera/RoomStatus', function () {
        return "Only the POST method enabled";
    });
    Route::get('opera/Profile',    function () {
        return "Only the POST method enabled";
    });
    Route::get('opera/Message',    function () {
        return "Only the POST method enabled";
    });
});

Route::group(['middleware' => ['oracle'], 'prefix' => 'v2'], function () {
    // Route::post('opera/Message ',    'v2\OperaController@index');
    Route::post('opera/RoomStatus', 'v3\OperaController@index');
    Route::post('opera/Profile',    'v3\OperaController@index');
    Route::post('opera/Message',    'v3\OperaController@index');
});

// integration with queues
Route::group(['middleware' => ['oracle'], 'prefix' => 'v3'], function () {
    Route::post('opera/RoomStatus', 'v3\OperaController@index');
    Route::post('opera/Profile',    'v3\OperaController@index');
    Route::post('opera/Message',    'v3\OperaController@index');
});

Route::group(['prefix' => 'v2'], function () {
    /*
    |--------------------------------------------------------------------------
    | TCA
    |--------------------------------------------------------------------------
    */
    Route::post('tca', 'v2\TcaController@index');
    /*
    |--------------------------------------------------------------------------
    | COMTROL
    |--------------------------------------------------------------------------
    */
    Route::post('comtrol', 'v2\ComtrolController@index');
});


/*
|--------------------------------------------------------------------------
| ALEXA
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'api'], function () {
    Route::group(['prefix' => 'v1'], function () {
        Route::post('alexa-room-service',   'v1\AlexaController@room_service');
        Route::post('alexa',                'v1\AlexaController@index');
        Route::post('alexa_validate',       'v1\AlexaController@alexa_validate');
        Route::post('alexa-checkout-time',  'v1\AlexaController@checkoutTime');
        Route::get('deviceAlexa', 'v1\AlexaController@getRoomByAlexa')->name('alexa_device');
        Route::get('staffAlexa', 'v1\AlexaController@getStaffData')->name('alexa_staff');
        Route::post('eventAlexa', 'v1\AlexaController@createEventAlexa')->name('alexa_event');
        Route::get('tagAlexa', 'v1\AlexaController@searchTag')->name('alexa_tag');
        Route::get('deptAlexa', 'v1\AlexaController@searchDepartment')->name('alexa_dept');
        Route::post('alexa/supervisor', 'v1\AlexaController@autoInspection')->name('alexa_supervisor');
        Route::post('alexa/update_hsk', 'v1\AlexaController@changeHskStatus')->name('alexa_hsk');
        Route::get('alexa/update_hsk', 'v1\AlexaController@changeHskStatus')->name('alexa_hsk');
        Route::get('alexa/get_guest_data', 'v1\AlexaController@getGuestData');
        Route::post('alexa/saveAlexaDevice', 'v1\AlexaController@saveDevice');
    });
});

Route::group(['prefix' => 'api'], function () {
    Route::group(['prefix' => 'v1'], function () {
        Route::get('alexa/oauth', 'v1\AlexaController@alexaAuth')->name('alexa_oauth');
        Route::post('alexa/login', 'v1\AlexaController@singIn')->name('alexa_login');
        Route::post('alexa/token', 'v1\AlexaController@generateToken')->name('alexa_token');
    });
});
/**
 * COMTROL
 */
Route::group(['middleware' => ['basic'], 'prefix' => 'lodginglink/api'], function () {

    Route::get('inbound',       'v2\ComtrolController@inbound');
    Route::post('outbound',     'v2\ComtrolController@outbound');

    Route::group(['prefix' => 'v16.0'], function () {
        Route::get('inbound',       'v2\ComtrolController@inbound');
        Route::post('outbound',     'v2\ComtrolController@outbound');
        Route::post('outbound-dev', 'v2\ComtrolController@outbound2');


        Route::group(['prefix' => 'rest'], function () {
            Route::get('inbound',       'v2\ComtrolController@inbound');
            Route::post('outbound',     'v2\ComtrolController@outbound');
            Route::post('outbound-dev', 'v2\ComtrolController@outbound2');
        });
    });
});

//Infor PMS
Route::post('v2/infor/{hotel_id}', 'v2\InforController@index')->middleware('InforAuth');

//StayNTouch
Route::group(['prefix' => 'v2/stayntouch'], function () {
    Route::post('guest/{hotel_id}',                     'v2\StayNTouchController@Guest');
    Route::post('reservation/{hotel_id}',               'v2\StayNTouchController@Reservation');
    Route::post('housekeeping/{hotel_id}',              'v2\StayNTouchController@Housekeeping');
    Route::post('housekeeping_stayntouch/{hotel_id}',   'v2\StayNTouchController@Housekeeping');
    Route::post('syncRoom/{hotel_id}',   'v2\StayNTouchController@createRoomConfig');
});

Route::post('/maestroSync/{hotel_id}/{room_id?}',  'v1\MaestroPmsController@GetDataSync');
Route::post('/operaSync/{hotel_id}/{room_id?}',    'v2\OperaController@SyncOracleHSK');
Route::post('/operaSyncLite/{hotel_id}/{room_id?}',    'v2\OperaController@SyncOracleHSKLite');
Route::post('/operaSyncOne/{hotel_id}/{room_id?}',    'v2\OperaController@SyncOracleHSKOne');
Route::post('/operafetch/{hotel_id}',    'v2\OperaController@fetch');
Route::post('/operaprofile/{hotel_id}',    'v2\OperaController@profile');
Route::post('/syncProfileData/{hotel_id}',    'v2\OperaController@syncProfileData');
// verify rooms
Route::post('/operaverify/{hotel_id}/',    'v2\OperaController@verify');

Route::group(['middleware' => ['auth:api'],], function () {
    Route::get('/getIntegrations',    'IntegrationMonitoringController@getHotel');
    Route::get('/getStats',    'IntegrationMonitoringController@getStats');
    Route::get('/getTotal/{hotel_id}/{date?}',    'IntegrationMonitoringController@getTotal');
});

Route::get('/reservationInquiry/{hotel_id}', function ($hotel_id) {
});


Route::post('v2/miller/request', 'v2\SMSMillerController@index')->middleware('miller');
Route::post('v2/miller/fetch/{hotel_id}', 'v2\SMSMillerController@SyncHSK');
Route::post('v2/miller/getreservation/{hotel_id}/{room_no?}', 'v2\SMSMillerController@SyncReservation');
Route::post('v2/miller/booking/{hotel_id}', 'v2\SMSMillerController@fetchData');
Route::post('v2/miller/fetchHSK/{hotel_id}', 'v2\SMSMillerController@fetchHSK');
Route::post('millerSync/{hotel_id}/{room_id}', 'v2\SMSMillerController@SyncReservation');
Route::post('v2/miller/allCodesInquiry/{hotel_id}', 'v2\SMSMillerController@allCodesInquiry');

//Route::get('crownParadise', 'CrownParadiseController@index');



Route::get('phpinfo', function () {
    phpinfo();
});

// V3
Route::group(['middleware' => ['auth:api'], 'prefix' => 'v3'], function () {
    Route::group(['prefix' => 'pms'], function () {
        // 
        Route::post("guest", "v3\GuestController@store");
        Route::put("guest", "v3\GuestController@update");
        // 
        Route::post("reservation", "v3\ReservationController@store");
        Route::put("reservation", "v3\ReservationController@update");
        // 
        Route::put("room-status", "v3\HousekeepingController@updateHsk");
    });
});
