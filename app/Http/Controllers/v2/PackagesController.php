<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\GuestCheckinDetails;
use App\Models\GuestRegistration;
use App\Models\Package;
use DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Validator;

class PackagesController extends Controller
{
    public function index(Request $request)
    {
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
        // Validar acceso al hotel x usuario
        if (! $this->validateHotelId($hotel_id, $staff_id)) {
            return response()->json(['error' => 'User does not have access to the hotel'], 400);
        }
        // Validar que el usuario tenga permisos para realizar esta operacion
        $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 2, $action = 'view');
        if (! $permission) {
            return response()->json(['error' => 'User does not have permission to perform this action'], 400);
        }
        // Configurar timezone y capturar fecha
        $this->configTimeZone($hotel_id);
        //$now = date('Y-m-d H:i:s');
        $data = Package::where('hotel_id', $hotel_id)
            ->where('active', 1)
            ->paginate($paginate);

        return response()->json($data, 200);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // Validar hotel
            if (! $request->exists('hotel_id')) {
                return response()->json(['error' => 'Hotel id not provided'], 400);
            }
            $hotel_id = $request->hotel_id;
            $staff_id = $request->user()->staff_id;
            // Validate if the object was sent
            if (! $request->exists('package')) {
                return response()->json(['error' => 'Package object, data not provided'], 400);
            }
            $package = $request->package;
            if (! $this->validateHotelId($hotel_id, $staff_id)) {
                return response()->json([
                    'create' => false,
                    'pkg_no' => 0,
                    'message' => 'The user does not correspond with the Hotel id',
                    'description' => null,
                ], 400);
            }
            $this->configTimeZone($hotel_id);
            $now = date('Y-m-d H:i:s');
            $validation = Validator::make($package, [
                'item_name' => 'required|string',
                'comment' => 'string',
                'guest_id' => [
                    'numeric',
                    Rule::exists('guest_registration')->where(function ($q) use ($hotel_id) {
                        $q->where('hotel_id', $hotel_id);
                    }),
                ],
                'room_id' => [
                    'numeric',
                    'required_without:location',
                    Rule::exists('hotel_rooms')->where(function ($q) use ($hotel_id) {
                        $q->where('hotel_id', $hotel_id);
                    }),
                ],
                'location' => [
                    'string',
                    'required_without:room_id',
                    Rule::exists('hotel_rooms')->where(function ($q) use ($hotel_id) {
                        $q->where('hotel_id', $hotel_id);
                    }),
                ],
                'phone_no' => [
                    'string',
                    'regex:/\+[0-9]{1,4}[0-9]{6,10}/',
                ],
            ]);
            if ($validation->fails()) {
                return response()->json([
                    'create' => false,
                    'pkg_no' => 0,
                    'message' => 'package object, failed validation',
                    'description' => $validation->errors(),
                ], 400);
            }

            if (isset($package['guest_id']) && ! empty($package['guest_id'])) {
                $guest_id = $package['guest_id'];
                $GuestRegistration = GuestRegistration::find($guest_id);
                if ($GuestRegistration) {
                    $GuestCheckinDetails = GuestCheckinDetails::where('hotel_id', $hotel_id)
                        ->where('guest_id', $guest_id)
                        ->where('status', 1)
                        ->orderBy('sno', 'DESC')
                        ->first();

                    $package['phone_no'] = $GuestRegistration->phone_no;
                    $package['room_id'] = $GuestCheckinDetails->room_no;
                } else {
                    DB::rollback();

                    return response()->json([
                        'create' => false,
                        'pkg_no' => 0,
                        'message' => 'The guest is not valid',
                        'description' => $validation->errors(),
                    ], 400);
                }
            } elseif (isset($package['phone_no']) && ! empty($package['phone_no'])) {
                $GuestRegistration = GuestRegistration::where('hotel_id', $hotel_id)
                    ->where('phone_no', $package['phone_no'])
                    ->first();

                if ($GuestRegistration) {
                    $GuestCheckinDetails = GuestCheckinDetails::where('hotel_id', $hotel_id)
                        ->where('guest_id', $GuestRegistration->guest_id)
                        ->where('status', 1)
                        ->orderBy('sno', 'DESC')
                        ->first();

                    $package['guest_id'] = $GuestRegistration->guest_id;
                    $package['room_id'] = $GuestCheckinDetails->room_no;
                } else {
                    if (isset($package['name']) && ! empty($package['name'])) {
                        $str = $package['name'];
                        $_firstname = explode(' ', $str, 2)[0];
                        $_lastname = '';
                        if (count(explode(' ', $str, 2)) > 1) {
                            $_lastname = explode(' ', $str, 2)[1];
                        }

                        $guest_id = GuestRegistration::create([
                            'hotel_id' => $hotel_id,
                            'firstname' => addslashes($_firstname),
                            'lastname' => addslashes($_lastname),
                            'phone_no' => $package['phone_no'],
                            'created_by' => $request->user()->staff_id,
                            'created_on' => $now,
                            'dob' => '1900-01-01 00:00:00',
                            'email_address' => '',
                            'address' => '',
                            'zipcode' => '',
                            'language' => '',
                            'comment' => '',
                            'city' => '',
                            'is_active' => 0,
                            'angel_status' => 0,
                            'state' => 0,
                            'updated_on' => null,
                            'updated_by' => null,
                            'id_device' => null,
                        ])->guest_id;

                        $sno = GuestCheckinDetails::create([
                            'guest_id' => $guest_id,
                            'hotel_id' => $hotel_id,
                            'room_no' => 0,
                            'check_in' => '1900-01-01 00:00:00',
                            'check_out' => '1900-01-01 00:00:00',
                            'comment' => '',
                            'status' => 0,
                        ])->sno;

                        $this->saveLogTracker([
                            'module_id' => 8,
                            'action' => 'add',
                            'prim_id' => $guest_id,
                            'staff_id' => $request->user()->staff_id,
                            'date_time' => date('Y-m-d H:i:s'),
                            'comments' => "Created by Package module sno: $sno",
                            'hotel_id' => $package['hotel_id'],
                            'type' => 'API',
                        ]);
                    } else {
                        DB::rollback();

                        return response()->json([
                            'create' => false,
                            'pkg_no' => 0,
                            'message' => 'The Guest name is required to create the guest',
                            'description' => null,
                        ], 400);
                    }
                }
            }

            $package['comment'] = isset($package['comment']) ? addslashes($package['comment']) : '';
            $package['created_by'] = $request->user()->staff_id;
            $package['created_on'] = $now;
            $package['status'] = 1;
            $package['active'] = 1;
            $package['confirmed_by'] = null;
            $package['signature_type'] = null;
            $package['delivered_by'] = null;
            $package['updated_on'] = null;
            $package['updated_by'] = null;
            $package['confirmed_person'] = null;
            $package['reference_number'] = null;
            $package['delivered_name'] = null;

            $last_package = Package::where('hotel_id', $hotel_id)->orderBy('pkg_no', 'DESC')->first();
            $last_consecutive = $last_package->consecute;
            $package['consecutive'] = ($last_consecutive + 1);
            $pkg_no = Package::create($package)->pkg_no;
            $this->saveLogTracker([
                'module_id' => 2,
                'action' => 'add',
                'prim_id' => $pkg_no,
                'staff_id' => $request->user()->staff_id,
                'date_time' => $now,
                'comments' => '',
                'hotel_id' => $hotel_id,
                'type' => 'API',
            ]);
            DB::commit();

            return response()->json([
                'create' => true,
                'pkg_no' => $pkg_no,
                'message' => '',
                'description' => null,
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'create' => false,
                'pkg_no' => 0,
                'message' => 'Bad request',
                'description' => $e,
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
        DB::beginTransaction();
        try {
            $package = \App\Models\Package::find($id);
            if ($package) {
                /* Validate send object */
                if (! isset($request->package)) {
                    return response()->json([
                        'update' => false,
                        'message' => 'guest_registration object, data not provided',
                        'description' => [],
                    ], 400);
                }
                /* configure timezone  by hotel */
                $this->configTimeZone($package->hotel_id);

                $package_old = $request->package;
                $package->created_by = $request->user()->staff_id;
                $package->created_on = date('Y-m-d H:i:s');
                $package->item_name = isset($package_old['item_name']) ? $package_old['item_name'] : $package->item_name;
                $package->status = isset($package_old['status']) ? $package_old['status'] : $package->status;

                if (isset($package_old['guest_id'])) {
                    $guest = \App\Models\GuestRegistration::find($package_old['guest_id']);
                    if ($guest) {
                        $guest_check = \App\Models\GuestCheckinDetails::where('guest_id', $guest->guest_id)->orderBy('sno', 'DESC')->first();
                        $package_old['guest_id'] = $guest->guest_id;
                        $package_old['phone_no'] = $guest->phone_no;
                        $package_old['room_id'] = $guest_check->room_id;

                        $package->guest_id = isset($package_old['guest_id']) ? $package_old['guest_id'] : $package->guest_id;
                        $package->phone_no = isset($package_old['phone_no']) ? $package_old['phone_no'] : $package->phone_no;
                        $package->room_id = isset($package_old['room_id']) ? $package_old['room_id'] : $package->room_id;
                    } else {
                        return response()->json([
                            'update' => false,
                            'message' => 'The guest is not valid',
                            'description' => $validation->errors(),
                        ], 400);
                    }
                }
                $package->save();
                DB::commit();
                $success = true;
            } else {
                DB::rollback();

                return response()->json([
                    'update' => false,
                    'message' => 'Record not foun',
                    'description' => $error,
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
                'description' => [],
            ], 200);
        } else {
            DB::rollback();

            return response()->json([
                'update' => false,
                'message' => 'Bad request',
                'description' => $error,
            ], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $package = \App\Models\Package::find($id);
        if ($package) {
            $package->active = 0;
            $package->save();

            return response()->json([
                'delete' => true,
                'message' => '',
            ], 200);
        } else {
            return response()->json([
                'delete' => false,
                'message' => 'Record not found',
            ], 400);
        }
    }
}
