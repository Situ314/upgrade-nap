<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\GuestRegistration;
use App\Models\HousekeepingCleanings;
use App\Models\RoomMove;
use App\Moodels\GuestCheckinDetails;
use DB;
use Illuminate\Http\Request;

class ComtrolController extends Controller
{
    private $file_log;

    private $path;

    //main guest
    public function checkInRoom(Request $request)
    {
        return $this->checkIn($request, 1);
    }

    //Add guest to the room
    public function checkInGuest(Request $request)
    {
        return $this->checkIn($request, 0);
    }

    private function checkIn(Request $request, $main_guest)
    {
        $hotel_id = $request->hotel_id;
        $staff_id = $request->user()->staff_id;
        $data = $request->data;

        $lan = 'en';
        if (array_key_exists('language', $data)) {
            if ($data['language'] == '1') {
                $lan = 'es';
            }
        }

        $this->configTimeZone($hotel_id);

        $guest_registration = [
            'hotel_id' => $hotel_id,
            'firstname' => array_key_exists('first_name', $data) ? $data['first_name'] : '',
            'lastname' => array_key_exists('last_name', $data) ? $data['last_name'] : '',
            'email_address' => array_key_exists('email_address', $data) ? $data['email_address'] : '',
            'phone_no' => array_key_exists('phone_number', $data) ? $data['phone_number'] : '',
            'address' => '',
            'zipcode' => array_key_exists('zip_code', $data) ? $data['zip_code'] : '',
            'dod' => null,
            'language' => $lan,
            'angel_status' => 1,
            'city' => '',
            'active_staffcol' => '',
            'created_on' => date('Y-m-d H:i:s'),
            'created_by' => $staff_id,
        ];

        $validation = Validator::make($guest_registration, [
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
                'regex:/(\+[0-9]{1,4}[0-9]{6,10}|)/',
                'nullable',
                Rule::unique('guest_registration')->where(function ($query) use ($hotel_id) {
                    return $query->where('is_active', 1)->where('hotel_id', '!=', $hotel_id);
                }),
            ],
            'angel_status' => 'numeric|required|in:0,1',
            'language' => 'string|in:en,es',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'create' => false,
                'error' => $validation->errors(),
            ], 400);
        }

        $find_guest = GuestRegistration::where(
            function ($query) use ($guest_registration) {
                if (
                    ! empty($guest_registration['email_address']) &&
                    ! empty($guest_registration['phone_no'])
                ) {
                    return $query
                        ->where('email_address', $guest_registration['email_address'])
                        ->orWhere('phone_no', $guest_registration['phone_no']);
                } elseif (! empty($guest_registration['email_address']) && empty($guest_registration['phone_no'])) {
                    return $query
                        ->where('email_address', $guest_registration['email_address']);
                } else {
                    return $query
                        ->where('phone_no', $guest_registration['phone_no']);
                }
            }
        )->where('is_active', 1)
        ->where('hotel_id', '!=', $hotel_id)
        ->first();

        if ($find_guest && $find_guest->is_active == 1) {
            return response()->json([
                'create' => false,
                'error' => [
                    'email_address' => ['The email_address is already registered in the system'],
                    'phone_no' => ['The phone_no is already registered in the system'],
                ],
            ], 400);
        }

        $find_guest = GuestRegistration::where(
            function ($query) use ($guest_registration) {
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
            }
        )
        ->where('is_active', 1)
        ->where('hotel_id', '=', $hotel_id)
        ->first();

        DB::beginTransaction();

        if ($find_guest) {
            $guest_id = $find_guest->guest_id;

            $guest_registration['created_on'] = $find_guest->created_on;
            $guest_registration['created_by'] = $find_guest->created_by;
            $guest_registration['updated_by'] = $staff_id;
            $guest_registration['updated_on'] = date('Y-m-d H:i:s');

            $find_guest->fill($guest_registration);
            $find_guest->save();
        } else {
            $guest_id = GuestRegistration::create($guest_registration)->guest_id;
        }

        $room = $this->getRoom($hotel_id, $staff_id, $data['room_number']);
        $room_id = $room['room_id'];
        $location = $room['location'];

        $guest_checkin_details = [
            'guest_id' => $guest_id,
            'hotel_id' => $hotel_id,
            'room_no' => $room_id,
            'check_in' => date('Y-m-d H:m:s'),
            'check_out' => $date['departure_date'].' '.$date['checkout_time'],
            'comment' => '',
            'main_guest' => $main_guest,
        ];

        $validation = Validator::make($guest_checkin_details, [
            'room' => 'required',
            'check_in' => 'required|date_format:"Y-m-d H:i:s"',
            'check_out' => 'required|date_format:"Y-m-d H:i:s"|after:'.$_checkin['check_in'],
            'comment' => 'string',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'create' => false,
                'error' => $validation->errors(),
            ], 400);
        }

        $find_guest_checkin_details = GuestCheckinDetails::where(
            function ($query) use ($room_id, $hotel_id, $guest_checkin_details) {
                return $query
                ->where('hotel_id', $hotel_id)
                ->where('status', 1)
                ->where('room_no', $room_id)
                ->where('check_in', '<=', $guest_checkin_details['check_in'])
                ->where('check_out', '>=', $guest_checkin_details['check_in']);
            }
        )->get();

        if (count($find_guest_checkin_details) > 0) {
            DB::rollback();

            return response()->json([
                'create' => false,
                'error' => [
                    'check_in' => [
                        "Room $location is in use in this date range",
                    ],
                ],
            ], 400);
        }

        GuestCheckinDetails::where(
            function ($query) use ($room_id, $hotel_id, $guest_checkin_details) {
                return $query
                ->where('hotel_id', $hotel_id)
                ->where('status', 1)
                ->where('room_no', $room_id)
                ->where('check_in', '<=', $guest_checkin_details['check_in'])
                ->where('check_out', '>=', $guest_checkin_details['check_in']);
            }
        )->update(['main_guest' => 0]);

        GuestCheckinDetails::create($guest_checkin_details);

        $this->saveLogTracker([
            'module_id' => 8,
            'action' => 'add',
            'prim_id' => $guest_id,
            'staff_id' => $staff_id,
            'date_time' => date('Y-m-d H:i:s'),
            'comments' => '',
            'hotel_id' => $hotel_id,
            'type' => 'API',
        ]);

        DB::commit();

        return response()->json([
            'create' => true,
        ], 200);
    }

    // specific guest check out
    public function checkOutGuest(Request $request)
    {
        return $this->checkOut($request, true);
    }

    // All guest check out
    public function checkOutRoom(Request $request)
    {
        return $this->checkOut($request, false);
    }

    //Express checkout
    public function expressCheckut(Request $request)
    {
        return $this->checkOut($request, true);
    }

    private function checkOut(Request $request, $individual)
    {
        $hotel_id = $request->hotel_id;
        $staff_id = $request->user()->staff_id;
        $data = $request->data;

        $this->configTimeZone($hotel_id);

        $room = $this->findRoomId($hotel_id, $staff_id, $data['room_number']);
        $room_id = $room['room_id'];
        $now = date('Y-m-d H:i:s');

        DB::beginTransaction();

        $full_name = '';
        $checking = GuestCheckinDetails::where(function ($query) use ($room_id, $now) {
            $query
                ->where('room_no', $room_id)
                ->where('status', 1)
                ->whereRaw("$now >= check_in and $now <= check_out");
        });

        if ($individual) {
            $full_name = $data['full_name'];
            $checking->whereHas('Guest', function ($query) use ($full_name) {
                $query->whereRaw("'$full_name' = CONACT(firstname, ' ', lastname)");
            });
        }

        $checking->update([
            'status' => 0,
            'check_out' => $now,
        ]);

        DB::commit();

        return response()->json([
            'update' => true,
        ], 200);
    }

    public function roomMove(Request $request)
    {
        try {
            $hotel_id = $request->hotel_id;
            $staff_id = $request->user()->staff_id;
            $data = $request->data;
            $this->configTimeZone($hotel_id);
            $room_number = $data['room_number'];
            $new_room_number = $data['new_room_number'];
            $current_room = $this->getRoom($hotel_id, $staff_id, $room_number);
            $new_room = $this->getRoom($hotel_id, $staff_id, $room_number);
            $full_name = $data['full_name'];

            DB::beginTransaction();

            $stay = GuestCheckinDetails::where(function ($query, $hotel_id, $current_room) {
                $query
                    ->where('hotel_id', $hotel_id)
                    ->where('room_no', $current_room['room_id']);
            })
            ->whereHas('Guest', function ($query) use ($full_name) {
                $query
                    ->whereRaw("'$full_name' = CONACT(firstname, ' ', lastname)");
            })
            ->select('guest_id')
            ->with(['Guest', function ($query) {
                $query
                    ->select('phone');
            }])
            ->first();

            if ($stay) {
                $room_move = RoomMove::create([
                    'guest_id' => $stay->guest_id,
                    'phone' => $stay->guest->phone_no,
                    'current_room_no' => $stay->room_no,
                    'new_room_no' => $new_room['room_id'],
                    'comment' => '',
                    'hotel_id' => $hotel_id,
                    'created_by' => $staff_id,
                    'created_on' => date('Y-m-d H:i:s'),
                    'updated_by' => 0,
                    'updated_on' => null,
                ]);

                $stay->room_no = $new_room['room_id'];
                $stay->save();
            }

            DB::commit();

            return response()->json([
                'room_move' => true,
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'room_move' => false,
                'error' => $e,
            ], 200);
        }
    }

    public function requestRoomStatus(Request $request)
    {
        try {
            $hotel_id = $request->hotel_id;
            $staff_id = $request->user()->staff_id;
            $data = $request->data;

            $room_number = $data['room_number'];
            $room = $this->getRoom($hotel_id, $staff_id, $room_number);
            $room_id = $room['room_id'];

            $hk = HousekeepingCleanings::where(function ($query) use ($hotel_id, $room_id) {
                $query
                    ->where('hotel_id', $hotel_id)
                    ->where('room_id', $room_id);
            })
            ->select([
                'front_desk_status',
                'hk_statu',
            ])
            ->first();

            $generic_status = 0;

            /*

            -----------------------------------------------
            |    front_desk_status    |     hk_status     |
            -----------------------------------------------
            |    1: Vacant,           |     1: Dirty      |
            |    2: Stay Over,        |     2: Cleaning,  |
            |    3: Due In,           |     3: Clean,     |
            |    4: Due Out,          |     4: Inspected, |
            |    5: Check Out         |     5: Pickup     |
            -----------------------------------------------

            UHLL
            --------------------
            |    Maid Codes    |
            --------------------
            |                  |
            |                  |
            |                  |
            |                  |
            |                  |
            |                  |
            |                  |
            --------------------

            */

            // Cleaning in-progress 3: [( 1 || 2 || 3 || 4 || 5 ) && ( 2 )]
            if (($hk->front_desk_status == 1 || $hk->front_desk_status == 2 || $hk->front_desk_status == 3 || $hk->front_desk_status == 4 || $hk->front_desk_status == 5) && $hk->hk_statu == 2) {
                $generic_status = 3;
            } //Passed inspection 8: [( 1 || 3 || 5 ) && ( 4 )]
            elseif (($hk->front_desk_status == 1 || $hk->front_desk_status == 3 || $hk->front_desk_status == 5) && $hk->hk_statu == 4) {
                $generic_status = 8;
            } //Pick-up 7: [( 1 || 3 || 5 ) && ( 5 )]
            elseif (($hk->front_desk_status == 1 || $hk->front_desk_status == 3 || $hk->front_desk_status == 5) && $hk->hk_statu == 5) {
                $generic_status = 7;
            } // Room Cleaned / occupied 11: [( 2 || 4 )  && ( 3 )]
            elseif (($hk->front_desk_status == 2 || $hk->front_desk_status == 4) && $hk->hk_statu == 3) {
                $generic_status = 11;
            } // Room Cleaned / Vacant 12: [( 1 || 3 || 5 ) && ( 3 )]
            elseif (($hk->front_desk_status == 1 || $hk->front_desk_status == 2 || $hk->front_desk_status == 3) && $hk->hk_statu == 3) {
                $generic_status = 12;
            } // Cleaning Request | occupied 13: [( 2 || 4 ) && ( 1 )]
            elseif (($hk->front_desk_status == 2 || $hk->front_desk_status == 4) && $hk->hk_statu == 1) {
                $generic_status = 13;
            } // Cleaning Request | Vacant 14: [( 1 || 3 || 5 ) && ( 1 )]
            elseif (($hk->front_desk_status == 1 || $hk->front_desk_status == 3 || $hk->front_desk_status == 5) && $hk->hk_statu == 1) {
                $generic_status = 14;
            }

            return response()->json([
                'room_number' => $room_number,
                'generic_status' => $generic_status,
            ], 200);
        } catch (\Exception $e) {
            return $e;
        }
    }
}
