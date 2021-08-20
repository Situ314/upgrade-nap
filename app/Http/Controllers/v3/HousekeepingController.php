<?php

namespace App\Http\Controllers\v3;

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
    public function updateHsk(Request $request)
    {
        // Capturar hotel id, por default el valor es null, en caso de no enviarlo
        $hotel_id = isset($request->hotel_id) ? $request->hotel_id : null;

        $validator = Validator::make($request->all(), [
            "room_status" => "array|required",
            "room_status.*.room" => [
                "distinct",
                'string',
                'required',
                Rule::exists('hotel_rooms', 'location')->where(function ($q) use ($hotel_id) {
                    $q->where('hotel_id', $hotel_id)->where('active', 1);
                })
            ],
            "room_status.*.status" => "numeric|required|in:1,2,3,4"
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'        => "error",
                'message'       => "Error during the validation of the information",
                'errors'        => $validator->errors()
            ], 400);
        }

        $staff_id = $request->user()->staff_id;

        if (!$this->validateHotelId($hotel_id, $staff_id)) {
            return response()->json([
                'status'        => "error",
                "message"       => "User does not have access to the hotel",
                'errors'   => null
            ], 400);
        }

        $this->configTimeZone($hotel_id);

        $room_status = $request->room_status;

        foreach ($room_status as $key => $status) {
            $room = \App\Models\HotelRoom::where('location', $status['room'])->where('hotel_id', $hotel_id)->where('active', 1)->first();
            if ($room) {
                $hk_status = $status["status"];

                // switch ($status["status"]) {
                //     case 'DIRTY':
                //         $hk_status = 1;
                //         break;
                //     case 'CLEANING':
                //         $hk_status = 2;
                //         break;
                //     case 'CLEAN':
                //         $hk_status = 3;
                //         break;
                //     case 'INSPECTED':
                //         $hk_status = 4;
                //         break;
                // }

                $HousekeepingData = [
                    "hotel_id" => $hotel_id,
                    "staff_id" => $request->user()->staff_id,
                    "rooms" => [ [ "room_id"   => $room->room_id, "hk_status" => $hk_status, ] ]
                ];

                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL             => "https://hotel.mynuvola.com/index.php/housekeeping/pmsHKChange",
                    CURLOPT_RETURNTRANSFER  => true,
                    CURLOPT_ENCODING        => "",
                    CURLOPT_MAXREDIRS       => 10,
                    CURLOPT_TIMEOUT         => 10,
                    CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST   => "POST",
                    CURLOPT_POSTFIELDS      => json_encode($HousekeepingData)
                ));
                $response = curl_exec($curl);
                $err = curl_error($curl);
                curl_close($curl);
            }
        }

        return response()->json([
            'status'    => 'success',
            'message'   => "Successfully updated",
            "response" => $response, 
            "err" => $err
        ], 200);

    }
}
