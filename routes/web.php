<?php

use \Illuminate\Http\Request;

// Route::get('/', function() { return view('welcome'); })->name('welcome');

Route::get('/', function() { 
    
    /*if(env("NUVOLA_DEV")) { return view('welcome'); }
    return Redirect::to("https://api-dev.mynuvola.net");*/
    return view('welcome');

})->name('welcome');

/*
|--------------------------------------------------------------------------
| MAESTRO PMS INTEGRATION
|--------------------------------------------------------------------------
*/
Route::post('maestro-pms', 'v1\MaestroPmsController@index');

/*
|--------------------------------------------------------------------------
| DOCUMENTATION
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'doc'], function() {
    Auth::routes();
    Route::get('/version', function() {
        return view('doc.version', ['url' => 'https://api-dev.mynuvola.net/api/v1']);
    })->name('doc.version');
});

Route::group(['prefix' => 'doc', 'middleware' => ['auth']], function() {
    Route::group(['prefix' => 'v1'], function() {
        $arr = ['url' => 'https://api-dev.mynuvola.net', 'version' => 'v1'];
        Route::get('/', function() use ($arr) { return view('doc.index', $arr); })->name('index_v1');
        Route::get('/token', function() use ($arr) { return view('doc.items.token', $arr); })->name('doc.token');
        $arr = ['url' => 'https://api-dev.mynuvola.net/api', 'version' => 'v1'];
        Route::get('/hotel', function() use ($arr) { return view('doc.items.hotel', $arr); });
        $arr = ['url' => 'https://api-dev.mynuvola.net/api/v1', 'version' => 'v1'];
        Route::get('/room',       function() use ($arr) { return view('doc.items.room', $arr); });
        Route::get('/guest',      function() use ($arr) { return view('doc.items.guest', $arr); });
        Route::get('/role',       function() use ($arr) { return view('doc.items.role', $arr); });
        Route::get('/staff',      function() use ($arr) { return view('doc.items.staff', $arr); });
        Route::get('/dept_tags',  function() use ($arr) { return view('doc.items.dept_tags', $arr); });
        Route::get('/event',      function() use ($arr) { return view('doc.items.event', $arr); });
        Route::get('/lost_found', function() use ($arr) { return view('doc.items.lost_found', $arr); });
        Route::get('/package',    function() use ($arr) { return view('doc.items.package', $arr); });
    });

    Route::group(['prefix' => 'v2'], function(){
        $arr = ['url' => 'https://api-dev.mynuvola.net', 'version' => 'v2'];
        Route::get('/token', function() use ($arr) { return view('doc.items.token', $arr); })->name('doc.token');
        $arr = ['url' => 'https://api-dev.mynuvola.net/api', 'version' => 'v2'];
        Route::get('/hotel', function() use ($arr) { return view('doc.items.hotel', $arr); });
        $arr = ['url' => 'https://api-dev.mynuvola.net/v2', 'version' => 'v2'];
        Route::get('/',             function() use ($arr) { return view('doc.index', $arr); })->name('index_v2');
        Route::get('/guest',        function() use ($arr) { return view('doc.items.guest', $arr); })->name('guest_v2');
        Route::get('/role',         function() use ($arr) { return view('doc.items.role', $arr); });
        Route::get('/staff',        function() use ($arr) { return view('doc.items.staff', $arr); });
        Route::get('/room',         function() use ($arr) { return view('doc.items.room', $arr); })->name('room_v2');
        Route::get('/dept',         function() use ($arr) { return view('doc.items.dept', $arr); })->name('dept_v2');
        Route::get('/event',        function() use ($arr) { return view('doc.items.event', $arr); })->name('dept_v2');
        Route::get('/hsk',          function() use ($arr) { return view('doc.items.housekeeping_cleaning', $arr); });
        Route::get('/maintenance',  function() use ($arr) { return view('doc.items.maintenance', $arr); });
    });
});
