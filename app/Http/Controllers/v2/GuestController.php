<?php

namespace App\Http\Controllers\v2;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use DateTime;
use Validator;
use Illuminate\Validation\Rule;
use Illuminate\Filesystem\Filesystem as File;
use \App\Models\GuestRegistration;
use \App\Models\GuestCheckinDetails;
use \App\Models\HotelRoom;
use \App\Models\IntegrationsGuestInformation;

class GuestController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
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
        if (!is_numeric($hotel_id)) {
            return response()->json(["sintaxis error" => "Hotel ID is numeric type"], 400);
        }
        // Validar acceso al hotel x usuario
        if (!$this->validateHotelId($hotel_id, $staff_id)) return response()->json(["error" => "User does not have access to the hotel"], 400);
        // Validar que el usuario tenga permisos para realizar esta operacion
        $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 8, $action = 'view');
        if (!$permission) return response()->json(["error" => "User does not have permission to perform this action"], 400);
        // Configurar timezone y capturar fecha
        $this->configTimeZone($hotel_id);
        $now = date('Y-m-d H:i:s');
        // Query
        $queryCol = [
            'guest_registration.guest_id',
            'integrations_guest_information.guest_number',
            'guest_registration.firstname',
            'guest_registration.lastname',
            'guest_registration.email_address',
            'guest_registration.phone_no',
            'guest_registration.angel_status',
        ];
        $query = GuestRegistration::select($queryCol)
            ->leftJoin('integrations_guest_information', function ($join) use ($hotel_id) {
                $join->on('integrations_guest_information.guest_id', '=', 'guest_registration.guest_id')
                    ->where('integrations_guest_information.hotel_id', $hotel_id);
            })
            ->where('guest_registration.hotel_id', $hotel_id)
            ->where('is_active', 1);

        if ($request->exists('status')) {
            $status = $request->status;
            if (!is_numeric($status)) {
                return response()->json(["sintaxis error" => "status is numeric type"], 400);
            }

            $query = $query->whereHas('GuestCheckingDetail', function ($q) use ($status) {
                return $q->where('status', $status);
            })->with([
                'GuestCheckingDetail' => function ($q) use ($status) {
                    return $q->select([
                        'sno',
                        'status',
                        'room_no',
                        'check_in',
                        'check_out',
                        'reservation_number',
                        'guest_id',
                    ])
                        ->where('status', $status)
                        ->orderBy('sno', 'DESC')
                        ->get();
                },
                'GuestCheckingDetail.Room' => function ($q) {
                    return $q->select(['room_id', 'location']);
                }
            ]);
        } else {
            $query = $query->with([
                'GuestCheckingDetail' => function ($q) {
                    return $q->select([
                        'sno',
                        'status',
                        'room_no',
                        'check_in',
                        'check_out',
                        'reservation_number',
                        'guest_id',
                    ])
                        ->orderBy('sno', 'DESC')
                        ->get();
                },
                'GuestCheckingDetail.Room' => function ($q) {
                    return $q->select(['room_id', 'location']);
                }
            ]);
        }



        if (isset($request->filter)) {
            $filter = $request->filter;
            $query = $query->where(function ($q) use ($queryCol, $filter) {
                foreach ($queryCol as $key => $value) {
                    if ($key == 0) {
                        $q->where($value, 'like', "%$filter%");
                    } else {
                        $q->orWhere($value, 'like', "%$filter%");
                    }
                }
                return $q;
            });
        }
        $data = $query->paginate($paginate);
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
        try {
            //\Log::info(json_encode($request));

            // Validate if the object was sent
            if (!$request->exists('guest_registration')) {
                return response()->json([
                    'create'        => false,
                    'guest_id'      => 0,
                    'message'       => "'guest_registration' object, data not provided",
                    'description'   => []
                ], 400);
            }

            // Validate if the object was sent
            if (!$request->exists('guest_checkin_details')) {
                return response()->json([
                    'create'        => false,
                    'guest_id'      => 0,
                    'message'       => "guest_checkin_details' object, data not provided",
                    'description'   => []
                ], 400);
            }

            $guest      = [];
            $checkin    = [];
            $success    = [];
            $error      = [];

            try {
                $request->guest_registration[0];
                $guest      = $request->guest_registration;
                $checkin    = $request->guest_checkin_details;
            } catch (\Exception $e1) {
                $guest[]    = $request->guest_registration;
                $checkin[]  = $request->guest_checkin_details;
            }

            $hotel_id = $guest[0]["hotel_id"];
            $staff_id = $request->user()->staff_id;

            //$this->writeLog("guest_v2", $hotel_id, "Start Guest Registration");

            $now = date('Y-m-d H:i:s');

            if ($this->validateHotelId($hotel_id, $staff_id)) {
                $this->configTimeZone($hotel_id);
                $create_no = 0;
                foreach ($guest as $key => $value) {
                    $_guest = $value;
                    $_checkin = $checkin[$key];

                    $validation = Validator::make($_guest, [
                        'hotel_id'      => 'required|numeric|exists:hotels',
                        'firstname'     => 'required|string',
                        'lastname'      => 'required|string',
                        'email_address' => [
                            'string',
                            'required_without:phone_no',
                            'required_if:phone_no,',
                            'nullable',
                            'regex:/([-0-9a-zA-Z.+_]+@[-0-9a-zA-Z.+_]+.[a-zA-Z]{2,4}|)/'
                        ],
                        'phone_no'      => [
                            'string',
                            'required_without:email_address',
                            'required_if:email_address,',
                            'regex:/(\+[0-9]{1,4}[0-9]{6,10}|)/',
                            'nullable'
                        ],
                        'angel_status'  => 'numeric|required|in:0,1',
                        'category'      => 'numeric|in:0,1,2,3,4,5',
                        'language'      => 'string|in:en,es',
                        'guest_number'  => 'string',
                        'comment'       => 'string'
                    ]);

                    if ($validation->fails()) {
                        $__err = [
                            "type_error"            => "guest_registration_fields",
                            "error"                 => $validation->errors(),
                            "guest_registration"    => $_guest,
                            "guest_checkin_details" => $_checkin
                        ];
                        $error[] = $__err;
                    } else {
                        $guest_registration = [
                            'hotel_id'      => $hotel_id,
                            'firstname'     => is_string($_guest['firstname'])              ? $_guest['firstname']      : '',
                            'lastname'      => is_string($_guest['lastname'])               ? $_guest['lastname']       : '',
                            'email_address' => array_key_exists('email_address', $_guest)   ? $_guest['email_address'] | ''  : '',
                            'phone_no'      => array_key_exists('phone_no', $_guest)        ? $_guest['phone_no'] | ''       : '',
                            'angel_status'  => isset($_guest['angel_status']) ? $_guest['angel_status'] : $this->validateAngelStatus($hotel_id),
                            'language'      => array_key_exists('language', $_guest)        ? $_guest['language']       : '',
                            'created_on'    => date('Y-m-d H:i:s'),
                            'created_by'    => $staff_id,
                            "address"       => '',
                            "state"         => '',
                            'zipcode'       => '',
                            'comment'       => isset($_guest['comment']) ? $_guest['comment'] : '',
                            'city'          => '',
                            'category'      => isset($_guest['category']) ? $_guest['category'] : 0,
                        ];

                        // $find_guest = GuestRegistration::where(function($query) use ($guest_registration) {
                        //     if(!empty($guest_registration['email_address']) && !empty($guest_registration['phone_no'])) {
                        //         return $query
                        //         ->where('email_address',$guest_registration['email_address'])
                        //         ->orWhere('phone_no',$guest_registration['phone_no']);
                        //     } else if(!empty($guest_registration['email_address']) && empty($guest_registration['phone_no'])) {
                        //         return $query
                        //         ->where('email_address',$guest_registration['email_address']);
                        //     }else {
                        //         return $query
                        //         ->where('phone_no',$guest_registration['phone_no']);
                        //     }
                        // })->where('is_active', 1)
                        // ->where('hotel_id', '!=', $hotel_id)
                        // ->first();

                        // if($find_guest && $find_guest->is_active == 1) {
                        //     $__err = [
                        //         "type_error" => "guest_registration_fields",
                        //         "error" => [
                        //             "email_address" => "The email_address is already registered in the system",
                        //             "phone_no" => "The phone_no is already registered in the system"
                        //         ],
                        //         "guest_registration"    => $_guest,
                        //         "guest_checkin_details" => $_checkin
                        //     ];
                        //     $error[] = $__err;

                        // } else {
                        $find_guest = GuestRegistration::where(function ($query) use ($guest_registration) {
                            if (!empty($guest_registration['email_address']) && !empty($guest_registration['phone_no'])) {
                                return $query
                                    ->where('email_address', $guest_registration['email_address'])
                                    ->orWhere('phone_no', $guest_registration['phone_no']);
                            } else if (!empty($guest_registration['email_address']) && empty($guest_registration['phone_no'])) {
                                return $query
                                    ->where('email_address', $guest_registration['email_address']);
                            } else {
                                return $query
                                    ->where('phone_no', $guest_registration['phone_no']);
                            }
                        })
                            ->where('is_active', 1)
                            ->where('hotel_id', '=', $hotel_id)
                            ->first();

                        DB::beginTransaction();

                        if ($find_guest) {
                            $guest_id = $find_guest->guest_id;
                            $find_guest->fill($guest_registration);
                            $find_guest->save();

                            if (isset($_guest['guest_number']) && !empty($_guest['guest_number'])) {
                                $IntegrationsGuestInformation = IntegrationsGuestInformation::where('hotel_id', $hotel_id)->where('guest_id', $guest_id)->first();
                                if ($IntegrationsGuestInformation) {
                                    $IntegrationsGuestInformation->guest_number = $_guest['guest_number'];
                                    $IntegrationsGuestInformation->save();
                                }
                            }
                        } else {

                            $guest_id = GuestRegistration::create($guest_registration)->guest_id;

                            if (isset($_guest['guest_number']) && !empty($_guest['guest_number'])) {
                                IntegrationsGuestInformation::create([
                                    "hotel_id"     => $hotel_id,
                                    "guest_id"      => $guest_id,
                                    "guest_number"  => $_guest['guest_number']
                                ]);
                            }
                        }

                        if (isset($_checkin["reservation_number"]) && !empty($_checkin["reservation_number"])) {
                            $__GuestCheckinDetails = GuestCheckinDetails::where('reservation_number', $_checkin["reservation_number"])->first();
                            if ($__GuestCheckinDetails) {
                                $__GuestRegistration = GuestRegistration::find($__GuestCheckinDetails->guest_id);
                                if ($__GuestRegistration) {
                                    if ($__GuestRegistration->is_active == 0) {
                                        $__GuestCheckinDetails->reservation_number = '';
                                        $__GuestCheckinDetails->save();
                                    }
                                }
                            }
                        }

                        $validation = Validator::make($_checkin, [
                            'room_no'               => 'required_without:room',
                            'room'                  => 'required_without:room_no',
                            'check_in'              => 'required|date_format:"Y-m-d H:i:s"',
                            'check_out'             => 'required|date_format:"Y-m-d H:i:s"|after:' . $_checkin['check_in'],
                            'comment'               => 'string',
                            'reservation_number'    => [
                                'string',
                                Rule::unique('guest_checkin_details')->where(function ($q) use ($hotel_id) {
                                    return $q->where('hotel_id', '=', $hotel_id)->where('status', 1);
                                })
                            ]
                        ]);

                        if ($validation->fails()) {

                            $__err = [
                                "type_error"            => "guest_checkin_details_fields",
                                "error"                 => $validation->errors(),
                                "guest_registration"    => $_guest,
                                "guest_checkin_details" => $_checkin
                            ];

                            $error[] = $__err;
                        } else {

                            $room = $this->findRoomId($hotel_id, $staff_id, array_key_exists('room_no', $_checkin) ?  $_checkin['room_no'] : $_checkin['room']);

                            $room_id = $room["room_id"];
                            $location = $room["room"];

                            $guest_checkin_details = [
                                'guest_id'              => $guest_id,
                                'hotel_id'              => $hotel_id,
                                'room_no'               => $room_id,
                                'comment'               => array_key_exists('comment', $_checkin) ? $_checkin['comment'] : '',
                                'check_in'              => $_checkin['check_in'],
                                'check_out'             => $_checkin['check_out'],
                                'reservation_number'    => isset($_checkin['reservation_number']) ? $_checkin['reservation_number'] : '',
                            ];

                            $now = date('Y-m-d H:i:s');


                            $check_in = $guest_checkin_details["check_in"];
                            $check_out =  $guest_checkin_details["check_in"];

                            $find_guest_checkin_details = GuestCheckinDetails::where('hotel_id', $hotel_id)
                                ->where('status', 1)
                                ->where('room_no', $room_id)
                                ->where(function ($query) use ($check_in, $check_out) {
                                    return $query
                                        ->whereRaw("'$check_in' BETWEEN check_in and check_out")
                                        ->orWhereRaw("'$check_out' BETWEEN check_in and check_out");
                                })->get();


                            if (count($find_guest_checkin_details) > 0) {
                                DB::rollback();

                                $__err = [
                                    "type_error"            => "busy_date_range",
                                    "error"                 => ["check_in" => ["Room $location is in use in this date range"]],
                                    "records"               => $find_guest_checkin_details,
                                    "guest_registration"    => $_guest,
                                    "guest_checkin_details" => $_checkin
                                ];

                                $error[] = $__err;
                            } else {
                                $guest_checkin_details = GuestCheckinDetails::create($guest_checkin_details);
                                DB::commit();
                                $create_no++;

                                if ($guest_registration['angel_status'] == 1) {
                                    $_rs = $this->sendAngelInvitation($guest_registration['email_address'], $guest_registration['hotel_id'], $guest_registration['phone_no']);
                                }

                                $success[] = [
                                    "guest_id" => $guest_id,
                                    "guest_registration" => $_guest
                                ];

                                $this->saveLogTracker([
                                    'module_id' => 8,
                                    'action'    => 'add',
                                    'prim_id'   => $guest_id,
                                    'staff_id'  => $staff_id,
                                    'date_time' => $now,
                                    'comments'  => '',
                                    'hotel_id'  => $hotel_id,
                                    'type'      => 'API-v2'
                                ]);

                                // $this->sendHousekeeping($hotel_id,[
                                //     "action" => "create",
                                //     "hotel_id" => $hotel_id,
                                //     "guests" => [
                                //         "guest_id"  => $guest_id,
                                //         "check_in"  => $guest_checkin_details->check_in,
                                //         "check_out" => $guest_checkin_details->check_out
                                //     ]
                                // ]);
                            }
                        }
                        // } 
                    }
                }

                GuestCheckinDetails::where(function ($query) use ($hotel_id, $now) {
                    return $query->where('hotel_id', $hotel_id)
                        ->where('status', 1)
                        ->where('check_out', '<', $now);
                })->update(['status' => 0]);

                /*$this->writeLog("guest_v2", $hotel_id, "    save guest_checkin_details 1::".json_encode([
                    'create'    => $create_no > 0 ? true : false,
                    'message'   => '',
                    'success'   => $success,
                    'error'     => $error
                ]));*/

                return response()->json([
                    'create'    => $create_no > 0 ? true : false,
                    'message'   => '',
                    'success'   => $success,
                    'error'     => $error
                ], 200);
            } else {

                /*$this->writeLog("guest_v2", $hotel_id, "    save guest_checkin_details 2::".json_encode([ 
                    'create'    => false, 
                    'message'   => 'the hotel_id does not belong to the current user',                     
                    'success'   => $success,
                    'error'     => $error
                ]));*/

                GuestCheckinDetails::where(function ($query) use ($hotel_id, $now) {
                    return $query->where('hotel_id', $hotel_id)
                        ->where('status', 1)
                        ->where('check_out', '<', $now);
                })->update(['status' => 0]);

                return response()->json([
                    'create'    => false,
                    'message'   => 'the hotel_id does not belong to the current user',
                    'success'   => $success,
                    'error'     => $error
                ], 400);
            }

            //$this->writeLog("guest_v2", $hotel_id, "end Guest Registration");

        } catch (\Exception $e) {
            //$this->writeLog("guest_v2", $hotel_id, "Error::".$e);
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

        $staff_id       = $request->user()->staff_id;

        if ($id == 'multiple') {
            // Validate if the object was sent
            if (!$request->exists('guest_registration')) {
                return response()->json([
                    'update'        => false,
                    'message'       => "'guests' guest_registration, data not provided",
                    'description'   => []
                ], 400);
            }
            // Validate if the object was sent
            if (!$request->exists('guest_checkin_details')) {
                return response()->json([
                    'update'        => false,
                    'message'       => "'guests' guest_checkin_details, data not provided",
                    'description'   => []
                ], 400);
            }


            $guest_registration_arr = $request->guest_registration;
            $guest_checkin_details_arr = isset($request->guest_checkin_details) ? $request->guest_checkin_details : null;

            $error = [];
            $successArr = [];
            $success = false;
            $hotel_id = 0;

            foreach ($guest_registration_arr as $key => $value) {

                $guest_find = GuestRegistration::find($value['guest_id']);
                $hoel_id = $guest_find->hotel_id;

                $gr   = $value;
                $gcd  = null;
                if ($guest_checkin_details_arr) {
                    $gcd = $guest_checkin_details_arr[$key];
                }

                $rs = $this->updateGuest($guest_find, $gr, $gcd, $staff_id);
                if (!$rs['update']) {
                    $error[] = [
                        'guest_id' => $gr["guest_id"],
                        'error' => $rs
                    ];
                } else {
                    $success = true;
                    $successArr[] = [
                        'guest_id' => $gr["guest_id"],
                        'success' => $rs['success']
                    ];
                }
            }

            $now = date('Y-m-d H:i:s');
            // GuestCheckinDetails::where(function($query) use ($hotel_id, $now) {                
            //     return $query->where('hotel_id',$hotel_id)
            //     ->where('status', 1)
            //     ->where('check_out', '<', $now);
            // })->update([ 'status' => 0 ]);

            return response()->json([
                'update' => $success,
                'error' => $error,
                'success' => $successArr
            ], 200);
        } else {
            $guest_find = GuestRegistration::find($id);

            $hotel_id = $guest_find->hotel_id;
            // Validate if the object was sent
            if (!$request->exists('guest_registration')) {
                return response()->json([
                    'update'        => false,
                    'message'       => "'guest_registration' object, data not provided",
                    'description'   => []
                ], 400);
            }

            $guest_registration     = $request->guest_registration;
            $guest_checkin_details  = isset($request->guest_checkin_details) ? $request->guest_checkin_details : null;

            $now = date('Y-m-d H:i:s');

            GuestCheckinDetails::where(function ($query) use ($hotel_id, $now) {

                return $query->where('hotel_id', $hotel_id)
                    ->where('status', 1)
                    ->where('check_out', '<', $now);
            })->update(['status' => 0]);

            return response()->json($this->updateGuest($guest_find, $guest_registration, $guest_checkin_details, $staff_id), 200);
        }
    }

    public function show(Request $request, $id)
    {
        $staff_id = $request->user()->staff_id;
        /**
         * Validar hotel
         * */
        if (!$request->exists('hotel_id')) return response()->json(["error" => "Hotel id not provided"], 400);
        $hotel_id = $request->hotel_id;
        /**
         * Validar acceso al hotel x usuario
         */
        if (!$this->validateHotelId($hotel_id, $staff_id)) return response()->json(["error" => "User does not have access to the hotel"], 400);
        /**
         *  Validar que el usuario tenga permisos para realizar esta operacion
         */
        $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 8, $action = 'view');
        if (!$permission) return response()->json(["error" => "User does not have permission to perform this action"], 400);

        $data = GuestRegistration::select([
            'guest_registration.guest_id',
            'firstname',
            'lastname',
            'email_address',
            'phone_no',
            'integrations_guest_information.guest_number'
        ])
            ->leftJoin('integrations_guest_information', 'integrations_guest_information.guest_id', '=', 'guest_registration.guest_id')
            ->where('guest_registration.hotel_id', $hotel_id)
            ->where('guest_registration.guest_id', $id)
            ->with([
                'GuestCheckingDetail'       => function ($q) {
                    $q->select(['sno', 'guest_id', 'room_no', 'check_in', 'check_out', 'status'])->orderBy('sno', 'DESC')->get();
                },
                'GuestCheckingDetail.Room'  => function ($q) {
                    $q->select(['room_id', 'location']);
                }
            ])->first();

        return response()->json($data, 200);
    }

    public function show2(Request $request, $guest_number)
    {
        $staff_id = $request->user()->staff_id;
        /**
         * Validar hotel
         * */
        if (!$request->exists('hotel_id')) return response()->json(["error" => "Hotel id not provided"], 400);
        $hotel_id = $request->hotel_id;
        /**
         * Validar acceso al hotel x usuario
         */
        if (!$this->validateHotelId($hotel_id, $staff_id)) return response()->json(["error" => "User does not have access to the hotel"], 400);
        /**
         *  Validar que el usuario tenga permisos para realizar esta operacion
         */
        $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 8, $action = 'view');
        if (!$permission) return response()->json(["error" => "User does not have permission to perform this action"], 400);

        $data = GuestRegistration::select([
            'guest_registration.guest_id',
            'firstname',
            'lastname',
            'email_address',
            'phone_no',
            'integrations_guest_information.guest_number'
        ])
            ->leftJoin('integrations_guest_information', 'integrations_guest_information.guest_id', '=', 'guest_registration.guest_id')
            ->where('guest_registration.hotel_id', $hotel_id)
            ->where('integrations_guest_information.guest_number', $guest_number)
            ->with([
                'GuestCheckingDetail'       => function ($q) {
                    $q->select(['sno', 'guest_id', 'room_no', 'check_in', 'check_out', 'status'])->orderBy('sno', 'DESC')->get();
                },
                'GuestCheckingDetail.Room'  => function ($q) {
                    $q->select(['room_id', 'location']);
                }
            ])->first();

        return response()->json($data, 200);
    }

    public function updateGuest($guest_find, $guest_registration, $guest_checkin_details, $staff_id)
    {
        try {
            $guest_find_id  = $guest_find->guest_id;
            $hotel_id       = $guest_find->hotel_id;

            $guest_to_validate = [
                'hotel_id'      => $guest_find->hotel_id,
                'firstname'     => array_key_exists('firstname', $guest_registration)     ? $guest_registration['firstname']     : $guest_find->firstname,
                'lastname'      => array_key_exists('lastname', $guest_registration)      ? $guest_registration['lastname']      : $guest_find->lastname,
                'email_address' => array_key_exists('email_address', $guest_registration) ? $guest_registration['email_address'] : $guest_find->email_address,
                'phone_no'      => array_key_exists('phone_no', $guest_registration)      ? $guest_registration['phone_no']      : $guest_find->phone_no,
                'angel_status'  => array_key_exists('angel_status', $guest_registration)  ? $guest_registration['angel_status']  : $guest_find->angel_status,
                'language'      => array_key_exists('language', $guest_registration)      ? $guest_registration['language']      : $guest_find->language,
                'updated_on'    => date('Y-m-d H:i:s'),
                'updated_by'    => $staff_id,
                'created_on'    => $guest_find->created_on,
                'created_by'    => $guest_find->created_by,
                "address"       => $guest_find->address,
                "state"         => $guest_find->state,
                'zipcode'       => $guest_find->zipcode,
                'comment'       => $guest_find->comment,
                'city'          => $guest_find->city
            ];

            $validation = Validator::make($guest_to_validate, [
                'hotel_id'      => 'required|numeric|exists:hotels',
                'firstname'     => 'required|string',
                'lastname'      => 'required|string',
                'email_address' =>  [
                    'string',
                    'required_without:phone_no',
                    'regex:/[-0-9a-zA-Z.+_]+@[-0-9a-zA-Z.+_]+.[a-zA-Z]{2,4}/',
                    Rule::unique('guest_registration')->where(function ($query) use ($hotel_id, $guest_find_id) {
                        return $query->where('is_active', 1)
                            ->where('hotel_id', '=', $hotel_id)
                            ->where('guest_id', '!=', $guest_find_id);
                    })
                ],
                'phone_no'      => [
                    'string',
                    'required_without:email_address',
                    'regex:/\+[0-9]{1,4}[0-9]{6,10}/',
                    Rule::unique('guest_registration')->where(function ($query) use ($hotel_id, $guest_find_id) {
                        return $query->where('is_active', 1)
                            ->where('hotel_id', '=', $hotel_id)
                            ->where('guest_id', '!=', $guest_find_id);
                    })
                ],
                'angel_status'  => 'numeric|required|in:0,1',
                'language'      => 'string|in:en,es'
            ]);

            if ($validation->fails()) {
                $message = 'Error updating fields';
                return [
                    'update'        => false,
                    'message'       => $message,
                    'description'   => $validation->errors(),
                ];
            } else {
                // $find_guest = GuestRegistration::where(function($query) use ($guest_to_validate, $guest_find_id) {
                //     if(!empty($guest_to_validate['email_address']) && !empty($guest_to_validate['phone_no'])) {
                //         return $query
                //         ->where('email_address',$guest_to_validate['email_address'])
                //         ->orWhere('phone_no',$guest_to_validate['phone_no'])
                //         ->where('guest_id', '!=', $guest_find_id);
                //     } else if(!empty($guest_to_validate['email_address']) && empty($guest_to_validate['phone_no'])) {
                //         return $query
                //         ->where('email_address',$guest_to_validate['email_address'])
                //         ->where('guest_id', '!=', $guest_find_id);
                //     }else {
                //         return $query
                //         ->where('phone_no',$guest_to_validate['phone_no'])
                //         ->where('guest_id', '!=', $guest_find_id);
                //     }
                // })
                // ->where('is_active', 1)
                // ->where('hotel_id', '!=', $hotel_id)
                // ->first();

                // if($find_guest && $find_guest->is_active == 1){
                //     $message = 'information is already in the system';
                //     return [ 
                //         'update'        => false,
                //         'message'       => $message,
                //         'description'   => [
                //             "email_address" => "The email_address is already registered in the system",
                //             "phone_no" => "The phone_no is already registered in the system",
                //         ],
                //     ];
                // } else {
                DB::beginTransaction();
                $guest_find->fill($guest_to_validate);
                $guest_find->save();

                if ($guest_checkin_details != null) {

                    $validation = Validator::make($guest_checkin_details, [
                        'sno'           => 'required|numeric',
                        'room_no'       => 'required_without:room|exists:hotel_rooms,room_id',
                        'room'          => 'required_without:room_no|exists:hotel_rooms,location',
                        'check_in'      => 'required|date_format:"Y-m-d H:i:s"',
                        'check_out'     => 'required|date_format:"Y-m-d H:i:s"|after:' . $guest_checkin_details['check_in'],
                        'comment'       => 'string'
                    ]);

                    if ($validation->fails()) {
                        $message = 'Error updating fields';
                        return [
                            'update'        => false,
                            'message'       => $message,
                            'description'   => $validation->errors(),
                        ];
                    }


                    $room_id =
                        array_key_exists('room_no', $guest_checkin_details) ?
                        $guest_checkin_details['room_no'] :
                        $this->findRoomId($hotel_id, $staff_id, $guest_checkin_details['room'])["room_id"];

                    $sno = $guest_checkin_details['sno'];

                    $guest_checkin_details_find = GuestCheckinDetails::where('guest_id', $guest_find_id)
                        ->where('sno', $sno)
                        ->where('status', 1)
                        ->first();


                    if ($guest_checkin_details_find) {

                        $guest_checkin_details_arr = GuestCheckinDetails::where(function ($query) use ($room_id, $hotel_id, $guest_checkin_details, $sno) {
                            return $query->where('hotel_id', $hotel_id)
                                ->where('status', 1)
                                ->where('sno', '!=', $sno)
                                ->where('room_no', $room_id)
                                ->whereRaw("'" . $guest_checkin_details["check_in"] . "' >= check_in and '" . $guest_checkin_details["check_in"] . "' <= check_out");
                        })->get();



                        if (count($guest_checkin_details_arr) == 0) {

                            $a = $guest_checkin_details_find;
                            $guest_checkin_details_find->fill($guest_checkin_details);
                            $guest_checkin_details_find->save();

                            DB::commit();
                            $message = '';
                            return [
                                'update'    => true,
                                'success'   => [
                                    "guest_registration"    => $guest_registration,
                                    "guest_checkin_details" => $guest_checkin_details,
                                ]
                            ];
                        } else {
                            DB::rollback();
                            $message = 'invalid date range change';
                            return [
                                'update'        => false,
                                'message'       => $message,
                                'description'   => null,
                            ];
                        }
                    } else {
                        DB::rollback();
                        $message = 'Record not found';
                        return [
                            'update'        => false,
                            'message'       => $message,
                            'description'   => null,
                        ];
                    }
                } else {
                    DB::commit();
                    return [
                        'update'    => true,
                        'success'   => [
                            "guest_registration"    => $guest_registration,
                        ]
                    ];
                }
                // }
            }
        } catch (\Exception $e) {
            echo $e;
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
        $staff_id = $request->user()->staff_id;
        if ($id == 'multiple') {
            $error = [];
            $count = 0;
            $guests = $request->guests;

            foreach ($guests as $key => $guest) {
                $_id = $guest["guest_id"];
                $rs = $this->destroyGuest($_id, $staff_id);
                if ($rs["delete"]) {
                    $count++;
                } else {
                    $error[] = [
                        "guest_id" => $_id,
                        "error" => $rs["error"]
                    ];
                }
            }

            return [
                "delete" => $count > 0 ? true : false,
                "error" => $error
            ];
        } else {
            return $this->destroyGuest($id, $staff_id);
        }
    }

    private function destroyGuest($guest_id, $staff_id)
    {
        $guest = GuestRegistration::find($guest_id);

        if ($guest) {
            DB::beginTransaction();
            $guest->is_active = 0;
            $guest->angel_status = 0;
            $guest->save();

            $chekin = GuestCheckinDetails::where('guest_id', $guest_id)->get();
            foreach ($chekin as $key => $value) {
                $value->status = 0;
                $value->save();
            }
            $hotel_id = $guest->hotel_id;
            $this->configTimeZone($hotel_id);
            $this->saveLogTracker([
                'module_id' => 8,
                'action'    => 'delete',
                'prim_id'   => $guest_id,
                'staff_id'  => $staff_id,
                'date_time' => date('Y-m-d H:i:s'),
                'comments'  => '',
                'hotel_id'  => $hotel_id,
                'type'      => 'API'
            ]);
            DB::commit();

            return [
                "delete" => true
            ];
        }

        return [
            "delete" => false,
            "error" => [
                "guest_id" => ["Recod not found"]
            ]
        ];
    }

    public function closeGuestCheckinDetails(Request $request)
    {
        DB::beginTransaction();
        try {
            /* configure timezone  by hotel */
            $this->configTimeZone($request->hotel_id);

            $now = date('Y-m-d H:i:s');

            $guest_id = $request->guest_id;
            $sno = $request->sno;


            $guest_checkin_details = GuestCheckinDetails::where(function ($query) use ($guest_id, $sno) {
                $query->where('guest_id', $guest_id)->where('sno', $sno);
            })->first();


            if ($guest_checkin_details) {

                $guest_checkin_details->status = 0;
                $guest_checkin_details->check_out = $now;
                $guest_checkin_details->save();

                $success = true;
                DB::commit();
            } else {

                $success = false;
                DB::rollback();
                return response()->json([
                    'close' => false,
                    'message' => 'Record not found',
                    'description' =>  []
                ], 400);
            }
        } catch (\Exception $e) {
            $success = false;
            DB::rollback();
        }

        if ($success) {
            return response()->json(['close' => true, 'message' => '', 'description' => null], 200);
        } else {
            DB::rollback();
            return response()->json(['close' => false, 'message' => 'Bad request', 'description' =>  $error], 400);
        }
    }

    public function checkoutGuest($hotel_id, $guest_id, $room_id)
    {

        $this->configTimeZone($hotel_id);
        $now = date('Y-m-d H:i:s');

        $GuestCheckinDetails = GuestCheckinDetails::where('hotel_id', $hotel_id)
            ->where('guest_id', $guest_id)
            ->where('room_no', $room_id)
            ->first();

        if ($GuestCheckinDetails) {
            $GuestCheckinDetails->update([
                "check_out"             => $now,
                "status"                => 0,
                "reservation_status"    => 3
            ]);

            return response()->json([
                'checkout_guest' => true
            ], 200);
        }

        return response()->json([
            'checkout_guest' => false
        ], 200);
    }

    public function checkoutRoom($hotel_id, $room_id)
    {
        $this->configTimeZone($hotel_id);
        $now = date('Y-m-d H:i:s');

        $GuestCheckinDetails = GuestCheckinDetails::where('hotel_id', $hotel_id)
            ->where('room_no', $room_id);

        if ($GuestCheckinDetails) {
            $GuestCheckinDetails->update([
                "check_out"             => $now,
                "status"                => 0,
                "reservation_status"    => 3
            ]);

            return response()->json([
                'checkout_room' => true
            ], 200);
        }

        return response()->json([
            'checkout_room' => false
        ], 200);
    }

    public function validateEmail(Request $request, $hotel_id, $email)
    {
        if (!$this->validateHotelId($hotel_id, $request->user()->staff_id)) {
            return response()->json([
                'exists' => null,
                "message" => "the hotel_id does not belong to the current user",
            ], 400);
        }
        $guest_registration = \App\Models\GuestRegistration::where('email_address', $email)
            ->where('hotel_id', $hotel_id)
            ->where('is_active', 1)
            ->first();
        if (!isset($guest_registration)) {
            return response()->json([
                'exists' => false,
                "message" => "",
            ], 200);
        } else {
            return response()->json([
                'exists' => true,
                "guest_registration" => $guest_registration
            ], 200);
        }
    }

    public function validatePhoneNumber(Request $request, $hotel_id, $phone_numer)
    {
        if (!$this->validateHotelId($hotel_id, $request->user()->staff_id)) {
            return response()->json([
                'exists' => null,
                "message" => "the hotel_id does not belong to the current user",
            ], 400);
        }
        $guest_registration = \App\Models\GuestRegistration::where('phone_no', $phone_numer)
            ->where('is_active', 1)
            ->where('hotel_id', $hotel_id)
            ->first();
        if (!isset($guest_registration)) {
            return response()->json([
                'exists' => false,
                "message" => "",
            ], 200);
        } else {
            return response()->json([
                'exists' => true,
                "guest_registration" => $guest_registration
            ], 200);
        }
    }

    private function sendHousekeeping($hotel_id, $data)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://concierge-dev1.mynuvola.com/index.php/housekeeping/pmsGuestChange",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache"
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        //$this->writeLog("guest_v2", $hotel_id, "Curl Response::".json_encode($response));
        //$this->writeLog("guest_v2", $hotel_id, "Curl Error::".json_encode($err));
        //$this->writeLog("guest_v2", $hotel_id, "End Housekeeping Status::".json_encode($data));
    }

    public function findRoomId($hotel_id, $staff_id, $location)
    {

        $room = HotelRoom::where('hotel_id', $hotel_id)
            ->where(function ($query) use ($location) {
                return
                    $query
                    ->where('location', $location)
                    ->orWhere('room_id', $location);
            })->first();

        if ($room) {

            $room->active = 1;
            $room->save();

            return [
                "room_id" => $room->room_id,
                "room" => $room->location
            ];
        } else {
            $room = HotelRoom::create([
                'hotel_id'      => $hotel_id,
                'location'      => $location,
                'created_by'    => $staff_id,
                'created_on'    => date('Y-m-d H:i:s'),
                'updated_by'    => null,
                'updated_on'    => null,
                'active'        => 1,
                'angel_view'    => 1,
                'device_token'  => ''
            ]);

            $this->saveLogTracker([
                'hotel_id'  => $hotel_id,
                'staff_id'  => $staff_id,
                'prim_id'   => $room->room_id,
                'module_id' => 17,
                'action'    => 'add',
                'date_time' => date('Y-m-d H:i:s'),
                'comments'  => '',
                'type'      => 'API'
            ]);

            return [
                "room_id" => $room->room_id,
                "room" => $room->location
            ];
        }
    }

    // funcion para generar multiples reservas a un mismo huesped
    public function storeMultipe(Request $request)
    {
        try {
            // Capturar hotel id, por default el valor es null, en caso de no enviarlo
            $hotel_id = isset($request->hotel_id) ? $request->hotel_id : null;
            // Generar validaciones
            $validator = Validator::make(
                $request->all(), //Pasarle al metodo toda la informacion que se recibe por $request
                [
                    'hotel_id'              => 'required|numeric|exists:hotels',
                    'guest'                 => 'required',
                    'guest.firstname'       => 'required|string',
                    'guest.lastname'        => 'required|string',
                    'guest.email_address'   => [
                        'string',
                        'required_without:phone_no',
                        'required_if:phone_no,',
                        'nullable',
                        'regex:/([-0-9a-zA-Z.+_]+@[-0-9a-zA-Z.+_]+.[a-zA-Z]{2,4}|)/'
                    ],
                    'guest.phone_no'        => [
                        'string',
                        'required_without:email_address',
                        'required_if:email_address,',
                        'regex:/(\+[0-9]{1,4}[0-9]{6,10}|)/',
                        'nullable'
                    ],
                    'guest.angel_status'    => 'numeric|required|in:0,1',
                    'guest.category'        => 'numeric|in:0,1,2,3,4,5',
                    'guest.language'        => 'string|in:en,es',
                    'guest.guest_number'    => 'string',
                    'guest.comment'         => 'string',
                    'reservations'          => 'required|array', //array con toda la info de las reservas
                    'reservations.*.room'       => [
                        'string',
                        'required_without:reservations.*.room_no',
                        Rule::exists('hotel_rooms', 'location')->where(function ($q) use ($hotel_id) {
                            $q->where('hotel_id', $hotel_id)->where('active', 1);
                        })
                    ],
                    'reservations.*.room_no'    => [
                        'string',
                        'required_without:reservations.*.room',
                        Rule::exists('hotel_rooms', 'room_id')->where(function ($q) use ($hotel_id) {
                            $q->where('hotel_id', $hotel_id)->where('active', 1);
                        })
                    ],
                    'reservations.*.check_in'   => 'required|date_format:"Y-m-d H:i:s"',
                    'reservations.*.check_out'  => 'required|date_format:"Y-m-d H:i:s"|after:reservations.*.check_in',
                    'reservations.*.comment'    => 'string',
                    'reservations.*.reservation_number' => [
                        'string',
                        'distinct',
                        Rule::unique('guest_checkin_details')->where(function ($q) use ($hotel_id) {
                            $q->where('hotel_id', '=', $hotel_id)->where('status', 1);
                        })
                    ]
                ]
            );

            if ($validator->fails()) {
                return response()->json([
                    'create'        => false,
                    'message'       => "Error during the validation of the information",
                    'description'   => $validator->errors()
                ], 400);
            }

            $staff_id = $request->user()->staff_id;

            if (!$this->validateHotelId($hotel_id, $staff_id)) {
                return response()->json([
                    'create'        => false,
                    "message"       => "User does not have access to the hotel",
                    'description'   => null
                ], 400);
            }

            $this->configTimeZone($hotel_id);

            $guest = $request->guest;
            $reservations = $request->reservations;

            $email = array_key_exists('email_address', $guest) ? $guest['email_address'] : '';
            $phone = array_key_exists('phone_no', $guest) ? $guest['phone_no'] : '';
            $guest_number = array_key_exists('guest_number', $guest) ? $guest['guest_number'] : '';

            $guestData = [
                'hotel_id'      => $hotel_id,
                'firstname'     => $guest["firstname"],
                'lastname'      => $guest["lastname"],
                'email_address' => $email,
                'phone_no'      => $phone,
                'angel_status'  => isset($guest['angel_status']) ? (intval($guest['angel_status']) == 1 ? ($this->validateAngelStatus($hotel_id)) : 0) : 0,
                'language'      => array_key_exists('language', $guest) ? $guest['language'] : '',
                'comment'       => array_key_exists('comment', $guest) ? $guest['comment'] : '',
                'category'      => array_key_exists('category', $guest) ? $guest['category'] : 0,
            ];

            $searchGuest = null;
            if (!empty($guest_number)) {
                $intGuestInfo = \App\Models\IntegrationsGuestInformation::where('hotel_id', $hotel_id)->where('guest_number', $guest_number)->first();
                if ($intGuestInfo) {
                    $searchGuest = \App\Models\GuestRegistration::find($intGuestInfo->guest_id);
                }
            }
            if (is_null($searchGuest)) {
                $searchGuest = \App\Models\GuestRegistration::where('hotel_id', $hotel_id)
                    ->where('is_active', 1)
                    ->where(function ($q) use ($email, $phone) {
                        if (!empty($email) && !empty($phone)) {
                            $q->where('email_address', $email)->orWhere('phone_no', $phone);
                        } else if (!empty($email)) {
                            $q->where('email_address', $email);
                        } else {
                            $q->where('phone_no', $phone);
                        }
                    })->first();
            }

            $_guest = null;
            if ($searchGuest) {
                $guest_id = $searchGuest->guest_id;
                $searchGuest->fill($guestData);
                $searchGuest->save();
                $_guest = $searchGuest;
            } else {
                $guestData = array_merge($guestData, [
                    'created_on'    => date('Y-m-d H:i:s'),
                    'created_by'    => $staff_id,
                    'address'       => '',
                    'state'         => '',
                    'zipcode'       => '',
                    'city'          => '',
                    'is_active'     => 1
                ]);

                $guestCreated = \App\Models\GuestRegistration::create($guestData);
                $guest_id = $guestCreated->guest_id;
                $_guest = $guestCreated;

                if (!empty($guest_number)) {
                    \App\Models\IntegrationsGuestInformation::create([
                        'hotel_id'      => $hotel_id,
                        'guest_id'      => $guest_id,
                        'guest_number'  => $guest_number
                    ]);
                }

                $this->saveLogTracker([
                    'module_id' => 8,
                    'action'    => 'add',
                    'prim_id'   => $guest_id,
                    'staff_id'  => $staff_id,
                    'date_time' => date("Y-m-d H:i:s"),
                    'comments'  => 'create guest',
                    'hotel_id'  => $hotel_id,
                    'type'      => 'API-v2'
                ]);
            }
            $totalProcessed = 0;
            $unprocessedReservations = [];
            $processedReservations = [];
            foreach ($reservations as $key => $reservation) {
                $reservation_number = array_key_exists('reservation_number', $reservation) && !empty($reservation['reservation_number']) ? $reservation['reservation_number'] : null;
                if (!is_null($reservation_number)) {
                    $chekingDetail = \App\Models\GuestCheckinDetails::where('reservation_number', $reservation_number)->first();
                    if ($chekingDetail) {
                        if ($chekingDetail->is_active == 0) {
                            $chekingDetail->reservation_number = "";
                            $chekingDetail->save();
                        }
                    }
                }

                $room_id = array_key_exists('room_no', $reservation) && !empty($reservation["room_no"]) ? $reservation["room_no"] : "";
                if (empty($room_id)) {
                    $location = $reservation["room"];
                    $room = $this->findRoomId($hotel_id, $staff_id, $location);
                    $room_id = $room["room_id"];
                }

                $check_in   = $reservation['check_in'];
                $check_out  = $reservation['check_out'];

                $reservationData = [
                    'guest_id'              => $guest_id,
                    'hotel_id'              => $hotel_id,
                    'room_no'               => $room_id,
                    'comment'               => array_key_exists('comment', $reservation) ? $reservation['comment'] : "",
                    'check_in'              => $check_in,
                    'check_out'             => $check_out,
                    'reservation_number'    => array_key_exists('reservation_number', $reservation) ? $reservation['reservation_number'] : ''
                ];

                $guestReservationRegistred = \App\Models\GuestCheckinDetails::where('hotel_id', $hotel_id)
                    ->where('status', 1)
                    ->where('room_no', $room_id)
                    ->where('guest_id', $guest_id)
                    ->where(function ($q) use ($check_in, $check_out) {
                        $q
                            ->whereRaw("'$check_in' BETWEEN check_in and check_out")
                            ->orWhereRaw("'$check_out' BETWEEN check_in and check_out");
                    })->first();

                if ($guestReservationRegistred) {
                    $unprocessedReservations["reservation_$key"] = $reservation;
                } else {
                    $resrvationCreated = \App\Models\GuestCheckinDetails::create($reservationData);
                    $processedReservations["reservation_$key"] = ["sno" => $resrvationCreated->sno];
                    $totalProcessed++;
                    $this->saveLogTracker([
                        'module_id' => 8,
                        'action'    => 'add',
                        'prim_id'   => $resrvationCreated->sno,
                        'staff_id'  => $staff_id,
                        'date_time' => date("Y-m-d H:i:s"),
                        'comments'  => 'create reservation',
                        'hotel_id'  => $hotel_id,
                        'type'      => 'API-v2'
                    ]);
                }
            }
            return response()->json([
                'create' => true,
                'message' => "",
                'success' => [
                    "guest_id" => $_guest->guest_id,
                    "reservations" => !empty($processedReservations) ? $processedReservations : null,
                ],
                'error' => !empty($unprocessedReservations) ? $unprocessedReservations : null
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Error en storeMultipe");
            \Log::error($e);
            return response()->json([
                'create'        => false,
                "message"       => "Bad request",
                'description'   => null
            ], 400);
        }
    }
}
