<?php

namespace App\Http\Controllers\v2;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Models\HousekeepingCleanings;
use App\Models\HousekeepingEvents;
use App\Models\Event;
use App\Models\HotelRoom;
use App\User;
use Validator;
use DB;

class HousekeepingController extends Controller
{
    public function hskList(Request $request)
    {
        $paginate = isset($request->paginate) ? $request->paginate : 50;
        $staff_id = $request->user()->staff_id;
        $hotel_id = $request->hotel_id;

        if ($this->validateHotelId($hotel_id, $staff_id)) #endregion
        {
            $hsk = null;
            
            /*Se agrega valiacion para parametro all, 
            con el objetivo de mostrar todas las habitaciones cuando este llegue en true*/
            if (isset($request->all) && ($request->all == 'true' || $request->all == true)) {

                //Se consultan todas las habitaciones con estado en caso de que lo tenga en formato lineal(habitacion-status)
                $hsk = HotelRoom::leftJoin('housekeeping_cleanings as hsk', function ($join) {
                    $join->on('hotel_rooms.room_id', '=', 'hsk.room_id')
                        ->where('hotel_rooms.hotel_id', '=', 'hsk.hotel_id')
                        ->where('is_active', 1);
                })
                    ->where('hotel_rooms.hotel_id', $hotel_id)
                    ->where('hotel_rooms.active', true)
                    ->select(
                        'hotel_rooms.location',
                        'hotel_rooms.room_id',
                        'hsk.cleaning_id',
                        'hsk.count_by_hotel_id',
                        'hsk.hk_status',
                        'hsk.front_desk_status',
                        'hsk.created_on',
                        'hsk.assigned_date'
                    )
                    ->paginate($paginate);

                //Se recorre el resultado y se parsea al formato  de la consulta convencional (HousekeepingCleanings.room)
                foreach ($hsk as $shk_room) {
                    
                    //Se crea el formato room
                    $room = [
                        "location" => $shk_room->location, "room_id" => $shk_room->room_id
                    ];

                    // Se agrega room al objeto actual
                    $shk_room->room =  $room;

                    //Se eliminan los campos del objeto principal para evitar datos repetidos
                    unset($shk_room->location);
                    unset($shk_room->room_id);
                }
            } else {  //Consulta convencional de este metodo
                $hsk = HousekeepingCleanings::select([
                    'cleaning_id',
                    'count_by_hotel_id',
                    'room_id',
                    'hk_status',
                    'front_desk_status',
                    'created_on',
                    'assigned_date'
                ])
                    ->where('hotel_id', $hotel_id)
                    ->where('is_active', 1);

                if (isset($request->room_id) && is_numeric($request->room_id)) {
                    $hsk = $hsk->where('room_id', $request->room_id);
                }

                if (isset($request->assigned_date) && is_numeric($request->assigned_date)) {
                    $hsk = $hsk->where('assigned_date', $request->assigned_date);
                }

                if (isset($request->hk_status) && is_numeric($request->hk_status)) {
                    $hsk = $hsk->where('hk_status', $request->hk_status);
                }

                if (isset($request->front_desk_status) && is_numeric($request->front_desk_status)) {
                    $hsk = $hsk->where('front_desk_status', $request->front_desk_status);
                }

                $hsk = $hsk->with(['Room' => function ($q) {
                    $q->select('location', 'room_id');
                }])->orderBy('cleaning_id', 'DESC')->paginate($paginate);
            }

            return response()->json($hsk, 200);
        }

        return response()->json([], 400);
    }

    public function housekeeperList(Request $request)
    {
        $staff_id = $request->user()->staff_id;
        // Validar hotel
        if (!$request->exists('hotel_id')) return response()->json(["error" => "Hotel id not provided"], 400);
        $hotel_id = $request->hotel_id;
        // Validar acceso al hotel x usuario
        if (!$this->validateHotelId($hotel_id, $staff_id)) return response()->json(["error" => "User does not have access to the hotel"], 400);
        //  Validar que el usuario tenga permisos para realizar esta operacion
        $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 36, $action = 'view');
        if (!$permission) return response()->json(["error" => "User does not have permission to perform this action"], 400);

        $HousekeepingStaff = User::select(['staff_id as housekeeper_id', 'firstname', 'lastname', 'username', 'email'])
            ->whereHas('Housekeeper', function ($q) use ($hotel_id) {
                $q->where('hotel_id', $hotel_id)
                    ->where('is_active', true)
                    ->where('is_housekeeper', true);
            })->get();

        return response()->json(["housekepers" => $HousekeepingStaff], 200);
    }

    public function show(Request $request, $id)
    {
        $staff_id = $request->user()->staff_id;
        // Validar hotel
        if (!$request->exists('hotel_id')) return response()->json(["error" => "Hotel id not provided"], 400);
        $hotel_id = $request->hotel_id;

        // Validar acceso al hotel x usuario
        if (!$this->validateHotelId($hotel_id, $staff_id)) return response()->json(["error" => "User does not have access to the hotel"], 400);

        //  Validar que el usuario tenga permisos para realizar esta operacion
        $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 36, $action = 'view');
        if (!$permission) return response()->json(["error" => "User does not have permission to perform this action"], 400);

        $hsk = HousekeepingCleanings::select(['cleaning_id', 'count_by_hotel_id', 'room_id', 'hk_status', 'front_desk_status', 'created_on'])
            ->where('hotel_id', $hotel_id)
            ->where('cleaning_id', $id)
            ->where('is_active', 1)
            ->with(['Room' => function ($q) {
                $q->select('location', 'room_id');
            }])
            ->first();

        return response()->json($hsk, 200);
    }


    public function updateHsk(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $hkc = \App\Models\HousekeepingCleanings::find($id);
            //dd($hkc);
            if ($hkc) {
                /* Validate send object */
                if (!isset($request->hsk)) {
                    return response()->json([
                        'update' => false,
                        "message" => "housekeeping_cleaning object, data not provided",
                        "description" => []
                    ], 400);
                }
                /* configure timezone  by hotel */
                $this->configTimeZone($hkc->hotel_id);
                $HousekeepingData = [
                    "hotel_id" => $hotel_id,
                    "staff_id" => $request->user()->staff_id,
                    "rooms" => [
                        "room_id"   => $hsk->room_id,
                        "hk_status" => $request->hsk['hk_status'],
                    ]
                ];
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL             => "https://integrations.mynuvola.com/index.php/housekeeping/pmsHKChange",
                    CURLOPT_RETURNTRANSFER  => true,
                    CURLOPT_ENCODING        => "",
                    CURLOPT_MAXREDIRS       => 10,
                    CURLOPT_TIMEOUT         => 2,
                    CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST   => "POST",
                    CURLOPT_POSTFIELDS      => json_encode($HousekeepingData)
                ));
                $response = curl_exec($curl);
                $err = curl_error($curl);
                curl_close($curl);
                dd($response);
                return response()->json([
                    'update' => true,
                    'message' => 'updated',
                    'description' =>  $id
                ], 200);
            } else {
                return response()->json([
                    'update' => false,
                    'message' => 'Record not foun',
                    'description' =>  []
                ], 400);
            }
        } catch (\Exception $e) {
            \Log::info($e);
        }
    }

    public function pickup(Request $request)
    {
        DB::beginTransaction();
        try {
            $staff_id = $request->user()->staff_id;
            //  Validar hotel
            if (!$request->exists('hotel_id')) return response()->json(["error" => "Hotel id not provided"], 400);
            $hotel_id = $request->hotel_id;
            //  Validar acceso al hotel x usuario
            if (!$this->validateHotelId($hotel_id, $staff_id)) return response()->json(["error" => "User does not have access to the hotel"], 400);
            //  Validar que el usuario tenga permisos para realizar esta operacion
            $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 36, $action = 'create');
            if (!$permission) return response()->json(["error" => "User does not have permission to perform this action"], 400);
            //  Validar pickup_event
            if (!$request->exists('pickup_event')) return response()->json(["error" => "Pickup Event not provided"], 400);
            //Limpiado la informacion y solo trabajando con la que esta establecida en la documentacion
            $pickup_event = collect($request->pickup_event);
            $pickup_event = $pickup_event->only(['issue', 'room_no', 'room', 'dept_id', 'tag_id', 'date', 'time', 'priority']);
            $pickup_event = $pickup_event->all();
            //Realizando validacion
            $validation = Validator::make($pickup_event, [
                'issue'     => 'string',
                'room_no'   => 'required_without:room',
                'room'      => 'required_without:room_no',
                'dept_id'   => ['numeric', Rule::exists('departments')->where(function ($q) use ($hotel_id) {
                    $q->where('hotel_id', $hotel_id);
                })],
                'tag_id'    => ['numeric', Rule::exists('tags')->where(function ($q) use ($hotel_id) {
                    $q->where('hotel_id', $hotel_id);
                })],
                'date'      => 'date_format:Y-m-d',
                'time'      => 'date_format:H:i:s',
                'priority'  => 'numeric|in:1,2,3',
            ]);

            if ($validation->fails()) {
                return response()->json([
                    'create'        => false,
                    "message"       => "Pick up object failed",
                    "description"   => $validation->errors()
                ], 400);
            }

            // Crear evento
            // Capturar count by hotel id
            $__last_event = Event::where('hotel_id', $hotel_id)->orderBy('event_id', 'DESC')->first();
            $count_by_hotel_id = 0;
            if ($__last_event) {
                $count_by_hotel_id = $__last_event->count_by_hotel_id + 1;
            }

            // Establecer la habitación
            if (array_key_exists("room_id", $pickup_event)) {
                $__room_id = $pickup_event["room_id"];
            } else {
                $room = $this->findRoomId($hotel_id, $staff_id, $pickup_event["room"]);
                $__room_id = (int)$room["room_id"];
            }

            // Validar que la habitacion este VC or INS
            $__housekeeping_cleanings = HousekeepingCleanings::where('hotel_id', $hotel_id)
                ->where('room_id', $__room_id)
                ->where('assigned_date', date('Y-m-d'))
                ->first();

            if (!$__housekeeping_cleanings) {
                return response()->json([
                    'create'        => false,
                    "message"       => "Room does not have a cleaning currently",
                    "description"   => []
                ], 400);
            } else {
                if (
                    ($__housekeeping_cleanings->front_desk_status == 1 && $__housekeeping_cleanings->hk_status == 5) ||
                    (
                        ($__housekeeping_cleanings->front_desk_status == 1 && $__housekeeping_cleanings->hk_status == 3) ||
                        ($__housekeeping_cleanings->front_desk_status == 3 && $__housekeeping_cleanings->hk_status == 3) ||
                        ($__housekeeping_cleanings->front_desk_status == 5 && $__housekeeping_cleanings->hk_status == 3) ||
                        ($__housekeeping_cleanings->front_desk_status == 8 && $__housekeeping_cleanings->hk_status == 3) ||
                        ($__housekeeping_cleanings->front_desk_status == 9 && $__housekeeping_cleanings->hk_status == 3))
                ) {

                    //Capturar el huesped
                    $now = date('Y-m-d H:i:s');
                    $__guest_checkin_details = GuestCheckinDetails::select(["room_no", "guest_id"])
                        ->where('status', 1)
                        ->where('hotel_id', $hotel_id)
                        ->whereRaw(DB::raw("'$now' >= check_in and '$now' <= check_out"))
                        ->orderBy('sno', 'DESC')
                        ->first();

                    if ($__guest_checkin_details) $pickup_event["guest_id"] = $__guest_checkin_details->guest_id;

                    $__date = !array_key_exists('date', $pickup_event) ? date('Y-m-d') : $pickup_event["date"];
                    $pickup_event["date"] = $__date;

                    $__time = !array_key_exists('time', $pickup_event) ? date('H:i:s') : $pickup_event["time"];
                    $pickup_event["time"] = $__time;

                    if (strtotime($pickup_event["date"]) < strtotime(date('Y-m-d'))) {
                        return response()->json([
                            'create'        => false,
                            "message"       => "Invalid date",
                            "description"   => []
                        ], 400);
                    }

                    if (strtotime($pickup_event["date"]) == strtotime(date('Y-m-d'))) {
                        if (strtotime("$pickup_event[date] $pickup_event[time]") < date('Y-m-d H:i:s')) {
                            return response()->json([
                                'create'        => false,
                                "message"       => "Invalid time",
                                "description"   => []
                            ], 400);
                        }
                    }

                    $pickup_event["hotel_id"]           = $hotel_id;
                    $pickup_event["room_id"]            = $__room_id;
                    $pickup_event["count_by_hotel_id"]  = $count_by_hotel_id;
                    $pickup_event["created_by"]         = $staff_id;
                    $pickup_event["created_on"]         = $now;
                    $pickup_event["closed_by"]          = 0;

                    $event_id = Event::create($pickup_event)->event_id;

                    $__housekeeping_events = HousekeepingEvents::create([
                        'hotel_id'      => $hotel_id,
                        'cleaning_id'   => $__housekeeping_cleanings->cleaning_id,
                        'event_id'      => $event_id,
                        'is_pickup'     => 1,
                        'is_active'     => 1
                    ]);

                    $this->saveLogTracker([
                        'module_id' => 1,
                        'action'    => 'add',
                        'prim_id'   => $event_id,
                        'staff_id'  => $staff_id,
                        'date_time' => $now,
                        'comments'  => $pickup_event["issue"],
                        'hotel_id'  => $hotel_id,
                        'type'      => 'API-v2'
                    ]);
                } else {
                    return response()->json([
                        'create'        => false,
                        "message"       => "Room should be Vacant Clean or Inspected",
                        "description"   => []
                    ], 400);
                }
            }
            DB::commit();
        } catch (\Exception $th) {
            DB::rollback();
            return response()->json([
                'create'        => false,
                'message'       => 'Something went wrong',
                'description'   =>  []
            ], 400);
        }
    }




    // public function createHsk(Request $request)
    // {
    //     $staff_id = $request->user()->staff_id;
    //     if(!$request->exists('hotel_id')) return response()->json([ "error" => "Hotel id not provided" ], 400);        
    //     $hotel_id = $request->hotel_id;
    //     // Validar acceso al hotel x usuario
    //     if(!$this->validateHotelId($hotel_id, $staff_id)) return response()->json([ "error" => "User does not have access to the hotel" ], 400 );
    //     //  Validar que el usuario tenga permisos para realizar esta operacion
    //     $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 36, $action = 'create');
    //     if(!$permission) return response()->json(["error" => "User does not have permission to perform this action" ], 400 );
    //     if(!isset($request->cleaning)) return response()->json(["error" => "The cleaning object was not provided" ], 400 );
    //     $cleaning = $request->cleaning;
    //     foreach ($cleaning as $kClean => $vClean) {
    //         $validation = Validator::make($vClean, [
    //             'room'              => 'string|required',
    //             'housekeeper_id'    => 'numeric|required',
    //             'supervisor_id'     => 'numeric',
    //         ]);
    //     }

    // }






}
