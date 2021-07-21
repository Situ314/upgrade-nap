<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use Validator;
use Illuminate\Validation\Rule;


class HotelRoomsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $paginate = isset($request->paginate) ? $request->paginate : 50;
        $staff_id = $request->user()->staff_id;
        $hotel_id = $request->hotel_id;

        if($this->validateHotelId($hotel_id, $staff_id)){
            $this->configTimeZone($hotel_id);
            $now = date('Y-m-d H:i:s');      
            
            $query_left_join = 
            "SELECT 
                c.check_in, 
                c.check_out,
                c.hotel_id,
                c.room_no,
                c.status
            FROM guest_checkin_details AS c
            INNER JOIN guest_registration AS g ON g.guest_id = c.guest_id AND g.is_active = 1
            WHERE
                g.hotel_id = $hotel_id AND
                '$now' >= c.check_in AND 
                '$now' <= c.check_out AND 
                c.status = 1
            ";
            
            $query = DB::table('hotel_rooms as h');
            
            if(!isset($request->status)) {
                $query = $query->select(
                    'h.room_id',
                    'h.location', 
                    DB::raw("(CASE WHEN ('$now' >= gr.check_in and '$now' <= gr.check_out and gr.status = 1) THEN 'occupied' ELSE 'available' END) as status")
                );
            } else {
                $query = $query->select(
                    'h.room_id', 
                    'h.location'
                );
            }

            $query = $query->leftJoin(
                DB::raw("($query_left_join) as gr "), function($join) { $join->on('gr.hotel_id', '=', 'h.hotel_id')->on('h.room_id', '=', 'gr.room_no'); })
                ->where('h.hotel_id','=',$hotel_id)
                ->where('h.active','=', 1);

            if(isset($request->status)) {
                if($request->status == 'occupied' || $request->status == '0') {
                    $query = $query->whereRaw("('$now' >= gr.check_in) and ('$now' <= gr.check_out) and gr.status = 1");
                } else if(($request->status == 'available') || $request->status == '1') {
                    $query = $query->whereNull('gr.check_out');
                }
            }else {
                $query = $query->select(
                    'h.room_id',
                    'h.location', 
                    DB::raw("(CASE WHEN ('$now' >= gr.check_in and '$now' <= gr.check_out and gr.status = 1) THEN 'occupied' ELSE 'available' END) as status")
                );
            }

            $data = $query
                ->orderBy('status', 'desc')
                ->orderBy('location', 'desc')
                ->paginate($paginate,['*']);
            
            return response()->json( $data, 200 );
        }
        return response()->json( [], 400 );
    }

    public function room_available(Request $request) 
    {
        $paginate = isset($request->paginate) ? $request->paginate : 50;
        $staff_id = $request->user()->staff_id;
        $hotel_id = $request->hotel_id;

        if($this->validateHotelId($hotel_id, $staff_id)){
            $this->configTimeZone($hotel_id);
            $now = date('Y-m-d H:i:s');      
            
            $query_left_join = 
            "SELECT 
                c.check_in, 
                c.check_out,
                c.hotel_id,
                c.room_no,
                c.status
            FROM guest_checkin_details AS c
            INNER JOIN guest_registration AS g ON g.guest_id = c.guest_id AND g.is_active = 1
            WHERE g.hotel_id = $hotel_id AND
                ('$now' >= c.check_in) AND ('$now' <= c.check_out) AND c.status = 1";
            
            $data = DB::table('hotel_rooms as h')
                ->select('h.room_id','h.location')
                ->leftJoin(DB::raw("($query_left_join) as gr "), function($join) {
                    $join->on('gr.hotel_id', '=', 'h.hotel_id')->on('h.room_id', '=', 'gr.room_no');
                })
                ->where('h.hotel_id','=',$hotel_id)
                ->where('h.active','=', 1)
                ->whereNull('gr.check_out')
                ->paginate($paginate);
            
            return response()->json( $data, 200 );
        }
        return response()->json( [], 400 );
    }

    // public function room_occupied(Request $request) {
    //     $paginate = isset($request->paginate) ? $request->paginate : 50;
    //     $staff_id = $request->user()->staff_id;
    //     $hotel_id = $request->hotel_id;

    //     if($this->validateHotelId($hotel_id, $staff_id)){
    //         $this->configTimeZone($hotel_id);
    //         $now = date('Y-m-d H:i:s');
            
    //         $data = DB::table('hotel_rooms as h')
    //             ->select('h.room_id','h.location')
    //             ->leftJoin(DB::raw("(SELECT 
    //                 c.check_in,
    //                 c.check_out,
    //                 c.hotel_id,
    //                 c.room_no,
    //                 c.status
    //             FROM guest_checkin_details as c
    //             inner join guest_registration as g on g.guest_id = c.guest_id and g.is_active = 1
    //             WHERE g.hotel_id = $hotel_id and
    //                 ('$now' >= c.check_in) and ('$now' <= c.check_out) and c.status = 1) as gr "), function($join){
    //                     $join->on('gr.hotel_id', '=', 'h.hotel_id')->on('h.room_id', '=', 'gr.room_no');
    //                 })
    //             ->where('h.hotel_id','=',$hotel_id)
    //             ->where('h.active','=', 1)
    //             ->whereRaw("('$now' >= gr.check_in) and ('$now' <= gr.check_out) and gr.status = 1")
    //             //->whereNull('gr.check_out')
    //             //->toSql();
    //             ->paginate($paginate);
            
    //         return response()->json( $data, 200 );
    //     }
    //     return response()->json( [], 400 );
    // }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            if(!isset($request->hotel_rooms)){
                return response()->json([ 
                    'create' => false, 
                    'room_id' => 0,
                    "message" => "hotel rooms, data not provided" ,
                    "description" => []
                ], 400);

            }
            $hotel_rooms = $request->hotel_rooms;
            
            $this->configTimeZone($hotel_rooms["hotel_id"]);
            
            if(!$this->validateHotelId($hotel_rooms["hotel_id"],$request->user()->staff_id)){
                return response()->json([
                    'create' => false,
                    'room_id' => 0,
                    "message" => "the hotel_id does not belong to the current user",
                    "description" => []
                ], 400);
            }
            
            $hotel_id = $hotel_rooms["hotel_id"];

            $validation = Validator::make($hotel_rooms,[
                'hotel_id' => 'integer|required|exists:hotels',
                'location' => [
                    "string",
                    "required",
                    Rule::unique('hotel_rooms')->where('hotel_id', $hotel_id)->where('active', 1)
                ]
            ]);
            if($validation->fails()){
                return response()->json([ 
                    'create' => false, 
                    'room_id' => 0,
                    "message" => "hotel_rooms object, failed validation",
                    "description" => $validation->errors()
                 ], 400);
            }
            $hotel_rooms["location_type_id"] = null;
            $hotel_rooms["created_by"] = $request->user()->staff_id;
            $hotel_rooms["created_on"] = date('Y-m-d H:i:s');
            $hotel_rooms["updated_on"] = null;
            $hotel_rooms["updated_by"] = null;
            $hotel_rooms["active"] = 1;
            $hotel_rooms["angel_view"] = 1;
            $hotel_rooms["device_token"] = '';
            $room_id = \App\Models\HotelRoom::create($hotel_rooms)->room_id;

            $this->saveLogTracker([
                'module_id' => 17,
                'action' => 'add',
                'prim_id' => $room_id,
                'staff_id'=> $request->user()->staff_id,
                'date_time' => date('Y-m-d H:i:s'),
                'comments' => '',
                'hotel_id' => $hotel_rooms["hotel_id"],
                'type' => 'API'
            ]);
            DB::commit();
            $success = true;
        } catch (\Exception $e) {
            $error = $e;
            $success = false;
            DB::rollback();
        }
        if ($success) {
            return response()->json([ 
                'create' => true, 
                'room_id' => $room_id,
                'message' => '',
                'description' => []
            ],201);
        }else{
            return response()->json([ 
                'create' => false,
                'room_id' => 0,
                'message' => 'Bad request',
                'description' =>  $error
            ], 400);
        }

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
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
        DB::beginTransaction();
        try {
            $hotel_rooms = \App\Models\HotelRoom::find($id);
            if($hotel_rooms){
                $this->configTimeZone($hotel_rooms->hotel_id);
            
                $hotel_rooms->location = $request->location;
                $hotel_rooms->updated_on = date('Y-m-d H:i:s');
                $hotel_rooms->updated_by = $request->user()->staff_id;
                $hotel_rooms->save();
                
                $this->saveLogTracker([
                    'module_id' => 17,
                    'action' => 'update',
                    'prim_id' => $hotel_rooms->room_id,
                    'staff_id'=> $request->user()->staff_id,
                    'date_time' => date('Y-m-d H:i:s'),
                    'comments' => '',
                    'hotel_id' => $hotel_rooms->hotel_id,
                    'type' => 'API'
                ]);

                DB::commit();
                $success = true;
            }else{
                return response()->json([ 
                    'update' => false,
                    'message' => 'Record not found',
                    'description' =>  [
                        "hotel_id not found"
                    ]
                ], 400);
            }
        } catch (\Exception $e) {
            
            $error = $e;
            $success = false;
            DB::rollback();

        }

        if ($success) {
            return response()->json([ 
                'update' => true, 
                'message' => '',
                'description' => []
            ],200);
        }else{
            return response()->json([ 
                'update' => false,
                'message' => 'Bad request',
                'description' =>  $error
            ], 400);
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
        DB::beginTransaction();
        try {
            $hotel_rooms = \App\Models\HotelRoom::find($id);
            
            $this->configTimeZone($hotel_rooms->hotel_id);

            $hotel_rooms->active = 0;
            $hotel_rooms->updated_on = date('Y-m-d H:i:s');
            $hotel_rooms->updated_by = $request->user()->staff_id;
            $hotel_rooms->save();
            
            $this->saveLogTracker([
                'module_id' => 17,
                'action' => 'delete',
                'prim_id' => $hotel_rooms->room_id,
                'staff_id'=> $request->user()->staff_id,
                'date_time' => date('Y-m-d H:i:s'),
                'comments' => '',
                'hotel_id' => $hotel_rooms->hotel_id,
                'type' => 'API'
            ]);

            DB::commit();
            $success = true;
        } catch (\Exception $e) {
            
            $error = $e;
            $success = false;
            DB::rollback();

        }

        if ($success) {
            return response()->json([ 
                'delete' => true, 
                'message' => '',
                'description' => []
            ],200);
        }else{
            return response()->json([ 
                'delete' => false,
                'message' => 'Bad request',
                'description' =>  $error
            ], 400);
        }

    }
}