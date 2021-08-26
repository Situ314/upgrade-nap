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
    private $DIRTY = 1;
    private $CLEANING = 2;
    private $CLEAN = 3;
    private $INSPECTED = 4;
    private $QUEUE = 5;
    private $RUSH = 6;
    private $OUT_OF_ORDER = 7;
    private $OUT_OF_SERVICE = 8;


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
            "room_status.*.status" => "numeric|required|in:1,2,3,4,5,6,7,8",
            "room_status.*.flag" => "boolean"
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
        $now = date("Y-m-d H:i:s");

        $IntegrationsActive = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)
            ->where('int_id', 16)
            ->where('state', 1)
            ->first();

        $reason_id = 0;
        if ($IntegrationsActive) {
            $reason_id = $IntegrationsActive->config["hk_reasons_id"];
        }


        foreach ($room_status as $key => $status) {
            $room = \App\Models\HotelRoom::where('location', $status['room'])->where('hotel_id', $hotel_id)->where('active', 1)->first();
            if ($room) {
                $hk_status = $status["status"];
                $flag = isset($status["flag"]) ? $status["flag"] : false;

                $room_id = $room->room_id;

                switch ($hk_status) {
                    case $this->QUEUE:
                        $this->queue($hotel_id, $staff_id, $room_id, $flag);
                        break;
                    case $this->RUSH:
                        $this->rush($hotel_id, $staff_id, $room_id, $flag);
                        break;
                    case $this->OUT_OF_ORDER:
                        $this->outOfOrder_outOfService($hotel_id, $staff_id, $room_id, $flag, 1, $reason_id);
                        break;
                    case $this->OUT_OF_SERVICE:
                        $this->outOfOrder_outOfService($hotel_id, $staff_id, $room_id, $flag, 2, $reason_id);
                        break;
                }

                if (in_array($hk_status, [$this->DIRTY, $this->CLEANING, $this->CLEAN, $this->INSPECTED])) {

                    if ($hk_status == $this->INSPECTED) {
                        $cleanings = \App\Models\HousekeepingCleanings::where('hotel_id', $this->hotel_id)
                            ->where('room_id', $room_id)
                            ->orderBy('assigned_date', 'DESC')
                            ->orderBy('cleaning_id', 'DESC')
                            ->limit(1)
                            ->fiest();
                            
                        if ($cleanings) {
                            $cleanings->in_queue = 0;
                            $cleanings->save();
                        }
                    }

                    $HousekeepingData = [
                        "hotel_id" => $hotel_id,
                        "staff_id" => $request->user()->staff_id,
                        "rooms" => [
                            [
                                "room_id"   => $room->room_id,
                                "hk_status" => $hk_status,
                            ]
                        ]
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
        }

        return response()->json([
            'status'    => 'success',
            'message'   => "Successfully updated",
        ], 200);
    }

    private function queue($hotel_id, $staff_id,  $room_id, $flag)
    {
        $cleanings = \App\Models\HousekeepingCleanings::where('hotel_id', $this->hotel_id)
            ->where('room_id', $room_id)
            ->orderBy('assigned_date', 'DESC')
            ->orderBy('cleaning_id', 'DESC')
            ->limit(2)
            ->get();

        if (count($cleanings) > 0) {
            $currentCleanig = $cleanings[0]->in_queue;
            $currentCleanig->in_queue = $flag ? 1 : 0;
            $currentCleanig->save();
        } else {
            $now = date("Y-m-d H:i:s");
            $yesterday = date("Y-m-d H:i:s", strtotime($now . " - 1 days"));

            $currentCleanig = \App\Models\HousekeepingCleanings::create([
                "hotel_id"      => $hotel_id,
                "room_id"       => $room_id,
                "in_queue"      => $flag ? 1 : 0,
                "is_active"     => 1,
                "created_by"    => $yesterday,
                "changed_by"    => $staff_id,
            ]);
        }

        \App\Models\HousekeepingTimeline::create([
            'item_id'       => $currentCleanig->cleaning_id,
            'hotel_id'      => $hotel_id,
            'action'        => $flag ? 'CLEANING_CREATED' : 'CLEANING_DELETED',
            'is_active'     => 1,
            'changed_by'    => $staff_id,
            'changed_on'    => date('Y-m-d H:i:s'),
            'platform'      => 'HSK API V3'
        ]);
    }

    private function rush($hotel_id, $staff_id,  $room_id, $flag)
    {
        $cleanings = \App\Models\HousekeepingCleanings::where('hotel_id', $this->hotel_id)
            ->where('room_id', $room_id)
            ->orderBy('assigned_date', 'DESC')
            ->orderBy('cleaning_id', 'DESC')
            ->limit(2)
            ->get();

        if (count($cleanings) > 0) {
            $currentCleanig = $cleanings[0]->in_queue;
            $currentCleanig->in_queue = $flag ? 2 : 0;
            $currentCleanig->save();
        } else {
            $now = date("Y-m-d H:i:s");
            $yesterday = date("Y-m-d H:i:s", strtotime($now . " - 1 days"));

            $currentCleanig = \App\Models\HousekeepingCleanings::create([
                "hotel_id"      => $hotel_id,
                "room_id"       => $room_id,
                "in_queue"      => $flag ? 2 : 0,
                "is_active"     => 1,
                "created_by"    => $yesterday,
                "changed_by"    => $staff_id,
            ]);
        }

        \App\Models\HousekeepingTimeline::create([
            'item_id'       => $currentCleanig->cleaning_id,
            'hotel_id'      => $hotel_id,
            'action'        => $flag ? 'CLEANING_CREATED' : 'CLEANING_DELETED',
            'is_active'     => 1,
            'changed_by'    => $staff_id,
            'changed_on'    => date('Y-m-d H:i:s'),
            'platform'      => 'HSK API V3'
        ]);
    }

    /**
     * $ooo_oos == 1 "Out of order"
     * $ooo_oos == 2 "Out of service"
     */
    private function outOfOrder_outOfService($hotel_id, $staff_id, $room_id, $flag, $ooo_oos, $reason_id = 0)
    {
        $date = date('Y-m-d H:i:s');
        $roomOut = \App\Models\HotelRoomsOut::where('hotel_id', $hotel_id)
            ->where('room_id', $room_id)
            ->where('is_active', 1)
            ->whereRaw("'$date' BETWEEN start_date AND end_date")
            ->orderBy('room_out_id', 'DESC')
            ->first();

        if ($flag) {
            if ($roomOut) {
                $roomOut->end_date = date('Y-m-d H:i:s', strtotime($roomOut->end_date . ' +30 days'));
                $roomOut->status = $ooo_oos;
                $roomOut->save();
                \App\Models\HotelRoomsOut::where('hotel_id', $hotel_id)
                    ->where('room_id', $room_id)
                    ->where('is_active', 1)
                    ->whereRaw("'$date' BETWEEN start_date AND end_date")
                    ->whereNotIn('room_out_id', [$roomOut->room_out_id])
                    ->update(['is_active' => 0]);
            } else {
                \App\Models\HotelRoomsOut::create([
                    'room_id'       => $room_id,
                    'hotel_id'      => $hotel_id,
                    'status'        => $ooo_oos,
                    'hk_reasons_id' => $reason_id,
                    'start_date'    => $date,
                    'end_date'      => date('Y-m-d H:i:s', strtotime($date . ' +90 days')),
                    'comment'       => 'HSK API V3',
                    'is_active'     => 1,
                    'created_by'    => $staff_id,
                    'created_on'    => $date,
                ]);
            }
        } else {
            if ($roomOut) {
                $roomOut->is_active     = 0;
                $roomOut->updated_by    = $staff_id;
                $roomOut->updated_on    = $date;
                $roomOut->save();
            }
        }
    }

    public function synergexSendHskChangeStatus(Request $request)
    {

        $synergexUrl = 'https://75.112.128.89/Nuvola/Nuvola.aspx?UpdateRoomHKStatus';

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL             => $synergexUrl,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_ENCODING        => '',
            CURLOPT_MAXREDIRS       => 10,
            CURLOPT_TIMEOUT         => 0,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST   => 'POST',
            CURLOPT_POSTFIELDS      => json_encode([
                "room_id" => $request->room_id,
                "room_status" => $request->room_status
            ]),
            CURLOPT_HTTPHEADER      => ['Content-Type: application/json'],
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        echo $response;
    }
}
