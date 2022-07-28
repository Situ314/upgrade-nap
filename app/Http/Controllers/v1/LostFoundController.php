<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LostFoundController extends Controller
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
            $data = \App\Models\LostFound::where('hotel_id', $hotel_id)->paginate($paginate);

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
            $lst_fnd_no = 0;
            if (! isset($request->lost_found)) {
                return response()->json([
                    'create' => false,
                    'lst_fnd_no' => 0,
                    'message' => 'lost_found object, data not provided',
                    'description' => [],
                ], 400);
            }
            $lost_found = $request->lost_found;
            if (! $this->validateHotelId($lost_found['hotel_id'], $request->user()->staff_id)) {
                return response()->json([
                    'create' => false,
                    'pkg_no' => 0,
                    'message' => 'The user does not correspond with the hotel id',
                    'description' => [],
                ], 400);
            }

            $validation = Validator::make($lost_found, [
                'hotel_id' => 'required|numeric|exists:hotels',
                'guest_id' => 'numeric|exists:guest_registration',
                'item_name' => 'required|string',
                'comment' => 'string',
                'name' => 'string',
                'room_id' => 'numeric|exists:hotel_rooms',
                'phone_no' => 'string',
            ]);
            if ($validation->fails()) {
                return response()->json([
                    'create' => false,
                    'pkg_no' => 0,
                    'message' => 'lost_found object, failed validation',
                    'description' => $validation->errors(),
                ], 400);
            }
            if (isset($lost_found['guest_id'])) {
                $guest = \App\Models\GuestRegistration::find($lost_found['guest_id']);
                if ($guest) {
                    $guest_check = \App\Models\GuestCheckinDetails::where('guest_id', $guest->guest_id)->orderBy('sno', 'DESC')->first();
                    $lost_found['guest_id'] = $guest->guest_id;
                    $lost_found['phone_no'] = $guest->phone_no;
                    $lost_found['room_id'] = $guest_check->room_id;
                } else {
                    return response()->json([
                        'create' => false,
                        'pkg_no' => 0,
                        'message' => 'The guest is not valid',
                        'description' => $validation->errors(),
                    ], 400);
                }
            }
            $this->configTimeZone($lost_found['hotel_id']);
            if (isset($lost_found['name'])) {
                if (isset($lost_found['phone_no'])) {
                    $str = $lost_found['name'];
                    $_hotel_id = $lost_found['hotel_id'];
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
                            'hotel_id' => $lost_found['hotel_id'],
                            'firstname' => addslashes($_firstname),
                            'lastname' => addslashes($_lastname),
                            'phone_no' => isset($lost_found['phone_no']) ? $lost_found['phone_no'] : '',
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
                            'hotel_id' => $lost_found['hotel_id'],
                            'guest_id' => $guest_id,
                        ], [
                            'guest_id' => $guest_id,
                            'hotel_id' => $lost_found['hotel_id'],
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
                        'hotel_id' => $lost_found['hotel_id'],
                        'type' => 'API',
                    ]);
                    $lost_found['guest_id'] = $guest_id;
                } else {
                    return response()->json([
                        'create' => false,
                        'pkg_no' => 0,
                        'message' => 'The phone number is required to create the guest',
                        'description' => $validation->errors(),
                    ], 400);
                }
            }

            $lost_found['created_by'] = $request->user()->staff_id;
            $lost_found['created_on'] = date('Y-m-d H:i:s');
            $lost_found['guest_id'] = isset($lost_found['guest_id']) ? $lost_found['guest_id'] : 0;
            $lost_found['phone_no'] = isset($lost_found['phone_no']) ? $lost_found['phone_no'] : '';
            $lost_found['room_id'] = isset($lost_found['room_id']) ? $lost_found['room_id'] : 0;
            $lost_found['comment'] = isset($lost_found['comment']) ? addslashes($lost_found['comment']) : '';
            $lost_found['status'] = '1';
            $lost_found['confirmed_by'] = null;
            $lost_found['signature_type'] = null;
            $lost_found['delivered_by'] = null;
            $lost_found['updated_on'] = null;
            $lost_found['updated_by'] = null;
            $lost_found['confirmed_person'] = null;
            $lost_found['reference_number'] = null;
            $lost_found['active'] = 1;
            $last_lost_found = \App\Models\LostFound::where('hotel_id', $lost_found['hotel_id'])->orderBy('lst_fnd_no', 'DESC')->first();
            $last_consecutive = $last_package->consecute;
            $lost_found['consecutive'] = ($last_consecutive + 1);
            $lost_found['delivered_name'] = null;

            $lst_fnd_no = \App\Models\LostFound::create($lost_found)->lst_fnd_no;
            $this->saveLogTracker([
                'module_id' => 3,
                'action' => 'add',
                'prim_id' => $lst_fnd_no,
                'staff_id' => $request->user()->staff_id,
                'date_time' => date('Y-m-d H:i:s'),
                'comments' => '',
                'hotel_id' => $lost_found['hotel_id'],
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
                'lst_fnd_no' => $lst_fnd_no,
                'message' => '',
                'description' => [],
            ], 201);
        } else {
            return response()->json([
                'create' => false,
                'lst_fnd_no' => 0,
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
            $lost_found = \App\Models\LostFound::find($id);
            if ($lost_found) {
                /* Validate send object */
                if (! isset($request->lost_found)) {
                    return response()->json([
                        'update' => false,
                        'message' => 'guest_registration object, data not provided',
                        'description' => [],
                    ], 400);
                }
                /* configure timezone  by hotel */
                $this->configTimeZone($lost_found->hotel_id);

                $lost_found_old = $request->lost_found;
                $lost_found->created_by = $request->user()->staff_id;
                $lost_found->created_on = date('Y-m-d H:i:s');
                $lost_found->item_name = isset($lost_found_old['item_name']) ? $lost_found_old['item_name'] : $lost_found->item_name;
                $lost_found->status = isset($lost_found_old['status']) ? $lost_found_old['status'] : $lost_found->status;

                if (isset($lost_found_old['guest_id'])) {
                    $guest = \App\Models\GuestRegistration::find($lost_found_old['guest_id']);
                    if ($guest) {
                        $guest_check = \App\Models\GuestCheckinDetails::where('guest_id', $guest->guest_id)->orderBy('sno', 'DESC')->first();
                        $lost_found_old['guest_id'] = $guest->guest_id;
                        $lost_found_old['phone_no'] = $guest->phone_no;
                        $lost_found_old['room_id'] = $guest_check->room_id;

                        $lost_found->guest_id = isset($lost_found_old['guest_id']) ? $lost_found_old['guest_id'] : $lost_found->guest_id;
                        $lost_found->phone_no = isset($lost_found_old['phone_no']) ? $lost_found_old['phone_no'] : $lost_found->phone_no;
                        $lost_found->room_id = isset($lost_found_old['room_id']) ? $lost_found_old['room_id'] : $lost_found->room_id;
                    } else {
                        return response()->json([
                            'update' => false,
                            'message' => 'The guest is not valid',
                            'description' => $validation->errors(),
                        ], 400);
                    }
                }
                $lost_found->save();
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
        $plost_found = \App\Models\LostFound::find($id);
        if ($plost_found) {
            $plost_found->active = 0;
            $plost_found->save();

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
