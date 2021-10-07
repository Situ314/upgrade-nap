<?php

namespace App\Http\Controllers\v2;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use Validator;
use \App\Models\Event;
use \App\Models\GuestCheckinDetails;
use Illuminate\Validation\Rule;
use \App\Models\DeptTag;
// use App\Models\EventStay; ESTA SOLO EN DEVELOP PERO NO EN PRODUCTION
use App\Models\IntegrationsActive;
use App\Models\IntegrationsStay;

class EventsController extends Controller
{

    public function index(Request $request)
    {
        // captura de parametros iniciales         
        $paginate = $request->paginate ?: 50;
        if (!is_numeric($paginate)) {
            return response()->json(["sintaxis error" => "Paginate is numeric type"], 400);
        }
        $staff_id = $request->user()->staff_id;
        // Validar hotel
        if (!$request->exists('hotel_id')) return response()->json(["error" => "Hotel id not provided"], 400);
        $hotel_id = $request->hotel_id;
        if (!is_numeric($hotel_id)) return response()->json(["sintaxis error" => "Hotel ID is numeric type"], 400);
        // Validar acceso al hotel x usuario
        if (!$this->validateHotelId($hotel_id, $staff_id)) return response()->json(["error" => "User does not have access to the hotel"], 400);
        // Validar que el usuario tenga permisos para realizar esta operacion
        $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 1, $action = 'view');
        if (!$permission) return response()->json(["error" => "User does not have permission to perform this action"], 400);
        // Configurar timezone y capturar fecha
        $this->configTimeZone($hotel_id);
        $now = date('Y-m-d H:i:s');

        $guest_id = $request->exists('guest_id') ? $request->guest_id : 0;
        if (!is_numeric($guest_id)) return response()->json(["error" => "Guest id is not a number "], 400);

        $room_number = $request->exists('room_number') ? $request->room_number : null;
        if($room_number) $room_number = explode(",", $room_number);
        else $room_number = [];

        $room_ids = [];
        if (count($room_number) > 0) {
            $hotelRooms = \App\Models\HotelRoom::select("room_id")->where('hotel_id', $hotel_id)->whereIn("location", $room_number)->get();
            foreach ($hotelRooms as $r) {
                $room_ids[] = $r->room_id;
            }
        }

        $columns = [
            'event_id',
            'guest_id',
            'room_id',
            'issue',
            'dept_tag_id',
            'date',
            'time'
        ];

        $query = Event::select($columns)
            ->with([
                'Room' => function ($q) {
                    return $q->select(['room_id', 'location']);
                },
                'DepTag.departament',
                'DepTag.tag'
            ])
            ->where(function ($q) use ($hotel_id, $guest_id, $room_ids) {
                $q->where('hotel_id', $hotel_id)->where('active', 1);
                if ($guest_id > 0) $q->where('guest_id', $guest_id);
                if (count($room_ids) > 0) $q->whereIn("room_id", $room_ids);
                return $q;
            })
            ->orderBy('event_id', 'DESC');

        $data = $query->paginate($paginate);
        return response()->json($data, 200);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            if (!$request->exists('hotel_id')) {
                return response()->json([
                    "create"        => false,
                    "message"       => "Hotel id not provided",
                    "description"   => []
                ], 400);
            }
            $hotel_id = $request->hotel_id;
            $staff_id = $request->user()->staff_id;
            $this->configTimeZone($hotel_id);

            if (!$this->validateHotelId($hotel_id, $staff_id)) {
                return response()->json([
                    "create"        => false,
                    "message"       => "User does not have access to the hotel",
                    "description"   => null
                ], 400);
            }

            $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 1, $action = 'create');
            if (!$permission) {
                return response()->json([
                    "create"        => false,
                    "message"       => "User does not have permission to perform this action",
                    "description"   => null
                ], 400);
            }
            if (!$request->exists('events')) {
                return response()->json([
                    "create"        => false,
                    "message"       => "event object, data not provided",
                    "description"   => null
                ], 400);
            }

            $event = $request->events;
            $__integration = IntegrationsActive::where('hotel_id', $hotel_id)->where('int_id', 13)->where('state', 1)->first();

            $now = date("Y-m-d");

            $data_validate = [
                'issue' => 'string|required',
                'location' => [
                    'string',
                    'required_without:room_id',
                    Rule::exists('hotel_rooms')->where(function ($q) use ($hotel_id) {
                        $q->where('hotel_id', $hotel_id);
                    })
                ],
                'room_id' => [
                    'numeric',
                    'required_without:location',
                    Rule::exists('hotel_rooms')->where(function ($q) use ($hotel_id) {
                        $q->where('hotel_id', $hotel_id);
                    })
                ],
                'guest_id' => [
                    'numeric',
                    Rule::exists('guest_registration')->where(function ($q) use ($hotel_id) {
                        $q->where('hotel_id', $hotel_id);
                    })
                ],
                'priority' => [
                    'numeric',
                    Rule::in([1, 2, 3])
                ],
                'status' => [
                    'numeric',
                    Rule::in([1, 2, 3])
                ],
                'date' => 'date_format:Y-m-d|after:' . date("Y-m-d", strtotime($now . " -1 day")),
                'time' => 'required_with:date|date_format:H:i:s'
            ];

            if (!$__integration) {
                $data_validate['tag_id'] = [
                    'numeric',
                    Rule::exists('tags')->where(function ($q) use ($hotel_id) {
                        $q->where('hotel_id', $hotel_id);
                    })
                ];

                $data_validate['dept_id'] =  [
                    'numeric',
                    Rule::exists('departments')->where(function ($q) use ($hotel_id) {
                        $q->where('hotel_id', $hotel_id);
                    })
                ];
            }

            $validation = Validator::make($event, $data_validate);

            if ($validation->fails()) {
                return response()->json([
                    'create'        => false,
                    "message"       => "event object, failed validation",
                    "description"   => $validation->errors()
                ], 400);
            }

            $__data_event = [
                'issue',
                'guest_id',
                'room_id',
                'location',
                'priority',
                'status',
                'tag_id',
                'date',
                'time'
            ];

            if (!$__integration) {
                $__data_event[] = 'dept_id';
            }
            $event = collect($request->events);
            $event = $event->only($__data_event);
            $event = $event->all();



            if ($__integration) {
                $integration_stay = IntegrationsStay::where('hotel_id', $hotel_id)->where('product_id', $event['tag_id'])->first();
                if (!$integration_stay) {
                    return response()->json([
                        'create'        => false,
                        "message"       => "event object, failed validation",
                        "description"   => "invalid tag_id"
                    ], 400);
                }
                $event['tag_id'] = $integration_stay->tag_id;
                // $event['dept_id'] = $integration_stay->product_id;
                $event['dept_id'] = $integration_stay->dept_id;
            }
            $last_event = Event::where('hotel_id', $hotel_id)
                ->orderBy('event_id', 'DESC')
                ->first();

            $count_by_hotel_id = 0;
            if ($last_event) {
                $count_by_hotel_id = $last_event->count_by_hotel_id + 1;
            }

            $room_id    = isset($event['room_id'])  ? $event['room_id']  : "";
            $location   = isset($event['location']) ? $event['location'] : "";
            $guest_id   = isset($event['guest_id']) ? $event['guest_id'] : "";

            if (empty($room_id)) {
                $room = $this->getRoom($hotel_id, $staff_id, $location);
                $room_id = $room['room_id'];
            }
            $now = date('Y-m-d H:i:s');

            $guest_checkin_details =  GuestCheckinDetails::select('room_no', 'guest_id')
                ->where(function ($q) use ($room_id, $guest_id, $now, $hotel_id) {
                    $q
                        ->where('status', 1)
                        ->where('hotel_id', $hotel_id)
                        ->whereRaw(DB::raw("'$now' >= check_in and '$now' <= check_out"));

                    if (!empty($room_id)) {
                        $q->where('room_no', $room_id);
                    } else {
                        $q->where('guest_id', $guest_id);
                    }
                })
                ->orderBy('sno', 'DESC')
                ->first();

            $event['guest_id'] = !empty($event['guest_id']) ? $event['guest_id'] : 0;
            $event['room_id'] = $room_id;

            if ($guest_checkin_details) {
                if (empty($room_id)) {
                    $event['room_id'] = $guest_checkin_details->room_no;
                } else {
                    $event['guest_id'] = $guest_checkin_details->guest_id;
                }
            }


            $getDeptTag = DeptTag::select(['dept_tag_id'])
                ->where('hotel_id', $hotel_id)
                ->where('dept_id', $event["dept_id"])
                ->where('tag_id', $event["tag_id"])
                ->first();

            if (!$getDeptTag) {
                return response()->json([
                    'create'        => false,
                    "message"       => "dept_id and tag_id are not linked",
                    "description"   => null
                ], 400);
            }

            $event["dept_tag_id"]       = $getDeptTag->dept_tag_id;
            $event["hotel_id"]          = $hotel_id;
            $event["issue"]             = $this->proccessString($event["issue"]);
            $event["closed_by"]         = 0;
            $event["count_by_hotel_id"] = $count_by_hotel_id;
            $event["created_by"]        = $staff_id;
            $event["created_on"]        = $now;
            $event["pending_by"]        = $staff_id;

            if (!isset($event["date"])) {
                $date = date('Y-m-d');
                $time = date('H:i:s');
                $event["date"]  = $date;
                $event["time"]  = $time;
                $event["pending_on"] = $now;
            } else {
                $event["status"] = 5;
                $event["pending_on"] = $event["date"] . " " . $event["time"];
            }

            $event_id = Event::create($event)->event_id;

            // if ($__integration) {
            //     EventStay::create([
            //         'hotel_id' => $hotel_id,
            //         'event_id' => $event_id,
            //         'stay_id' => $request->request_ids,
            //     ]);
            // }

            $this->saveLogTracker([
                'module_id' => 1,
                'action'    => 'add',
                'prim_id'   => $event_id,
                'staff_id'  => $request->user()->staff_id,
                'date_time' => $now,
                'comments'  => $event["issue"],
                'hotel_id'  => $event["hotel_id"],
                'type'      => 'API-V2'
            ]);

            DB::commit();

            return response()->json([
                'create'        => true,
                'event_id'      => $event_id,
                'message'       => '',
                'description'   => null
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'create'        => false,
                'message'       => 'Bad request',
                'description'   =>  []
            ], 400);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            if (!$request->exists('hotel_id')) {
                return response()->json([
                    "update"        => false,
                    "message"       => "Hotel id not provided",
                    "description"   => null
                ], 400);
            }
            $hotel_id = $request->hotel_id;
            $staff_id = $request->user()->staff_id;
            $this->configTimeZone($hotel_id);
            if (!$this->validateHotelId($hotel_id, $staff_id)) {
                return response()->json([
                    "update"        => false,
                    "message"       => "User does not have access to the hotel",
                    "description"   => null
                ], 400);
            }
            $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 1, $action = 'update');
            if (!$permission) {
                return response()->json([
                    "update"        => false,
                    "message"       => "User does not have permission to perform this action",
                    "description"   => null
                ], 400);
            }
            if (!$request->exists('events')) {
                return response()->json([
                    "update"        => false,
                    "message"       => "event object, data not provided",
                    "description"   => null
                ], 400);
            }
            $event_update = $request->events;
            $validation = Validator::make($event_update, [
                'issue'       => 'string',
                'location'  => [
                    'string',
                    Rule::exists('hotel_rooms')->where(function ($q) use ($hotel_id) {
                        $q->where('hotel_id', $hotel_id);
                    })
                ],
                'room_id'   => [
                    'numeric',
                    Rule::exists('hotel_rooms')->where(function ($q) use ($hotel_id) {
                        $q->where('hotel_id', $hotel_id);
                    })
                ],
                'guest_id'  => [
                    'numeric',
                    Rule::exists('guest_registration')->where(function ($query) use ($hotel_id) {
                        $query->where('hotel_id', $hotel_id);
                    })
                ],
                'dept_id' => [
                    'numeric',
                    Rule::exists('departments')->where(function ($query) use ($hotel_id) {
                        $query->where('hotel_id', $hotel_id);
                    })
                ],
                'tag_id' => [
                    'numeric',
                    Rule::exists('tags')->where(function ($query) use ($hotel_id) {
                        $query->where('hotel_id', $hotel_id);
                    })
                ],
                'priority'  => ['numeric', Rule::in([1, 2, 3])],
                'status'    => ['numeric', Rule::in([1, 2, 3])]
            ]);
            if ($validation->fails()) {
                return response()->json([
                    'update'        => false,
                    "message"       => "event object, failed validation",
                    "description"   => $validation->errors()
                ], 400);
            }
            $room_id    = isset($event_update['room_id'])  ? $event_update['room_id']  : "";
            $location   = isset($event_update['location']) ? $event_update['location'] : "";
            $guest_id   = isset($event_update['guest_id']) ? $event_update['guest_id'] : "";

            $event = Event::find($id);
            if (!$event) {
                return response()->json([
                    'update'        => false,
                    'message'       => 'Record not found',
                    'description'   =>  null
                ], 400);
            }

            $__update = false;
            if (!empty($room_id) && $event->room_id !== $room_id) {
                $event->room_id = $room_id;
                $__update = true;
            }

            if (!empty($location)) {
                $room = $this->getRoom($hotel_id, $staff_id, $location);
                $room_id = $room['room_id'];
                if ($event->room_id !== $room_id) {
                    $event->room_id = $room_id;
                    $__update = true;
                }
            }

            if ($__update || !empty($guest_id)) {
                $now = date('Y-m-d H:i:s');
                $GuestCheckinDetails =  GuestCheckinDetails::select('room_no', 'guest_id')
                    ->where(function ($q) use ($room_id, $guest_id, $now, $hotel_id, $__update) {
                        $q
                            ->where('hotel_id', $hotel_id)
                            ->whereRaw(DB::raw("'$now' >= check_in and '$now' <= check_out"))
                            ->where('status', 1);

                        if ($__update) {
                            $q->where('room_no', $room_id);
                        } else {
                            $q->where('guest_id', $guest_id);
                        }
                    })
                    ->orderBy('sno', 'DESC')
                    ->first();

                if ($GuestCheckinDetails) {
                    $event->room_id = $GuestCheckinDetails->room_id;
                    $event->guest_id = $GuestCheckinDetails->guest_id;
                    $__update = true;
                }
            }

            if (isset($event_update["issue"]) && !empty($event_update["issue"]) && $event->issue !== $event_update["issue"]) {
                $event->issue = $event_update["issue"];
                $__update = true;
            }

            if (isset($event_update["dept_id"]) && isset($event_update["tag_id"])) {
                $getDeptTag = DeptTag::select(['dept_tag_id'])
                    ->where('hotel_id', $hotel_id)
                    ->where('dept_id', $event_update["dept_id"])
                    ->where('tag_id', $event_update["tag_id"])
                    ->first();

                if ($getDeptTag) {
                    if ($event->dept_tag_id !== $getDeptTag->dept_tag_id) {
                        $event->dept_tag_id = $getDeptTag->dept_tag_id;
                        $__update = true;
                    }
                }
            }

            if (isset($event_update["status"]) && $event->status !== $event_update["status"]) {
                $event->status = $event_update["status"];
                $__update = true;
            }

            if (isset($event_update["priority"]) && $event->priority !== $event_update["priority"]) {
                $event->priority = $event_update["priority"];
                $__update = true;
            }

            if ($__update) {
                $event->save();
                $this->saveLogTracker([
                    'module_id' => 1,
                    'action'    => 'update',
                    'prim_id'   => $event->event_id,
                    'staff_id'  => $staff_id,
                    'date_time' => $now,
                    'comments'  => $event->issue,
                    'hotel_id'  => $hotel_id,
                    'type'      => 'API-V2'
                ]);
            }

            DB::commit();
            return response()->json([
                'update'        => true,
                'message'       => "",
                'description'   => null
            ], 201);
        } catch (\Exception $th) {
            DB::rollback();
            return response()->json([
                'update'        => false,
                'message'       => 'Bad request',
                'description'   =>  null
            ], 400);
        }
    }

    public function destroy($id, Request $request)
    {
        DB::beginTransaction();
        try {
            if (!$request->exists('hotel_id')) {
                return response()->json([
                    "delete"        => false,
                    "message"       => "Hotel id not provided",
                    "description"   => []
                ], 400);
            }
            $hotel_id = $request->hotel_id;
            $staff_id = $request->user()->staff_id;
            $this->configTimeZone($hotel_id);
            $now = date('Y-m-d H:i:s');

            if (!$this->validateHotelId($hotel_id, $staff_id)) {
                return response()->json([
                    "delete"        => false,
                    "message"       => "User does not have access to the hotel",
                    "description"   => []
                ], 400);
            }

            $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 1, $action = 'delete');
            if (!$permission) {
                return response()->json([
                    "delete"        => false,
                    "message"       => "User does not have permission to perform this action",
                    "description"   => []
                ], 400);
            }
            $event = Event::find($id);
            $event->active = 2;
            $event->save();

            $this->saveLogTracker([
                'module_id' => 1,
                'action'    => 'delete',
                'prim_id'   => $id,
                'staff_id'  => $staff_id,
                'date_time' => $now,
                'comments'  => '',
                'hotel_id'  => $hotel_id,
                'type'      => 'API'
            ]);

            DB::commit();
            return response()->json([
                'delete'        => true,
                'message'       => "",
                'description'   => null
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'delete'        => false,
                'message'       => 'Record not found',
                'description'   => null
            ], 400);
        }
    }

    public function indexByGuest(Request $request, $guest_id)
    {
        $paginate = isset($request->paginate) ? $request->paginate : 50;
        $staff_id = $request->user()->staff_id;
        $hotel_id = $request->hotel_id;

        if (!$request->exists('hotel_id')) {
            return response()->json(["error" => "Hotel id not provided"], 400);
        }

        if (!$this->validateHotelId($hotel_id, $staff_id)) {
            return response()->json([
                "error" => "User does not have access to the hotel"
            ], 400);
        }

        $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 1, $action = 'view');

        if (!$permission) {
            return response()->json([
                "error" => "User does not have permission to perform this action"
            ], 400);
        }

        $data = Event::select(['event_id', 'guest_id', 'room_id', 'issue', 'dept_tag_id'])
            ->with([
                'Room' => function ($query) {
                    return $query->select(['room_id', 'location']);
                },
                'DepTag.departament',
                'DepTag.tag'
            ])
            ->where(function ($query) use ($hotel_id, $guest_id) {
                $query
                    ->where('guest_id', $guest_id)
                    ->where('hotel_id', $hotel_id)
                    ->where('active', 1);
            })
            ->paginate($paginate);

        return response()->json($data, 200);
    }

    // public function eventTest(Request $request)
    // {
    //     // captura de parametros iniciales         
    //     $paginate = $request->paginate ?: 50;
    //     if (!is_numeric($paginate)) {
    //         return response()->json(["sintaxis error" => "Paginate is numeric type"], 400);
    //     }
    //     $staff_id = $request->user()->staff_id;
    //     // Validar hotel
    //     if (!$request->exists('hotel_id')) return response()->json(["error" => "Hotel id not provided"], 400);
    //     $hotel_id = $request->hotel_id;
    //     if (!is_numeric($hotel_id)) {
    //         return response()->json(["sintaxis error" => "Hotel ID is numeric type"], 400);
    //     }
    //     // Validar acceso al hotel x usuario
    //     if (!$this->validateHotelId($hotel_id, $staff_id)) return response()->json(["error" => "User does not have access to the hotel"], 400);
    //     // Validar que el usuario tenga permisos para realizar esta operacion
    //     $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 1, $action = 'view');
    //     if (!$permission) return response()->json(["error" => "User does not have permission to perform this action"], 400);
    //     // Configurar timezone y capturar fecha
    //     $this->configTimeZone($hotel_id);
    //     $now = date('Y-m-d H:i:s');

    //     $guest_id = $request->exists('guest_id') ? $request->guest_id : 0;

    //     if (!is_numeric($guest_id)) return response()->json(["error" => "Guest id is not a number "], 400);

    //     $query = Event::select([
    //         'event_id',
    //         'guest_id',
    //         'status',
    //         'owner',
    //         'room_id',
    //         'issue',
    //         'priority',
    //         'onhold',
    //         'created_by',
    //         'created_on',
    //         'updated_by',
    //         'updated_on',
    //         'dept_tag_id'
    //     ])
    //         ->with([
    //             'Room' => function ($q) {
    //                 return $q->select(['room_id', 'location']);
    //             },
    //             'DepTag.departament',
    //             'DepTag.tag',
    //             'CreatedByData' => function ($q) {
    //                 return $q->select(['staff_id', 'firstname', 'lastname', 'is_active', 'email']);
    //             },
    //             'UpdateByData' => function ($q) {
    //                 return $q->select(['staff_id', 'firstname', 'lastname', 'is_active', 'email']);
    //             },
    //             'OwnerData' => function ($q) {
    //                 return $q->select(['staff_id', 'firstname', 'lastname', 'is_active', 'email']);
    //             },
    //         ])
    //         ->where(function ($q) use ($hotel_id, $guest_id) {
    //             $q->where('hotel_id', $hotel_id)->where('active', 1);
    //             if ($guest_id > 0) $q->where('guest_id', $guest_id);
    //             return $q;
    //         })->orderBy('event_id', 'DESC');
    //     $data = $query->paginate($paginate);
    //     return response()->json($data, 200);
    // }
}
