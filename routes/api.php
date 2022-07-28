<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Authorization, X-Auth-Token,x-xsrf-token');

use App\Http\Controllers\CrownParadiseController;
use App\Http\Controllers\HotelsController;
use App\Http\Controllers\IntegrationMonitoringController;
use App\Http\Controllers\v1;
use App\Http\Controllers\v2;
use App\Http\Controllers\v3;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| EL PREFIX API SE LE AGREGA A LAS URLs YA QUE SE QUITO DEL ARCHIVO
| RouteServiceProVider.php
|--------------------------------------------------------------------------
*/

//V1
Route::middleware('auth:api')->prefix('api')->group(function () {
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

    Route::get('/hotels', [HotelsController::class, 'index'])->name('hotels');

    Route::prefix('v1')->group(function () {

        //HOTELS
        Route::get('/hotels', [HotelsController::class, 'index'])->name('hotels');

        //ROOMS
        Route::resource('hotel-room', v1\HotelRoomsController::class);
        Route::get('hotel-room-available', [v1\HotelRoomsController::class, 'room_available']);
        Route::get('hotel-room-occupied', [v1\HotelRoomsController::class, 'room_occupied']);

        //GUEST
        Route::resource('guest', v1\GuestController::class);
        Route::get('close-guest-checkin', [v1\GuestController::class, 'closeGuestCheckinDetails']);
        Route::get('guest/validate/email/{hotel_id}/{email}', [v1\GuestController::class, 'validateEmail']);
        Route::get('guest/validate/phone/{hotel_id}/{phone_number}', [v1\GuestController::class, 'validatePhoneNumber']);

        //ROLE
        Route::resource('role', v1\RoleController::class);

        //STAFF
        Route::resource('staff', v1\StaffController::class);

        //DEPT AND TAG
        Route::resource('dept-tag', v1\DeptTagController::class);

        //EVENT
        Route::resource('event', v1\EventsController::class);

        //PACKAGES
        Route::resource('package', v1\PackagesController::class);

        //LOST FAOUND
        Route::resource('lost-found', v1\LostFoundController::class);

        //COMPANY INTEGRATION
        Route::resource('company-integration', v1\CompanyIntegrationController::class);
    });
});

//V2
Route::middleware('auth:api')->prefix('v2')->group(function () {

    //pruebas cristian
    Route::get('eventTest', [v2\EventsController::class, 'eventTest']);

    //ROOM
    Route::resource('hotel-room', v2\HotelRoomsController::class);
    Route::resource('role', v1\RoleController::class);
    Route::resource('staff', v1\StaffController::class);
    Route::prefix('partner')->group(function () {
        Route::get('guest/{guest_number}', [v2\GuestController::class, 'show2']);
    });
    //GUEST
    Route::resource('guest', v2\GuestController::class);
    Route::post('guest/multiple-reservations', [v2\GuestController::class, 'storeMultipe']);
    Route::get('close-guest-checkin', [v2\GuestController::class, 'closeGuestCheckinDetails']);
    Route::get('guest/validate/email/{hotel_id}/{email}', [v2\GuestController::class, 'validateEmail']);
    Route::get('guest/validate/phone/{hotel_id}/{phone_number}', [v2\GuestController::class, 'validatePhoneNumber']);
    Route::get('checkout-guest/{hotel_id}/{guest_id}/{room_id}', [v2\GuestController::class, 'checkoutGuest']);
    Route::get('checkout-room/{hotel_id}/{room_id}', [v2\GuestController::class, 'checkoutRoom']);
    //DEPARTMENT
    Route::resource('department', v2\DeparmentController::class);
    //EVENTS
    Route::get('event/guest/{guest_id}', [v2\EventsController::class, 'indexByGuest']);
    Route::resource('event', v2\EventsController::class);
    //COMTROL
    Route::prefix('comtrol')->group(function () {
        Route::post('check-in-guest', [v2\ComtrolController::class, 'checkInGuest']);
        Route::post('check-in-room', [v2\ComtrolController::class, 'checkInRoom']);
        Route::post('check-out-guest', [v2\ComtrolController::class, 'checkOutGuest']);
        Route::post('check-out-room', [v2\ComtrolController::class, 'checkOutRoom']);
        Route::post('express-checkout', [v2\ComtrolController::class, 'expressCheckut']);
        Route::post('room-move', [v2\ComtrolController::class, 'roomMove']);
        Route::post('wake-up-request', [v2\ComtrolController::class, 'wakeUpRequest']);
        Route::post('wake-up-information-request', [v2\ComtrolController::class, 'wakeUpInformationRequest']);
    });
    //Housekeeping Cleaning
    Route::get('hsk', [v2\HousekeepingController::class, 'hskList']);
    Route::get('hsk/{id}', [v2\HousekeepingController::class, 'show']);
    Route::get('housekeeper', [v2\HousekeepingController::class, 'housekeeperList']);
    Route::post('hsk', [v2\HousekeepingController::class, 'createHsk']);
    Route::put('hsk/{id}', [v2\HousekeepingController::class, 'updateHsk']);
    //MAINTENANCE
    Route::resource('maintenance', v2\MaintenanceController::class);
});

Route::prefix('v2')->group(function () {
    Route::get('opera/RoomStatus', function () {
        return 'Only the POST method enabled';
    });
    Route::get('opera/Profile', function () {
        return 'Only the POST method enabled';
    });
    Route::get('opera/Message', function () {
        return 'Only the POST method enabled';
    });
});

Route::middleware('oracle')->prefix('v2')->group(function () {
    // Route::post('opera/Message ',    [v2\OperaController::class, 'index']);
    Route::post('opera/RoomStatus', [v3\OperaController::class, 'index']);
    Route::post('opera/Profile', [v3\OperaController::class, 'index']);
    Route::post('opera/Message', [v3\OperaController::class, 'index']);
});

// integration with queues
Route::middleware('oracle')->prefix('v3')->group(function () {
    Route::post('opera/RoomStatus', [v3\OperaController::class, 'index']);
    Route::post('opera/Profile', [v3\OperaController::class, 'index']);
    Route::post('opera/Message', [v3\OperaController::class, 'index']);
});

Route::prefix('v2')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | TCA
    |--------------------------------------------------------------------------
    */
    Route::post('tca', [v2\TcaController::class, 'index']);
    /*
    |--------------------------------------------------------------------------
    | COMTROL
    |--------------------------------------------------------------------------
    */
    Route::post('comtrol', [v2\ComtrolController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| ALEXA
|--------------------------------------------------------------------------
*/
Route::prefix('api')->group(function () {
    Route::prefix('v1')->group(function () {
        Route::post('alexa-room-service', [v1\AlexaController::class, 'room_service']);
        Route::post('alexa', [v1\AlexaController::class, 'index']);
        Route::post('alexa_validate', [v1\AlexaController::class, 'alexa_validate']);
        Route::post('alexa-checkout-time', [v1\AlexaController::class, 'checkoutTime']);
        Route::get('deviceAlexa', [v1\AlexaController::class, 'getRoomByAlexa'])->name('alexa_device');
        Route::get('staffAlexa', [v1\AlexaController::class, 'getStaffData'])->name('alexa_staff');
        Route::post('eventAlexa', [v1\AlexaController::class, 'createEventAlexa'])->name('alexa_event');
        Route::get('tagAlexa', [v1\AlexaController::class, 'searchTag'])->name('alexa_tag');
        Route::get('deptAlexa', [v1\AlexaController::class, 'searchDepartment'])->name('alexa_dept');
        Route::post('alexa/supervisor', [v1\AlexaController::class, 'autoInspection'])->name('alexa_supervisor');
        Route::post('alexa/update_hsk', [v1\AlexaController::class, 'changeHskStatus'])->name('alexa_hsk');
        Route::get('alexa/update_hsk', [v1\AlexaController::class, 'changeHskStatus'])->name('alexa_hsk');
        Route::get('alexa/get_guest_data', [v1\AlexaController::class, 'getGuestData']);
        Route::post('alexa/saveAlexaDevice', [v1\AlexaController::class, 'saveDevice']);
    });
});

Route::prefix('api')->group(function () {
    Route::prefix('v1')->group(function () {
        Route::get('alexa/oauth', [v1\AlexaController::class, 'alexaAuth'])->name('alexa_oauth');
        Route::post('alexa/login', [v1\AlexaController::class, 'singIn'])->name('alexa_login');
        Route::post('alexa/token', [v1\AlexaController::class, 'generateToken'])->name('alexa_token');
    });
});
/**
 * COMTROL
 */
Route::middleware('basic')->prefix('lodginglink/api')->group(function () {
    Route::get('inbound', [v2\ComtrolController::class, 'inbound']);
    Route::post('outbound', [v2\ComtrolController::class, 'outbound']);

    Route::prefix('v16.0')->group(function () {
        Route::get('inbound', [v2\ComtrolController::class, 'inbound']);
        Route::post('outbound', [v2\ComtrolController::class, 'outbound']);
        Route::post('outbound-dev', [v2\ComtrolController::class, 'outbound2']);

        Route::prefix('rest')->group(function () {
            Route::get('inbound', [v2\ComtrolController::class, 'inbound']);
            Route::post('outbound', [v2\ComtrolController::class, 'outbound']);
            Route::post('outbound-dev', [v2\ComtrolController::class, 'outbound2']);
        });
    });
});

//Infor PMS
Route::post('v2/infor/{hotel_id}', [v2\InforController::class, 'index'])->middleware('InforAuth');

//StayNTouch
Route::prefix('v2/stayntouch')->group(function () {
    Route::post('guest/{hotel_id}', [v2\StayNTouchController::class, 'Guest']);
    Route::post('reservation/{hotel_id}', [v2\StayNTouchController::class, 'Reservation']);
    Route::post('housekeeping/{hotel_id}', [v2\StayNTouchController::class, 'Housekeeping']);
    Route::post('housekeeping_stayntouch/{hotel_id}', [v2\StayNTouchController::class, 'Housekeeping']);
    Route::post('syncRoom/{hotel_id}', [v2\StayNTouchController::class, 'createRoomConfig']);
});

Route::post('/maestroSync/{hotel_id}/{room_id?}', [v1\MaestroPmsController::class, 'GetDataSync']);

Route::post('/operaSync/{hotel_id}/{room_id?}', [v2\OperaController::class, 'SyncOracleHSK']);
Route::post('/operaSyncReserved/{hotel_id}/{room_id?}', [v2\OperaController::class, 'SyncOracleHSKReserved']);

Route::post('/operaSyncLite/{hotel_id}/{room_id?}', [v2\OperaController::class, 'SyncOracleHSKLite']);
Route::post('/operaSyncOne/{hotel_id}/{room_id?}', [v2\OperaController::class, 'SyncOracleHSKOne']);
Route::post('/operafetch/{hotel_id}', [v2\OperaController::class, 'fetch']);
Route::post('/operaprofile/{hotel_id}', [v2\OperaController::class, 'profile']);
Route::post('/syncProfileData/{hotel_id}', [v2\OperaController::class, 'syncProfileData']);
// verify rooms
Route::post('/operaverify/{hotel_id}/', [v2\OperaController::class, 'verify']);

Route::middleware('auth:api')->group(function () {
    Route::get('/getIntegrations', [IntegrationMonitoringController::class, 'getHotel']);
    Route::get('/getStats', [IntegrationMonitoringController::class, 'getStats']);
    Route::get('/getTotal/{hotel_id}/{date?}', [IntegrationMonitoringController::class, 'getTotal']);
});

Route::get('/reservationInquiry/{hotel_id}', function ($hotel_id) {
});

Route::post('v2/miller/request', [v2\SMSMillerController::class, 'index'])->middleware('miller');
Route::post('v2/miller/fetch/{hotel_id}', [v2\SMSMillerController::class, 'SyncHSK']);
Route::post('v2/miller/getreservation/{hotel_id}/{room_no?}', [v2\SMSMillerController::class, 'SyncReservation']);
Route::post('v2/miller/booking/{hotel_id}', [v2\SMSMillerController::class, 'fetchData']);
Route::post('v2/miller/fetchHSK/{hotel_id}', [v2\SMSMillerController::class, 'fetchHSK']);
Route::post('millerSync/{hotel_id}/{room_id}', [v2\SMSMillerController::class, 'SyncReservation']);
Route::post('v2/miller/allCodesInquiry/{hotel_id}', [v2\SMSMillerController::class, 'allCodesInquiry']);

//Route::get('crownParadise', [CrownParadiseController::class, 'index']);

Route::get('phpinfo', function () {
    phpinfo();
});

// V3
// https://api.mynuvola.net/v3/pms/reservation

Route::middleware('auth:api')->prefix('v3')->group(function () {
    Route::prefix('pms')->group(function () {
        //
        Route::post('guest', [v3\GuestController::class, 'store']);
        Route::put('guest', [v3\GuestController::class, 'update']);
        //
        Route::get('reservation', [v3\ReservationController::class, 'index']);
        Route::post('reservation', [v3\ReservationController::class, 'store']);
        Route::put('reservation', [v3\ReservationController::class, 'update']);
        //
        Route::put('room-status', [v3\HousekeepingController::class, 'updateHsk']);
    });
});

Route::prefix('v3')->group(function () {
    Route::prefix('pms')->group(function () {
        Route::prefix('change-hsk-status')->group(function () {
            // SYNERGEX
            Route::post('synergex', [v3\HousekeepingController::class, 'synergexSendHskChangeStatus']);
        });
    });
});

/**
 * Routes Testing
 */

//Testing Rooms Opera
Route::post('/testingoperaSync/{hotel_id}/{room_id?}', [v2\OperaController::class, 'testingoperaSync']);

Route::post('/opera/process-profile', [v3\OperaController::class, 'processProfile']);
