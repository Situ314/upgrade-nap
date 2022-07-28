<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\GuestCheckinDetails;
use App\Models\GuestRegistration;
use App\Models\HotelRoom;
use DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Validator;

class GuestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        DB::enableQueryLog();
        // captura de parametros iniciales
        $paginate = $request->paginate ?: 50;
        if (! is_numeric($paginate)) {
            return response()->json(['sintaxis error' => 'Paginate is numeric type'], 400);
        }
        $staff_id = $request->user()->staff_id;
        // Validar hotel
        if (! $request->exists('hotel_id')) {
            return response()->json(['error' => 'Hotel id not provided'], 400);
        }
        $hotel_id = $request->hotel_id;
        if (! is_numeric($hotel_id)) {
            return response()->json(['sintaxis error' => 'Hotel ID is numeric type'], 400);
        }
        // Validar que el usuario tenga permisos para realizar esta operacion
        $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 8, $action = 'view');
        if (! $permission) {
            return response()->json([], 400);
        }

        if ($this->validateHotelId($hotel_id, $staff_id)) {
            $data = GuestRegistration::select([
                'guest_id',
                'firstname',
                'lastname',
                'email_address',
                'phone_no',
            ])
                ->where(function ($query) use ($hotel_id) {
                    $query
                        ->where('hotel_id', $hotel_id)
                        ->where('is_active', 1);
                })
                ->paginate($paginate);
            \Log::info(json_encode(DB::getQueryLog()));

            return response()->json($data, 200);
        }

        return response()->json([], 400);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        DB::enableQueryLog();

        DB::beginTransaction();
        try {
            if (! $request->exists('guest_registration')) {
                return response()->json([
                    'create' => false,
                    'guest_id' => 0,
                    'message' => "'guest_registration' object, data not provided",
                    'description' => [],
                ], 400);
            }

            if (! $request->exists('guest_checkin_details')) {
                return response()->json([
                    'create' => false,
                    'guest_id' => 0,
                    'message' => "guest_checkin_details' object, data not provided",
                    'description' => [],
                ], 400);
            }

            $gReg = $request->guest_registration;
            $gChe = $request->guest_checkin_details;

            $hotel_id = $gReg['hotel_id'];
            $staff_id = $request->user()->staff_id;

            $validation = Validator::make($gReg, [
                'hotel_id' => 'required|numeric|exists:hotels',
                'firstname' => 'required|string',
                'lastname' => 'required|string',
                'email_address' => [
                    'string',
                    'required_without:phone_no',
                    'required_if:phone_no,',
                    'nullable',
                    'regex:/([-0-9a-zA-Z.+_]+@[-0-9a-zA-Z.+_]+.[a-zA-Z]{2,4}|)/',
                    Rule::unique('guest_registration')->where(function ($query) use ($hotel_id) {
                        return $query->where('is_active', 1)->where('hotel_id', '!=', $hotel_id);
                    }),
                ],
                'phone_no' => [
                    'string',
                    'required_without:email_address',
                    'required_if:email_address,',
                    //'regex:/(\+[0-9]{1,4}[0-9]{6,10}|)/',
                    'nullable',
                    Rule::unique('guest_registration')->where(function ($query) use ($hotel_id) {
                        return $query->where('is_active', 1)->where('hotel_id', '!=', $hotel_id);
                    }),
                ],
                'angel_status' => 'numeric|in:0,1',
                'language' => 'string|in:en,es',
            ]);

            if ($validation->fails()) {
                return response()->json([
                    'create' => false,
                    'guest_id' => 0,
                    'message' => 'guest_registration object, failed validation',
                    'description' => $validation->errors(),
                ], 400);
            }

            if (! $this->validateHotelId($hotel_id, $staff_id)) {
                return response()->json([
                    'create' => false,
                    'guest_id' => 0,
                    'message' => 'the hotel_id does not belong to the current user',
                    'description' => [],
                ], 400);
            }

            $email_address = array_get($gReg, 'email_address', '');
            $email_address = filter_var($email_address, FILTER_VALIDATE_EMAIL) ? $email_address : '';

            $guest_registration = [
                'hotel_id' => $hotel_id,
                'email_address' => $email_address,
                'firstname' => stripslashes(array_get($gReg, 'firstname', '')),
                'lastname' => stripslashes(array_get($gReg, 'lastname', '')),
                'phone_no' => stripslashes(array_get($gReg, 'phone_no', '')),
                'language' => stripslashes(array_get($gReg, 'language', '')),
                'angel_status' => $this->validateAngelStatus($hotel_id),
                'created_on' => date('Y-m-d H:i:s'),
                'created_by' => $staff_id,
                'address' => '',
                'state' => '',
                'zipcode' => '',
                'comment' => '',
                'city' => '',
            ];

            // $find_guest = \App\Models\GuestRegistration::where(function ($query) use ($guest_registration) {
            //     if (
            //         !empty($guest_registration['email_address']) &&
            //         !empty($guest_registration['phone_no'])
            //     ) {
            //         return $query
            //             ->where('email_address', $guest_registration['email_address'])
            //             ->orWhere('phone_no', $guest_registration['phone_no']);
            //     } else if (
            //         !empty($guest_registration['email_address']) &&
            //         empty($guest_registration['phone_no'])
            //     ) {
            //         return $query
            //             ->where('email_address', $guest_registration['email_address']);
            //     } else {
            //         return $query
            //             ->where('phone_no', $guest_registration['phone_no']);
            //     }
            // })
            //     ->where('is_active', 1)
            //     ->where('hotel_id', '!=', $hotel_id)
            //     ->first();

            // if ($find_guest && $find_guest->is_active == 1) {
            //     return response()->json([
            //         'create'        => false,
            //         'guest_id'      => 0,
            //         'message'       => 'The information is already in the system',
            //         'description'   => [
            //             "email_address" => "The email_address is already registered in the system",
            //             "phone_no"      => "The phone_no is already registered in the system",
            //         ]
            //     ], 400);
            // }

            $find_guest = \App\Models\GuestRegistration::where(function ($query) use ($guest_registration) {
                if (
                    ! empty($guest_registration['email_address']) &&
                    ! empty($guest_registration['phone_no'])
                ) {
                    return $query
                        ->where('email_address', $guest_registration['email_address'])
                        ->orWhere('phone_no', $guest_registration['phone_no']);
                } elseif (
                    ! empty($guest_registration['email_address']) &&
                    empty($guest_registration['phone_no'])
                ) {
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

            if ($find_guest) {
                $guest_id = $find_guest->guest_id;
                $find_guest->fill($guest_registration);
                $find_guest->save();
            } else {
                $guest_id = GuestRegistration::create($guest_registration)->guest_id;
            }

            $validation = Validator::make($gChe, [
                'room_no' => 'required_without:room',
                'room' => 'required_without:room_no',
                'check_in' => 'required|date_format:"Y-m-d H:i:s"',
                'check_out' => 'required|date_format:"Y-m-d H:i:s"|after:'.$gChe['check_in'],
                'comment' => 'string',
            ]);

            if ($validation->fails()) {
                return response()->json([
                    'create' => false,
                    'guest_id' => 0,
                    'message' => 'guest_checkin_details object, failed validation',
                    'description' => $validation->errors(),
                ], 400);
            }

            $room = $this->findRoomId($hotel_id, $staff_id, array_key_exists('room_no', $gChe) ? $gChe['room_no'] : $gChe['room']);
            $room_id = $room['room_id'];
            $location = $room['room'];

            $guest_checkin_details = [
                'guest_id' => $guest_id,
                'hotel_id' => $hotel_id,
                'room_no' => $room_id,
                'comment' => array_key_exists('comment', $gChe) ? $gChe['comment'] : '',
                'check_in' => $gChe['check_in'],
                'check_out' => $gChe['check_out'],
            ];

            $now = date('Y-m-d H:i:s');

            $guest_checkin_details = GuestCheckinDetails::create($guest_checkin_details);

            $this->saveLogTracker([
                'module_id' => 8,
                'action' => 'add',
                'prim_id' => $guest_id,
                'staff_id' => $staff_id,
                'date_time' => $now,
                'comments' => '',
                'hotel_id' => $hotel_id,
                'type' => 'API',
            ]);

            if (! empty($guest_registration['email_address'])) {
                $rs = $this->sendAngelInvitation($guest_registration['email_address'], $hotel_id, $guest_registration['phone_no']);
            }
            \Log::info(json_encode(DB::getQueryLog()));
            DB::commit();

            return response()->json([
                'create' => true,
                'guest_id' => $guest_id,
                'message' => '',
                'description' => [],
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'create' => false,
                'guest_id' => 0,
                'message' => 'Bad request',
                'description' => ["$e"],
            ], 400);
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
        DB::enableQueryLog();

        DB::beginTransaction();
        try {
            $guest_registration = \App\Models\GuestRegistration::find($id);
            if ($guest_registration) {
                $guest_checkin_details = \App\Models\GuestCheckinDetails::where('guest_id', $guest_registration->guest_id)
                    ->orderBy('sno', 'DESC')
                    ->first();

                /* Validate send object */
                if (! isset($request->guest_registration)) {
                    DB::rollback();

                    return response()->json([
                        'update' => false,
                        'message' => 'guest_registration object, data not provided',
                        'description' => [],
                    ], 400);
                }

                $hotel_id = $guest_registration->hotel_id;
                $staff_id = $request->user()->staff_id;
                /* configure timezone  by hotel */
                $this->configTimeZone($hotel_id);

                /* validate if current user belongs the hotel */
                if (! $this->validateHotelId($guest_registration->hotel_id, $staff_id)) {
                    DB::rollback();

                    return response()->json([
                        'create' => false,
                        'guest_id' => 0,
                        'message' => 'the hotel_id does not belong to the current user',
                        'description' => [],
                    ], 400);
                }

                /* Store in variables */
                $guest_registration_old = $request->guest_registration;
                $guest_checkin_details_old = isset($request->guest_checkin_details) ? $request->guest_checkin_details : null;

                /* Validate guest registration object */
                $validation = Validator::make($guest_registration_old, [
                    'hotel_id' => 'required|numeric|exists:hotels',
                    'firstname' => 'string',
                    'lastname' => 'string',
                    'email_address' => 'string',
                    'phone_no' => 'string',
                ]);

                if (! isset($guest_registration['email_address']) && ! isset($guest_registration['phone_no'])) {
                    $message = 'The phone_no field is required';
                    if (! isset($guest_registration['email_address'])) {
                        $message = 'The email_address field is required';
                    }

                    return response()->json([
                        'update' => false,
                        'guest_id' => 0,
                        'message' => $message,
                        'description' => [],
                    ], 400);
                }

                /* if error are found */
                if ($validation->fails()) {
                    DB::rollback();

                    return response()->json([
                        'update' => false,
                        'guest_id' => 0,
                        'message' => 'guest_registration object, failed validation',
                        'description' => $validation->errors(),
                    ], 400);
                }

                if (isset($guest_registration_old['email_address'])) {
                    $exist_emial = \App\Models\GuestRegistration::where('guest_id', '!=', $guest_registration->guest_id)
                        ->where('hotel_id', $guest_registration->hotel_id)
                        ->where('email_address', $guest_registration_old['email_address'])
                        ->get();
                    if (count($exist_emial) > 0) {
                        DB::rollback();

                        return response()->json([
                            'update' => false,
                            'message' => 'The email you are trying to update is already registered in the system',
                            'description' => [],
                        ], 400);
                    }
                    $guest_registration->email_address = $guest_registration_old['email_address'];
                }

                if (isset($guest_registration_old['phone_no'])) {
                    $exist_phone = \App\Models\GuestRegistration::where('guest_id', '!=', $guest_registration->guest_id)
                        ->where('hotel_id', $guest_registration->hotel_id)
                        ->where('phone_no', $guest_registration_old['phone_no'])
                        ->get();
                    if (count($exist_phone) > 0) {
                        DB::rollback();

                        return response()->json([
                            'update' => false,
                            'message' => 'The phone number you are trying to update is already registered in the system',
                            'description' => [],
                        ], 400);
                    }
                    $guest_registration->phone_no = $guest_registration_old['phone_no'];
                }

                $guest_registration->firstname = isset($guest_registration_old['firstname']) ? $guest_registration_old['firstname'] : $guest_registration->firstname;
                $guest_registration->lastname = isset($guest_registration_old['lastname']) ? $guest_registration_old['lastname'] : $guest_registration->lastname;

                if (isset($guest_registration_old['firstname']) || $guest_registration_old['lastname']) {
                    $exist_names = \App\Models\GuestRegistration::where('guest_id', '!=', $guest_registration->guest_id)
                        ->where('firstname', $guest_registration->firstname)
                        ->where('lastname', $guest_registration->lastname)
                        ->get();

                    if (count($exist_names) > 0) {
                        DB::rollback();

                        return response()->json([
                            'update' => false,
                            'message' => 'The firstname and lastname you are trying to update is already registered in the system',
                            'description' => [],
                        ], 400);
                    }
                }

                $guest_registration->save();

                if ($guest_checkin_details_old !== null) {
                    if (isset($guest_checkin_details_old['room_no'])) {
                        $validate_room = DB::select(
                            'SELECT
                                gr.guest_id, 
                                gd.room_no 
                            FROM guest_registration gr 
                            INNER JOIN guest_checkin_details gd ON gr.guest_id = gd.guest_id 
                            INNER JOIN hotels h ON gr.hotel_id=h.hotel_id 
                            WHERE 
                                gr.hotel_id = ? AND 
                                gd.check_out >= ? AND 
                                gd.room_no = ? and 
                                DATEDIFF(gr.guest_id,gd.guest_id)',
                            [
                                $guest_registration->hotel_id,
                                date('Y-m-d H:i:s'),
                                $guest_checkin_details_old['room_no'],
                            ]
                        );

                        if (count($validate_room) > 0) {
                            DB::rollback();

                            return response()->json([
                                'update' => false,
                                'message' => 'The room you are trying to save is not available',
                                'description' => ['room' => 'The room is not available'],
                            ], 400);
                        }
                    } elseif (isset($guest_checkin_details_old['room'])) {
                        $location = $guest_checkin_details_old['room'];
                        $str_room = 'Room';
                        $room = \App\Models\HotelRoom::where('hotel_id', $guest_registration->hotel_id)->get();
                        $room_id = 0;
                        foreach ($room as $r) {
                            if (is_numeric($r->location)) {
                                if ($str_room.' '.$r->location === $location) {
                                    $room_id = $r->room_id;
                                }
                            } else {
                                if ($r->location === $location) {
                                    $room_id = $r->room_id;
                                }
                            }
                        }

                        if ($room_id == 0) {
                            $room_id = \App\Models\HotelRoom::create(
                                [
                                    'hotel_id' => $guest_registration->hotel_id,
                                    'location' => $location,
                                    'created_by' => $request->user()->staff_id,
                                    'updated_by' => null,
                                    'created_on' => date('Y-m-d H:i:s'),
                                    'updated_on' => null,
                                    'active' => 1,
                                    'angel_view' => 1,
                                    'device_token' => '',
                                ]
                            )->room_id;
                            $this->saveLogTracker([
                                'module_id' => 17,
                                'action' => 'update',
                                'prim_id' => $room_id,
                                'staff_id' => $request->user()->staff_id,
                                'date_time' => date('Y-m-d H:i:s'),
                                'comments' => '',
                                'hotel_id' => $guest_registration->hotel_id,
                                'type' => 'API',
                            ]);
                        }

                        $guest_checkin_details_old['room_no'] = $room_id;
                        $validate_room = DB::select(
                            'SELECT gr.guest_id, gd.room_no FROM guest_registration gr
                            INNER JOIN guest_checkin_details gd ON gr.guest_id = gd.guest_id
                            INNER JOIN hotels h ON gr.hotel_id=h.hotel_id
                            WHERE gr.hotel_id = ? AND  gd.check_out >= ? AND gd.room_no = ?',
                            [
                                $guest_registration->hotel_id,
                                date('Y-m-d H:i:s'),
                                $guest_checkin_details_old['room_no'],
                            ]
                        );
                        if (count($validate_room) > 0) {
                            DB::rollback();

                            return response()->json([
                                'update' => false,
                                'message' => 'The room you are trying to save is not available',
                                'description' => ['room' => 'The room is not available'],
                            ], 400);
                        }
                    } else {
                        DB::rollback();

                        return response()->json([
                            'update' => false,
                            'message' => 'Room not supplied',
                            'description' => [],
                        ], 400);
                    }
                    $guest_checkin_details_last = \App\Models\GuestCheckinDetails::where('guest_id', $guest_registration->guest_id)->where('sno', '!=', $guest_checkin_details->sno)->orderBy('sno', 'DESC')->first();
                    if (isset($guest_checkin_details_last)) {
                        $new_check_in = isset($guest_checkin_details_old['check_in']) ? $guest_checkin_details_old['check_in'] : $guest_checkin_details->check_in;
                        $last_check_out = $guest_checkin_details_last['check_out'];
                        if ($new_check_in > $last_check_out) {
                            $guest_checkin_details->check_in = isset($guest_checkin_details_old['check_in']) ? $guest_checkin_details_old['check_in'] : $guest_checkin_details->check_in;
                            $guest_checkin_details->check_out = isset($guest_checkin_details_old['check_out']) ? $guest_checkin_details_old['check_out'] : $guest_checkin_details->check_out;
                            $guest_checkin_details->save();
                        } else {
                            DB::rollback();

                            return response()->json([
                                'update' => false,
                                'message' => 'Missed date range',
                                'description' => [],
                            ], 400);
                        }
                    } else {
                        $guest_checkin_details->check_in = isset($guest_checkin_details_old['check_in']) ? $guest_checkin_details_old['check_in'] : $guest_checkin_details->check_in;
                        $guest_checkin_details->check_out = isset($guest_checkin_details_old['check_out']) ? $guest_checkin_details_old['check_out'] : $guest_checkin_details->check_out;
                        $guest_checkin_details->save();
                    }
                }

                $this->saveLogTracker([
                    'module_id' => 8,
                    'action' => 'update',
                    'prim_id' => $guest_registration->guest_id,
                    'staff_id' => $request->user()->staff_id,
                    'date_time' => date('Y-m-d H:i:s'),
                    'comments' => '',
                    'hotel_id' => $guest_registration->hotel_id,
                    'type' => 'API',
                ]);

                DB::commit();
                $success = true;
            } else {
                DB::rollback();

                return response()->json([
                    'update' => false,
                    'message' => 'Record not found',
                    'description' => [],
                ], 400);
            }
        } catch (\Exception $e) {
            $error = $e;
            $success = false;
            DB::rollback();
        }
        \Log::info(json_encode(DB::getQueryLog()));
        if ($success) {
            return response()->json([
                'update' => true,
                'message' => '',
                'description' => [],
            ], 200);
        } else {
            DB::rollback();

            return response()->json([
                'update' => false,
                'message' => 'Bad request',
                'description' => ["$error"],
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
        DB::enableQueryLog();

        $guest_registration = \App\Models\GuestRegistration::find($id);
        if ($guest_registration) {
            $guest_registration->is_active = 0;
            $guest_registration->angel_status = 0;
            $guest_registration->save();

            $guest_checkin_details = \App\Models\GuestCheckinDetails::where('guest_id', $id)->get();

            foreach ($guest_checkin_details as $gcd) {
                $gcd->status = 2;
                $gcd->save();
            }

            $this->configTimeZone($guest_registration->hotel_id);

            $this->saveLogTracker([
                'module_id' => 8,
                'action' => 'delete',
                'prim_id' => $id,
                'staff_id' => $request->user()->staff_id,
                'date_time' => date('Y-m-d H:i:s'),
                'comments' => '',
                'hotel_id' => $guest_registration->hotel_id,
                'type' => 'API',
            ]);
            \Log::info(json_encode(DB::getQueryLog()));

            return response()->json([
                'delete' => true,
                'message' => '',
                'description' => [],
            ], 400);
        } else {
            return response()->json([
                'delete' => false,
                'message' => 'Record not found',
                'description' => [],
            ], 400);
        }
    }

    public function closeGuestCheckinDetails(Request $request)
    {
        DB::enableQueryLog();

        DB::beginTransaction();
        try {
            $this->configTimeZone($request->hotel_id);
            $now = date('Y-m-d H:i:s');
            $guest_id = $request->guest_id;
            $guest_checkin_details = \App\Models\GuestCheckinDetails::where('guest_id', $guest_id)
                ->where('check_in', '>=', $now)
                ->where('check_out', '<=', $now)
                ->first();
            if ($guest_checkin_details) {
                $guest_checkin_details->status = 0;
                $guest_checkin_details->save();
                $success = true;
                DB::commit();
            } else {
                $success = false;
                DB::rollback();

                return response()->json([
                    'close' => false,
                    'message' => 'Record not found',
                    'description' => [],
                ], 400);
            }
        } catch (\Exception $e) {
            $error = $e;
            $success = false;
            DB::rollback();
        }
        \Log::info(json_encode(DB::getQueryLog()));
        if ($success) {
            return response()->json([
                'close' => true,
                'message' => '',
                'description' => [],
            ], 200);
        } else {
            DB::rollback();

            return response()->json([
                'close' => false,
                'message' => 'Bad request',
                'description' => $error,
            ], 400);
        }
    }

    public function validateEmail(Request $request, $hotel_id, $email)
    {
        if (! $this->validateHotelId($hotel_id, $request->user()->staff_id)) {
            return response()->json([
                'exists' => null,
                'message' => 'the hotel_id does not belong to the current user',
            ], 400);
        }
        $guest_registration = \App\Models\GuestRegistration::where('email_address', $email)
            ->where('hotel_id', $hotel_id)
            ->first();

        if (! isset($guest_registration)) {
            return response()->json([
                'exists' => false,
                'message' => '',
            ], 200);
        } else {
            return response()->json([
                'exists' => true,
                'message' => '',
            ], 200);
        }
    }

    public function validatePhoneNumber(Request $request, $hotel_id, $phone_numer)
    {
        if (! $this->validateHotelId($hotel_id, $request->user()->staff_id)) {
            return response()->json([
                'exists' => null,
                'message' => 'the hotel_id does not belong to the current user',
            ], 400);
        }
        $guest_registration = \App\Models\GuestRegistration::where('phone_no', $phone_numer)
            ->where('hotel_id', $hotel_id)
            ->first();

        if (! isset($guest_registration)) {
            return response()->json([
                'exists' => false,
                'message' => '',
            ], 200);
        } else {
            return response()->json([
                'exists' => true,
                'message' => '',
            ], 200);
        }
    }

    public function findRoomId($hotel_id, $staff_id, $location)
    {
        $room = HotelRoom::where('hotel_id', $hotel_id)

            ->where(function ($query) use ($location) {
                return $query
                    ->where('location', $location)
                    ->orWhere('room_id', $location);
            })->first();

        if ($room) {
            return [
                'room_id' => $room->room_id,
                'room' => $room->location,
            ];
        } else {
            $room = HotelRoom::create([
                'hotel_id' => $hotel_id,
                'location' => $location,
                'created_by' => $staff_id,
                'created_on' => date('Y-m-d H:i:s'),
                'updated_by' => null,
                'updated_on' => null,
                'active' => 1,
                'angel_view' => 1,
                'device_token' => '',
            ]);

            $this->saveLogTracker([
                'hotel_id' => $hotel_id,
                'staff_id' => $staff_id,
                'prim_id' => $room->room_id,
                'module_id' => 17,
                'action' => 'add',
                'date_time' => date('Y-m-d H:i:s'),
                'comments' => '',
                'type' => 'API',
            ]);

            return [
                'room_id' => $room->room_id,
                'room' => $room->location,
            ];
        }
    }
}
