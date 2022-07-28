<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\HotelRoom;
use DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Validator;

class HotelRoomsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $paginate = $request->paginate ?: 50;
        $staff_id = $request->user()->staff_id;

        if (! $request->exists('hotel_id')) {
            return response()->json([
                'error' => 'Hotel id not provided',
            ], 400);
        }

        $hotel_id = $request->hotel_id;

        if (! $this->validateHotelId($hotel_id, $staff_id)) {
            return response()->json([
                'error' => 'User does not have access to the hotel',
            ], 400);
        }

        $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 17, $action = 'view');

        if (! $permission) {
            return response()->json([
                'error' => 'User does not have permission to perform this action',
            ], 400);
        }

        $this->configTimeZone($hotel_id);
        $now = date('Y-m-d H:i:s');

        $query_left_join = "SELECT 
                c.check_in,
                c.check_out,
                c.hotel_id,
                c.room_no,
                c.status 
            FROM guest_checkin_details AS c 
            INNER JOIN guest_registration AS g ON 
                g.guest_id = c.guest_id AND 
                g.is_active = 1 
            WHERE 
                g.hotel_id = $hotel_id AND 
                '$now' >= c.check_in AND 
                '$now' <= c.check_out AND  
                c.status = 1
        ";

        $query = DB::table('hotel_rooms as h');

        if (! isset($request->status)) {
            $query = $query->select(
                'h.room_id',
                'h.location',
                DB::raw("(CASE WHEN ('$now' >= gr.check_in AND '$now' <= gr.check_out and gr.status = 1) THEN 'occupied' ELSE 'available' END) as status")
            );
        } else {
            $query = $query->select(
                'h.room_id',
                'h.location'
            );
        }

        $query = $query->leftJoin(
            DB::raw("($query_left_join) as gr "),
            function ($join) {
                $join->on('gr.hotel_id', '=', 'h.hotel_id')->on('h.room_id', '=', 'gr.room_no');
            }
        )
            ->where('h.hotel_id', '=', $hotel_id)
            ->where('h.active', '=', 1);

        if (isset($request->status)) {
            if ($request->status == 'occupied' || $request->status == '0') {
                $query = $query->whereRaw("('$now' >= gr.check_in) and ('$now' <= gr.check_out) and gr.status = 1");
            } elseif (($request->status == 'available') || $request->status == '1') {
                $query = $query->whereNull('gr.check_out');
            }
        } else {
            $query = $query->select(
                'h.room_id',
                'h.location',
                DB::raw("(CASE WHEN ('$now' >= gr.check_in and '$now' <= gr.check_out and gr.status = 1) THEN 'occupied' ELSE 'available' END) as status")
            );
        }

        $data = $query
            ->orderBy('status', 'desc')
            ->orderBy('location', 'desc')
            ->paginate($paginate, ['*']);

        return response()->json($data, 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! $request->exists('hotel_rooms')) {
            return response()->json([
                'create' => false,
                'message' => 'event object, data not provided',
                'description' => [],
            ], 400);
        }

        $hotel_rooms = [];

        /*
        {
            "hotel_rooms": {
                "hotel_id": 195,
                "location": "20001"
            }
        }

         {
            "hotel_id": 195,
            "hotel_rooms": [
                { "location": "20002" },
                { "location": "20003" },
                { "location": "20004" },
                { "location": "20005" }
            ]
        }
         */

        try {
            $request->hotel_rooms[0];
            $is_array = true;
        } catch (\Exception $e) {
            $is_array = false;
        }

        if ($is_array) {
            if (! $request->exists('hotel_id')) {
                return response()->json([
                    'create' => false,
                    'message' => 'Hotel id not provided',
                    'description' => [],
                ], 400);
            }
            $hotel_id = $request->hotel_id;
            $hotel_rooms = $request->hotel_rooms;
        } else {
            $hotel_id = $request->hotel_rooms['hotel_id'];
            $hotel_rooms[] = $request->hotel_rooms;
        }

        $staff_id = $request->user()->staff_id;

        if (! $this->validateHotelId($hotel_id, $staff_id)) {
            return response()->json([
                'create' => false,
                'message' => 'User does not have access to the hotel',
                'description' => [],
            ], 400);
        }

        $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 17, $action = 'create');
        if (! $permission) {
            return response()->json([
                'create' => false,
                'message' => 'User does not have permission to perform this action',
                'description' => [],
            ], 400);
        }

        $this->configTimeZone($hotel_id);
        $now = date('Y-m-d H:i:s');

        $success = [];
        $error = [];

        foreach ($hotel_rooms as $key => $value) {
            try {
                DB::beginTransaction();

                $room = collect($value);
                $room = $room->only([
                    'location',
                ]);
                $room = $room->all();

                $validation = Validator::make($room, [
                    'location' => [
                        'string',
                        'required',
                        Rule::unique('hotel_rooms')->where(function ($query) use ($hotel_id) {
                            $query
                                ->where('hotel_id', $hotel_id)
                                ->where('active', 1);
                        }),
                    ],
                ]);

                if ($validation->fails()) {
                    $error[] = [
                        'index' => $key,
                        'location' => $room['location'],
                        'error' => $validation->errors(),
                    ];
                    DB::rollback();
                } else {
                    $room['hotel_id'] = $hotel_id;
                    $room['location_type_id'] = 0;
                    $room['created_by'] = $staff_id;
                    $room['created_on'] = $now;
                    $room['updated_on'] = null;
                    $room['updated_by'] = null;
                    $room['active'] = 1;
                    $room['angel_view'] = 1;
                    $room['device_token'] = '';

                    $room_id = HotelRoom::create($room)->room_id;

                    $this->saveLogTracker([
                        'module_id' => 17,
                        'action' => 'add',
                        'prim_id' => $room_id,
                        'staff_id' => $staff_id,
                        'date_time' => $now,
                        'comments' => '',
                        'hotel_id' => $hotel_id,
                        'type' => 'API-v2',
                    ]);

                    $success[] = [
                        'index' => $key,
                        'room_id' => $room_id,
                    ];
                    DB::commit();
                }
            } catch (\Exception $e) {
                DB::rollback();
                $error[] = [
                    'index' => $key,
                    'location' => $room['location'],
                    'error' => $e,
                ];
            }
        }

        $http_code = 0;
        $create = false;

        if (count($success) == 0) {
            $http_code = 400;
        } else {
            $http_code = 200;
            $create = true;
        }

        if ($is_array) {
            return response()->json([

                'create' => $create,
                'success' => $success,
                'error' => $error,

            ], $http_code);
        } else {
            return response()->json([

                'create' => $create,
                'room_id' => $create ? $success[0]['room_id'] : 0,
                'message' => '',
                'description' => $create ? [] : $error[0]['error'],

            ], $http_code);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! $request->exists('hotel_id')) {
            return response()->json([
                'create' => false,
                'message' => 'Hotel id not provided',
                'description' => [],
            ], 400);
        }
        $hotel_id = $request->hotel_id;
        $staff_id = $request->user()->staff_id;

        if (! $request->exists('location')) {
            return response()->json([
                'create' => false,
                'message' => 'Location, data not provided',
                'description' => [],
            ], 400);
        }

        $location = [];

        try {
            $is_array = ! is_string($request->location);
        } catch (\Exception $e) {
            $is_array = false;
        }

        if ($is_array) {
            $location = $request->location;
        } else {
            $location[] = [
                'location' => $request->location,
                'room_id' => $id,
            ];
        }

        if (! $this->validateHotelId($hotel_id, $staff_id)) {
            return response()->json([
                'update' => false,
                'message' => 'User does not have access to the hotel',
                'description' => [],
            ], 400);
        }

        $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 17, $action = 'update');
        if (! $permission) {
            return response()->json([
                'create' => false,
                'message' => 'User does not have permission to perform this action',
                'description' => [],
            ], 400);
        }

        $this->configTimeZone($hotel_id);
        $now = date('Y-m-d H:i:s');

        $success = [];
        $error = [];

        // return response()->json([
        //     "location" => $location
        // ], 200);

        foreach ($location as $key => $value) {
            try {
                $room_new = collect($value);
                $room_new = $room_new->only([
                    'room_id',
                    'location',
                ]);
                $room_new = $room_new->all();

                $validation = Validator::make($room_new, [
                    'room_id' => [
                        'required',
                        Rule::exists('hotel_rooms')->where(function ($query) use ($hotel_id) {
                            $query
                                ->where('hotel_id', $hotel_id);
                        }),
                    ],
                    'location' => [
                        'string',
                        'required',
                        Rule::unique('hotel_rooms')->ignore((int) $room_new['room_id'], 'room_id')->where(function ($query) use ($hotel_id) {
                            $query
                                ->where('hotel_id', $hotel_id)
                                ->where('active', 1);
                        }),
                    ],
                ]);

                if ($validation->fails()) {
                    $error[] = [
                        'index' => $key,
                        'location' => $room['location'],
                        'error' => $validation->errors(),
                    ];
                }

                $room_id = $room_new['room_id'];

                $room = HotelRoom::find($room_id);

                if (! $room) {
                    $error[] = [
                        'index' => $key,
                        'room_id' => $room_id,
                        'error' => [
                            'room_id' => 'Record not found',
                        ],
                    ];
                } else {
                    DB::beginTransaction();

                    $room->location = $room_new['location'];
                    $room->updated_on = $now;
                    $room->updated_by = $staff_id;
                    $room->save();

                    $this->saveLogTracker([
                        'module_id' => 17,
                        'action' => 'update',
                        'prim_id' => $room_id,
                        'staff_id' => $staff_id,
                        'date_time' => $now,
                        'comments' => '',
                        'hotel_id' => $hotel_id,
                        'type' => 'API-v2',
                    ]);

                    $success[] = [
                        'index' => $key,
                        'room_id' => $room_id,
                    ];

                    DB::commit();
                }
            } catch (\Exception $e) {
                echo $e;
                DB::rollback();
                $error[] = [
                    'index' => $key,
                    'location' => [],
                    'error' => $e,
                ];
            }
        }

        $http_code = 0;
        $update = false;

        if (count($success) == 0) {
            $http_code = 400;
        } else {
            $http_code = 200;
            $update = true;
        }

        if ($is_array) {
            return response()->json([

                'update' => $update,
                'success' => $success,
                'error' => $error,

            ], $http_code);
        } else {
            return response()->json([
                'update' => $update,
                'message' => '',
                'description' => $update ? [] : $error[0]['error'],
            ], $http_code);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        if (! $request->exists('hotel_id')) {
            return response()->json([
                'delete' => false,
                'message' => 'Hotel id not provided',
                'description' => [],
            ], 400);
        }
        $hotel_id = $request->hotel_id;
        $staff_id = $request->user()->staff_id;

        $location = [];
        $is_array = false;

        if ($id == 'multiple') {
            if (! $request->exists('location')) {
                return response()->json([
                    'create' => false,
                    'message' => 'Location, data not provided',
                    'description' => [],
                ], 400);
            }
            $is_array = true;
            $location = $request->location;
        } else {
            $location[] = [
                'room_id' => $id,
            ];
        }

        if (! $this->validateHotelId($hotel_id, $staff_id)) {
            return response()->json([
                'update' => false,
                'message' => 'User does not have access to the hotel',
                'description' => [],
            ], 400);
        }

        $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 17, $action = 'delete');
        if (! $permission) {
            return response()->json([
                'create' => false,
                'message' => 'User does not have permission to perform this action',
                'description' => [],
            ], 400);
        }

        $this->configTimeZone($hotel_id);
        $now = date('Y-m-d H:i:s');

        $success = [];
        $error = [];

        foreach ($location as $key => $value) {
            try {
                $room = collect($value);
                $room = $room->only([
                    'room_id',
                ]);
                $room = $room->all();

                $validation = Validator::make($room, [
                    'room_id' => [
                        'required',
                        Rule::exists('hotel_rooms')->where(function ($query) use ($hotel_id) {
                            $query
                                ->where('hotel_id', $hotel_id);
                        }),
                    ],
                ]);

                if ($validation->fails()) {
                    $error[] = [
                        'index' => $key,
                        'location' => $room['room_id'],
                        'error' => $validation->errors(),
                    ];
                } else {
                    $room_id = $room['room_id'];
                    $room = HotelRoom::find($room_id);

                    if (! $room) {
                        $error[] = [
                            'index' => $key,
                            'room_id' => $room_id,
                            'error' => [
                                'room_id' => 'Recordn ot found',
                            ],
                        ];
                    }

                    DB::beginTransaction();

                    $room->active = 0;
                    $room->save();

                    $this->saveLogTracker([
                        'module_id' => 17,
                        'action' => 'delete',
                        'prim_id' => $room_id,
                        'staff_id' => $staff_id,
                        'date_time' => $now,
                        'comments' => '',
                        'hotel_id' => $hotel_id,
                        'type' => 'API-v2',
                    ]);

                    $success[] = [
                        'index' => $key,
                        'room_id' => $room_id,
                    ];

                    DB::commit();
                }
            } catch (\Exception $e) {
                echo $e;
                DB::rollback();
                $error[] = [
                    'index' => $key,
                    'room_id' => $room['room_id'],
                    'error' => $e,
                ];
            }

            $http_code = 0;
            $delete = false;

            if (count($success) == 0) {
                $http_code = 400;
            } else {
                $http_code = 200;
                $delete = true;
            }
        }
        if ($is_array) {
            return response()->json([
                'delete' => $delete,
                'success' => $success,
                'error' => $error,

            ], $http_code);
        } else {
            return response()->json([
                'delete' => $delete,
                'message' => '',
                'description' => $delete ? [] : $error[0]['error'],
            ], $http_code);
        }

        DB::beginTransaction();
        try {
            $hotel_rooms = HotelRoom::find($id);

            $this->configTimeZone($hotel_rooms->hotel_id);

            $hotel_rooms->active = 0;
            $hotel_rooms->updated_on = date('Y-m-d H:i:s');
            $hotel_rooms->updated_by = $request->user()->staff_id;
            $hotel_rooms->save();

            $this->saveLogTracker([
                'module_id' => 17,
                'action' => 'delete',
                'prim_id' => $hotel_rooms->room_id,
                'staff_id' => $request->user()->staff_id,
                'date_time' => date('Y-m-d H:i:s'),
                'comments' => '',
                'hotel_id' => $hotel_rooms->hotel_id,
                'type' => 'API-v2',
            ]);

            DB::commit();
            $success = true;
        } catch (\Exception $e) {
            $error = $e;
            $success = false;
            DB::rollback();
        }
    }
}
