<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use \App\Models\Hotel;
use \App\Models\GuestCheckinDetails;
use \App\Models\HotelRoom;
use \App\Models\LogTracker;
use Spatie\ArrayToXml\ArrayToXml;
use DateTime;
use Illuminate\Support\Facades\Mail;


class MaestroPmsFullReSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct() {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $hotels = [ 
            // 198, 
            // 208, 
            // 216, 
            // 217, 
            // 230, 
            // 231, 
            // 232 
        ];
        $__response = [];
        foreach ($hotels as $key => $hotel_id) {
            $IntegrationsActive = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)->first();
            if($IntegrationsActive)
            {
                $HotelId            = $IntegrationsActive->pms_hotel_id;
                $url                = $IntegrationsActive->config["url"];
                $agreed_upon_key    = $IntegrationsActive->config["agreed_upon_key"];
                $data               = $this->getSalt($url, $HotelId);
                $PasswordHash       = hash('sha256',$agreed_upon_key.$data->Salt);

                $this->configTimeZone($hotel_id);

                $now = date('Y-m-d');
                
                $GuestCheckinDetails = \App\Models\GuestCheckinDetails::with(['Guest','GuestPms'])
                ->where('hotel_id', $hotel_id)
                ->where('reservation_number', '!=', '')
                ->where('reservation_number', 'not like', '%_RM')
                ->whereRaw("'$now' BETWEEN DATE_FORMAT(check_in,'%Y-%m-%d') and DATE_FORMAT(check_out,'%Y-%m-%d')")
                ->get();

                foreach ($GuestCheckinDetails as $key => $stay) 
                {
                    $fullname = (empty($stay->guest->firstname) ? "" : $stay->guest->firstname).(empty($stay->guest->firstname.$stay->guest->lastname) ? "" : ", ").(empty($stay->guest->lastname) ? "" : $stay->guest->lastname);

                    $xml_response = ArrayToXml::convert([
                        'HotelId'       => $HotelId,
                        'PasswordHash'  => $PasswordHash,
                        'Action'        => 'ReservationInquiry',
                        'RequestData'   => [
                            'ReservationNumber' => $stay->reservation_number,
                            'LastName'          => $stay->guest->lastname
                        ]
                    ], 'Request');

                    $rs = $this->reservationInquery($url, $xml_response);

                    if(!is_null($rs)) {
                        \Log::error(json_encode($rs));
                        if($rs->Status === 'failure') {

                            $__response[$hotel_id][] = [
                                "request_inquiry"   => false,
                                "sno"               => $stay->sno,
                                "reservation_number" => $stay->reservation_number,
                                "fullname"          => $fullname,
                                "message"           => $rs->Message,                            
                            ];

                        } else {
                            if(isset($rs->Reservations)) {
                                $Reservations = $rs->Reservations;
                                if(isset($Reservations) && isset($Reservations->ReservationData)) {
                                    $ReservationData = $Reservations->ReservationData;
                                    if(isset($ReservationData->ReservationStatus) && !empty($ReservationData->ReservationStatus)) {
                                        $ReservationStatus = $ReservationData->ReservationStatus;
                                        
                                        $__reservation_status   = 0;
                                        $__status               = 0;
                                        $__update               = "";

                                        switch ($ReservationStatus) {
                                            case 'reserved':
                                                $__reservation_status = 0;
                                                $__status = 1;
                                                break;
                                            case 'checked_in':
                                                $__reservation_status = 1;
                                                $__status = 1;
                                                break;
                                            case 'cancelled':
                                                $__reservation_status = 2;
                                                $__status = 0;
                                                break;
                                            case 'checked_out':
                                                $__reservation_status = 3;
                                                $__status = 0;
                                                break;
                                        }

                                        if($stay->status != $__status) {
                                            $__update .= "status: $stay->status to $__status, ";
                                            $stay->status = $__status;
                                        }

                                        if($stay->reservation_status != $__reservation_status) {
                                            $__update .= "reservation_status: $stay->reservation_status to $__reservation_status, ";
                                            $stay->reservation_status = $__reservation_status; 
                                        }

                                        $check_in   = (new DateTime($ReservationData->ArrivalDate))->format('Y-m-d H:i:s');                                    
                                        if($stay->check_in != $check_in) {
                                            $__update .= "check_in: $stay->check_in to $check_in, ";
                                            $stay->check_in = $check_in;
                                        }

                                        $check_out  = (new DateTime( $ReservationData->DepartureDate))->format('Y-m-d H:i:s');
                                        if($stay->check_out != $check_out) {
                                            $__update .= "check_out: $stay->check_out to $check_out, ";
                                            $stay->check_out = $check_out;
                                        }

                                        $room_no = 0;
                                        if (isset($ReservationData->Room) && isset($ReservationData->Room->RoomCode) && is_string($ReservationData->Room->RoomCode) && !empty($ReservationData->Room->RoomCode)) {
                                            $room_code  = $ReservationData->Room->RoomCode;
                                            $room       = $this->findRoomId($hotel_id, 1, $room_code);
                                            $room_no    = $room->room_id;
                                        }

                                        if($stay->room_no != $room_no) {
                                            $__update .= "room_no: $stay->room_no to $room_no, ";
                                            $stay->room_no = $room_no;
                                        }

                                        if(!empty($__update)) {
                                            $stay->save();
                                            $this->saveLogTracker([
                                                'module_id' => 8,
                                                'action'    => 'update',
                                                'prim_id'   => $stay->guest_id,
                                                'staff_id'  => 1,
                                                'date_time' => date('Y-m-d H:i:s'),
                                                'comments'  => "Update Guest information: $__update",
                                                'hotel_id'  => $hotel_id,
                                                'type'      => 'API-maestro_pms'
                                            ]);
                                            $__response[$hotel_id][] = [
                                                "request_inquiry"   => true,
                                                "sno"               => $stay->sno,
                                                "reservation_number" => $stay->reservation_number,
                                                "fullname"          => $fullname,
                                                "message"           => "Update Guest information: $__update",                            
                                            ];
                                        } else {
                                            $__response[$hotel_id][] = [
                                                "request_inquiry"   => true,
                                                "sno"               => $stay->sno,
                                                "reservation_number" => $stay->reservation_number,
                                                "fullname"          => $fullname,
                                                "message"           => "Synchronized record",                            
                                            ];
                                        }

                                        if($stay->guest_pms == null && isset($ReservationData->ClientCode)) {
                                            // \App\Models\IntegrationsGuestInformation::create([
                                            //     "hotel_id"      => $stay->hotel_id,
                                            //     "guest_id"      => $stay->guest_id,
                                            //     "guest_number"  => $ReservationData->ClientCode
                                            // ]);
                                        }
                                    }
                                }
                            }
                        }             
                    } else {
                        $__response[$hotel_id][] = [
                            "request_inquiry"   => false,
                            "sno"               => $stay->sno,
                            "reservation_number" => $stay->reservation_number,
                            "fullname"          => $fullname,
                            "message"           => "Operation timed out",                            
                        ];
                    }
                }
            }
        }

        $emails = [
            'jsanchez@mynuvola.co',
            'fidel@mynuvola.com'
        ];
        Mail::send('emails.maestro',[ "data" => $__response ], function($m) use ($emails){
            $m->from('integrations@api.mynuvola.net', 'Nuvola integrations');
            $m->to($emails);
            $m->subject('Maestro PMS - Request Inquiry Report');
        });
    }

    private function getSalt($url, $HotelId)
    {
        $port = explode(":", $url)[2];
        $port = explode("/", $port);
        $port = $port[0];

        $xml =
        "<?xml version='1.0' encoding='utf-8' ?>".
        "<Request>".
        "<Version>1.0</Version>".
        "<HotelId>$HotelId</HotelId>".
        "<GetSalt/>".
        "</Request>";

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_PORT            => $port,
            CURLOPT_URL             => $url,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_ENCODING        => "",
            CURLOPT_MAXREDIRS       => 10,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST   => "POST",
            CURLOPT_POSTFIELDS      => $xml,
            CURLOPT_SSL_VERIFYPEER  => 0,
            CURLOPT_SSL_VERIFYHOST  => 0,            
            CURLOPT_HTTPHEADER      => [ "content-type: text/xml" ],
        ]);

        $response   = curl_exec($curl);
        $err        = curl_error($curl);

        curl_close($curl);

        if ($err) {
            //echo "cURL Error #:" . $err;
            \Log::error($err);
            return null;
        } else {
            //\Log::info($response);
            
            $xml        = simplexml_load_string($response);
            $str_json   = json_encode($xml);
            $json       = json_decode($str_json);

            return $json;
        }
    }

    private function reservationInquery($url, $xml) 
    {
        $port = explode(":", $url)[2];
        $port = explode("/", $port);
        $port = $port[0];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_PORT            => $port,
            CURLOPT_URL             => $url,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_ENCODING        => "",
            CURLOPT_MAXREDIRS       => 10,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST   => "POST",
            CURLOPT_POSTFIELDS      => $xml,
            CURLOPT_SSL_VERIFYPEER  => 0,
            CURLOPT_SSL_VERIFYHOST  => 0,            
            CURLOPT_HTTPHEADER      => [ "content-type: text/xml" ],
        ]);
        $response   = curl_exec($curl);
        $err        = curl_error($curl);

        curl_close($curl);

        if ($err) {
            \Log::error($err);
            return null;
        } else {
            //\Log::info($response);
            $xml        = simplexml_load_string($response);
            $str_json   = json_encode($xml);
            $json       = json_decode($str_json);
            return $json;
        }
    }

    private function configTimeZone($hotel_id) 
    {
        $timezone = Hotel::find($hotel_id)->time_zone;
        date_default_timezone_set($timezone);
    }

    private function findRoomId($hotel_id, $staff_id, $room_code)
    {
        if(is_numeric($room_code)) {
            $__room = "";
            $strlen = strlen($room_code);
            if($strlen == 3) {
                $__room = "0$room_code";
            } else if($strlen == 4) {
                $sub = substr($room_code,0,1);
                if($sub === '0'){ 
                    $__room = substr($room_code, 1);
                }
            }
            $HotelRoom = HotelRoom::where('hotel_id', $hotel_id)
            ->where('active', 1)
            ->where(function($q) use ($room_code, $__room) {
                $q->where('location', $room_code)->orWhere('location', $__room);
            })
            ->orderBy('room_id', 'ASC')
            ->first();
        } else {
            $HotelRoom = HotelRoom::where('hotel_id', $hotel_id)
            ->where('active', 1)
            ->where('location', $room_code)
            ->orderBy('room_id', 'ASC')
            ->first();
        }

        if($HotelRoom) {
            return $HotelRoom;
        }

        $HotelRoom = HotelRoom::create([
            'hotel_id'      => $hotel_id,
            'location'      => $room_code,
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
            'prim_id'   => $HotelRoom->room_id,
            'module_id' => 17,
            'action'    => 'add',                
            'date_time' => date('Y-m-d H:i:s'),
            'comments'  => "Location $room_code created",                
            'type'      => 'API-maestro_pms'
        ]);

        return $HotelRoom;
    }

    private function saveLogTracker($__log_tracker) 
    {
        //$track_id = LogTracker::create($__log_tracker)->track_id;
        //return $track_id;
    }
}
