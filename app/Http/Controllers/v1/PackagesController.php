<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use DB;
use Illuminate\Http\Request;
use Validator;

class PackagesController extends Controller
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

        if ($this->validateHotelId($hotel_id, $staff_id)) {
            $data = \App\Models\Package::where('hotel_id', $hotel_id)->where('active', 1)->paginate($paginate);

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
        DB::beginTransaction();
        try {
            if (! isset($request->package)) {
                return response()->json([
                    'create' => false,
                    'pkg_no' => 0,
                    'message' => 'package object, data not provided',
                    'description' => [],
                ], 400);
            }
            $package = $request->package;

            if (! $this->validateHotelId($package['hotel_id'], $request->user()->staff_id)) {
                return response()->json([
                    'create' => false,
                    'pkg_no' => 0,
                    'message' => 'The user does not correspond with the hotel id',
                    'description' => [],
                ], 400);
            }

            $validation = Validator::make($package, [
                'hotel_id' => 'required|numeric|exists:hotels',
                'item_name' => 'required|string',
                'guest_id' => 'numeric|exists:guest_registration',
                'comment' => 'string',
                'name' => 'string',
                'room_id' => 'numeric|exists:hotel_rooms',
                'phone_no' => 'string',
            ]);
            if ($validation->fails()) {
                return response()->json([
                    'create' => false,
                    'pkg_no' => 0,
                    'message' => 'package object, failed validation',
                    'description' => $validation->errors(),
                ], 400);
            }

            if (isset($package['guest_id'])) {
                $guest = \App\Models\GuestRegistration::find($package['guest_id']);
                if ($guest) {
                    $guest_check = \App\Models\GuestCheckinDetails::where('guest_id', $guest->guest_id)->orderBy('sno', 'DESC')->first();
                    $package['guest_id'] = $guest->guest_id;
                    $package['phone_no'] = $guest->phone_no;
                    $package['room_id'] = $guest_check->room_no;
                } else {
                    return response()->json([
                        'create' => false,
                        'pkg_no' => 0,
                        'message' => 'The guest is not valid',
                        'description' => $validation->errors(),
                    ], 400);
                }
            }

            $this->configTimeZone($package['hotel_id']);

            if (isset($package['name'])) {
                if (isset($package['phone_no'])) {
                    $str = $package['name'];
                    $_hotel_id = $package['hotel_id'];
                    $_firstname = explode(' ', $str, 2)[0];
                    if (count(explode(' ', $str, 2)) > 1) {
                        $_lastname = explode(' ', $str, 2)[1];
                    } else {
                        $_lastname = '';
                    }
                    $guest = \App\Models\GuestRegistration::where(function ($q) use ($_firstname, $_lastname, $_hotel_id) {
                        $q->whereRaw("LOWER(`firstname`) = '".strtoupper($_firstname)."'");
                        $q->WhereRaw("LOWER(`lastname`) = '".strtoupper($_lastname)."'");
                        $q->WhereRaw('hotel_id = '.$_hotel_id);
                    })->first();
                    $guest_id = 0;
                    if ($guest) {
                        $guest_id = $guest->guest_id;
                    } else {
                        $guest_id = \App\Models\GuestRegistration::create([
                            'hotel_id' => $package['hotel_id'],
                            'firstname' => addslashes($_firstname),
                            'lastname' => addslashes($_lastname),
                            'phone_no' => isset($package['phone_no']) ? $package['phone_no'] : '',
                            'email_address' => '',
                            'address' => '',
                            'zipcode' => '',
                            'dob' => '1900-01-01 00:00:00',
                            'language' => '',
                            'comment' => '',
                            'is_active' => 0,
                            'angel_status' => 0,
                            'created_on' => date('Y-m-d H:i:s'),
                            'created_by' => $request->user()->staff_id,
                            'updated_on' => null,
                            'updated_by' => null,
                            'city' => '',
                            'id_device' => null,
                            'state' => '0',
                        ])->guest_id;

                        \App\Models\GuestCheckinDetails::firstOrCreate([
                            'hotel_id' => $package['hotel_id'],
                            'guest_id' => $guest_id,
                        ], [
                            'guest_id' => $guest_id,
                            'hotel_id' => $package['hotel_id'],
                            'room_no' => 0,
                            'check_in' => '1900-01-01 00:00:00',
                            'check_out' => '1900-01-01 00:00:00',
                            'comment' => '',
                            'status' => 0,
                        ]);
                    }
                    $this->saveLogTracker([
                        'module_id' => 8,
                        'action' => 'add',
                        'prim_id' => $guest_id,
                        'staff_id' => $request->user()->staff_id,
                        'date_time' => date('Y-m-d H:i:s'),
                        'comments' => '',
                        'hotel_id' => $package['hotel_id'],
                        'type' => 'API',
                    ]);
                    $package['guest_id'] = $guest_id;
                } else {
                    return response()->json([
                        'create' => false,
                        'pkg_no' => 0,
                        'message' => 'The phone number is required to create the guest',
                        'description' => $validation->errors(),
                    ], 400);
                }
            }
            $package['created_by'] = $request->user()->staff_id;
            $package['created_on'] = date('Y-m-d H:i:s');
            $package['guest_id'] = isset($package['guest_id']) ? $package['guest_id'] : 0;
            $package['phone_no'] = isset($package['phone_no']) ? $package['phone_no'] : '';
            $package['room_id'] = isset($package['room_id']) ? $package['room_id'] : 0;
            $package['comment'] = isset($package['comment']) ? addslashes($package['comment']) : '';
            $package['status'] = '1';
            $package['confirmed_by'] = null;
            $package['signature_type'] = null;
            $package['delivered_by'] = null;
            $package['updated_on'] = null;
            $package['updated_by'] = null;
            $package['confirmed_person'] = null;
            $package['reference_number'] = null;
            $package['active'] = 1;
            $last_package = \App\Models\Package::where('hotel_id', $package['hotel_id'])->orderBy('pkg_no', 'DESC')->first();
            $last_consecutive = $last_package->consecute;
            $package['consecutive'] = ($last_consecutive + 1);
            $package['delivered_name'] = null;

            $package['courier'] = isset($package['courier']) ? addslashes($package['courier']) : '';

            $pkg_no = \App\Models\Package::create($package)->pkg_no;
            $this->saveLogTracker([
                'module_id' => 2,
                'action' => 'add',
                'prim_id' => $pkg_no,
                'staff_id' => $request->user()->staff_id,
                'date_time' => date('Y-m-d H:i:s'),
                'comments' => '',
                'hotel_id' => $package['hotel_id'],
                'type' => 'API',
            ]);
            DB::commit();
            $success = true;
        } catch (\Exception $e) {
            echo $e;
            $error = $e;
            $success = false;
            DB::rollback();
        }
        if ($success) {
            return response()->json([
                'create' => true,
                'pkg_no' => $pkg_no,
                'message' => '',
                'description' => [],
            ], 201);
        } else {
            return response()->json([
                'create' => false,
                'pkg_no' => 0,
                'message' => 'Bad request',
                'description' => $error,
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
