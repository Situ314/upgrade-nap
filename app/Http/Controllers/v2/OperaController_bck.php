<?php

namespace App\Http\Controllers\v2;

use Illuminate\Support\Arr;
use App\Http\Controllers\Controller;
use App\Models\GuestCheckinDetails;
use App\Models\GuestRegistration;
use App\Models\IntegrationsActive;
use App\Models\IntegrationsGuestInformation;
use DB;
use Illuminate\Http\Request;
use Spatie\ArrayToXml\ArrayToXml;

class OperaController extends Controller
{
    // public function index(Request $request)
    // {
    //     $message = "Integration not found";

    //     $type = $request->Type;
    //     $data = (object) $request->Data["$type"];

    //     $this->writeLog("opera", null, json_encode([
    //         "all" => $request->all(),
    //     ]));

    //     $ResortID = $data->ResortID;
    //     $int = $this->getDataIntegartion($ResortID);
    //     if ($int) {
    //         $hotel_id = $int->hotel_id;
    //         $staff_id = $int->created_by;

    //         $this->configTimeZone($hotel_id);

    //         $method = "__$type";
    //         if (method_exists($this, $method)) {
    //             $rs = $this->$method($hotel_id, $staff_id, $data);
    //             return response()->json($rs, 200);
    //         } else {
    //             $message = "$type is not active";
    //         }
    //     }

    //     $this->writeLog("opera", $hotel_id, json_encode([
    //         "message" => $message,
    //         "data" => $data
    //     ]));

    //     return response()->json([
    //         "result" => false,
    //         "message" => $message
    //     ], 400);
    // }

    // private function __Reservation($hotel_id, $staff_id, $data)
    // {

    //     $guest_id = null;
    //     $GuestCheckinDetails = null;

    //     $IntegrationsGuestInformation = IntegrationsGuestInformation::where('hotel_id', $hotel_id)->where('guest_number', $data->ProfileID)->first();
    //     if ($IntegrationsGuestInformation) {
    //         $guest_id = $IntegrationsGuestInformation->guest_id;
    //         $reservation_number = $data->ReservationID;
    //         $GuestCheckinDetails = GuestCheckinDetails::where('hotel_id', $hotel_id)->where('reservation_number', $reservation_number)->first();
    //     }
    //     $rs = $this->proccessReservation($hotel_id, $staff_id, $guest_id, $GuestCheckinDetails, $data);
    //     return $rs;
    // }

    // private function proccessReservation($hotel_id, $staff_id, $guest_id = null, $GuestCheckinDetails = null, $data)
    // {
    //     DB::beginTransaction();
    //     $result = false;
    //     $message = "";
    //     $now = date('Y-m-d H:i:s');
    //     try {
    //         if (!is_null($guest_id)) {
    //             $GuestRegistration = GuestRegistration::find($guest_id);

    //             if ($GuestRegistration) {
    //                 $guest_registration = [
    //                     'firstname'     => isset($data->FirstName) ? $data->FirstName : "",
    //                     'lastname'      => isset($data->LastName) ? $data->LastName : "",
    //                     'address'       => isset($data->Direction) ? $data->Direction : "",
    //                     'updated_on'    => $now,
    //                     'updated_by'    => $staff_id
    //                 ];

    //                 $GuestRegistration->fill($guest_registration);
    //                 $GuestRegistration->save();

    //                 if (
    //                     (isset($data->ArrivaleDate) && !empty($data->ArrivaleDate)) && (isset($data->DepartureDate) && !empty($data->DepartureDate))
    //                 ) {
    //                     $check_in = str_replace("T", " ", $data->ArrivaleDate);
    //                     $check_out = str_replace("T", " ", $data->DepartureDate);

    //                     switch ($data->ReservationStatus) {
    //                         case 'RESERVED':
    //                             $reservation_status = 0;
    //                             $status = 1;
    //                             break;
    //                         case 'CHECKED_IN':
    //                             $reservation_status = 1;
    //                             $status = 1;
    //                             break;
    //                         case 'CANCELLED':
    //                             $reservation_status = 2;
    //                             $status = 0;
    //                             break;
    //                         case 'CHECKED_OUT':
    //                             $reservation_status = 3;
    //                             $status = 0;
    //                             break;
    //                         default:
    //                             $reservation_status = 4;
    //                             $status = 0;
    //                             break;
    //                     }

    //                     if ($status == 0) {
    //                         if (strtotime($check_out) > strtotime($now)) {
    //                             $check_out = $now;
    //                         }
    //                     }

    //                     $room_no = 0;
    //                     if ($data->RoomNumber) {
    //                         $room = $this->getRoom($hotel_id, $staff_id, $data->RoomNumber);
    //                         if ($room) {
    //                             $room_no = $room["room_id"];
    //                         }
    //                     }

    //                     $guest_checkin_details = [
    //                         'room_no'               => $room_no,
    //                         'check_in'              => $check_in,
    //                         'check_out'             => $check_out,
    //                         'status'                => $status,
    //                         'reservation_status'    => $reservation_status
    //                     ];

    //                     if (
    //                         ($GuestCheckinDetails->room_no != $room_no) && ($GuestCheckinDetails->reservation_status == 1 && $reservation_status == 1)
    //                     ) {
    //                         $GuestCheckinDetails->status = 0;
    //                         $GuestCheckinDetails->reservation_status = 3;
    //                         $GuestCheckinDetails->reservation_number = $GuestCheckinDetails->reservation_number . "_RM";
    //                         $GuestCheckinDetails->save();

    //                         RoomMove::create([
    //                             'guest_id'          => $guest_id,
    //                             'phone'             => '',
    //                             'current_room_no'   => $GuestCheckinDetails->room_no,
    //                             'new_room_no'       => $room_no,
    //                             'comment'           => '',
    //                             'hotel_id'          => $hotel_id,
    //                             'created_by'        => $staff_id,
    //                             'created_on'        => $now,
    //                             'updated_by'        => 0,
    //                             'updated_on'        => null,
    //                         ]);

    //                         GuestCheckinDetails::create([
    //                             'guest_id'              => $guest_id,
    //                             'hotel_id'              => $hotel_id,
    //                             'room_no'               => $room_no,
    //                             'check_in'              => $check_in,
    //                             'check_out'             => $check_out,
    //                             'comment'               => '',
    //                             'status'                => $status,
    //                             'main_guest'            => 0,
    //                             'reservation_status'    => $reservation_status,
    //                             'reservation_number'    => $reservation_number
    //                         ]);
    //                     } else {
    //                         $GuestCheckinDetails->fill($guest_checkin_details);
    //                         $GuestCheckinDetails->save();
    //                     }

    //                     DB::commit();
    //                     return [
    //                         "result" => true,
    //                         "message" => null
    //                     ];
    //                 }
    //             }
    //             DB::rollback();
    //         } else {
    //             $guest_registration = [
    //                 'hotel_id'      => $hotel_id,
    //                 'firstname'     => isset($data->FirstName) ? $data->FirstName : "",
    //                 'lastname'      => isset($data->LastName) ? $data->LastName : "",
    //                 'address'       => isset($data->Direction) ? $data->Direction : "",
    //                 'email_address' => "",
    //                 'phone_no'      => "",
    //                 'angel_status'  => $this->validateAngelStatus($hotel_id),
    //                 'language'      => "en",
    //                 'created_on'    => date('Y-m-d H:i:s'),
    //                 'created_by'    => $staff_id,
    //                 "address"       => '',
    //                 "state"         => '',
    //                 'zipcode'       => '',
    //                 'comment'       => '',
    //                 'city'          => ''
    //             ];

    //             $guest_id = GuestRegistration::create($guest_registration)->guest_id;
    //             IntegrationsGuestInformation::create([
    //                 'hotel_id'      => $hotel_id,
    //                 'guest_id'      => $guest_id,
    //                 'guest_number'  => $data->ProfileID
    //             ]);

    //             $room_no = 0;
    //             if ($data->RoomNumber) {
    //                 $room = $this->getRoom($hotel_id, $staff_id, $data->RoomNumber);
    //                 if ($room) {
    //                     $room_no = $room["room_id"];
    //                 }
    //             }

    //             if (
    //                 (isset($data->ArrivaleDate) && !empty($data->ArrivaleDate)) && (isset($data->DepartureDate) && !empty($data->DepartureDate))
    //             ) {
    //                 $check_in = str_replace("T", " ", $data->ArrivaleDate);
    //                 $check_out = str_replace("T", " ", $data->DepartureDate);

    //                 switch ($data->ReservationStatus) {
    //                     case 'RESERVED':
    //                         $reservation_status = 0;
    //                         $status = 1;
    //                         break;
    //                     case 'CHECKED_IN':
    //                         $reservation_status = 1;
    //                         $status = 1;
    //                         break;
    //                     case 'CANCELLED':
    //                         $reservation_status = 2;
    //                         $status = 0;
    //                         break;
    //                     case 'CHECKED_OUT':
    //                         $reservation_status = 3;
    //                         $status = 0;
    //                         break;
    //                     default:
    //                         $reservation_status = 4;
    //                         $status = 0;
    //                         break;
    //                 }

    //                 if ($status == 0) {
    //                     if (strtotime($check_out) > strtotime($now)) {
    //                         $check_out = $now;
    //                     }
    //                 }

    //                 if (isset($data->ReservationID) && !empty($data->ReservationID)) {
    //                     $reservation_number = $data->ReservationID;

    //                     $guest_checkin_details = [
    //                         'guest_id'              => $guest_id,
    //                         'hotel_id'              => $hotel_id,
    //                         'room_no'               => $room_no,
    //                         'check_in'              => $check_in,
    //                         'check_out'             => $check_out,
    //                         'comment'               => '',
    //                         'status'                => $status,
    //                         'main_guest'            => 0,
    //                         'reservation_status'    => $reservation_status,
    //                         'reservation_number'    => $reservation_number
    //                     ];

    //                     $sno = GuestCheckinDetails::create($guest_checkin_details)->sno;

    //                     $this->saveLogTracker([
    //                         'module_id' => 8,
    //                         'action'    => 'add',
    //                         'prim_id'   => $sno,
    //                         'staff_id'  => $staff_id,
    //                         'date_time' => date('Y-m-d H:i:s'),
    //                         'comments'  => '',
    //                         'hotel_id'  => $hotel_id,
    //                         'type'      => 'API-opera'
    //                     ]);
    //                     DB::commit();
    //                     return [
    //                         "result" => true,
    //                         "message" => null
    //                     ];
    //                 } else {
    //                     $message = "ReservationID not  provided";
    //                 }
    //             } else {
    //                 $message = "ArrivaleDate and DepartureDate is not valid";
    //             }
    //         }

    //         DB::rollback();
    //     } catch (\Exception $e) {
    //         echo $e;
    //         DB::rollback();
    //         $message = "Internal record";
    //         $this->writeLog("opera", $hotel_id, "Error::" . $e);
    //     }

    //     return [
    //         "result"    => false,
    //         "message"   => $message
    //     ];
    // }

    // private function getDataIntegartion($ResortID)
    // {
    //     $ia = IntegrationsActive::where('pms_hotel_id', $ResortID)
    //         ->whereHas('integration', function ($q) {
    //             $q->where('name', 'oracle_opera');
    //         })->first();
    //     return $ia;
    // }

    public function activityService(Request $request)
    {
        //\Log::info("Opera: activityService");
        //\Log::info($request->getContent());
        //\Log::info('IP: ' . request()->ip());
        return $this->SwitchRequest($this->FormatXML($request));
    }

    public function nameService(Request $request)
    {
        $hotel_id = $request->hotel_id;
        $staff_id = $request->staff_id;
        $data = $request->data;
        $type = 'ProfileRegistration';
        $this->dispatch(new \App\Jobs\Opera($hotel_id, $staff_id, $type, Arr::get($data, 'Body.NewProfileRequest')));

        return 'true';
        // $this->SwitchNameRequest($this->FormatXML($request));
    }

    public function reservationService(Request $request)
    {
        $hotel_id = $request->hotel_id;
        $staff_id = $request->staff_id;
        $data = $request->data;
        $type = 'GuestStatusNotificationExtRequest';

        $this->dispatch(new \App\Jobs\Opera($hotel_id, $staff_id, $type, $data));

        return $this->SwitchRequest($this->FormatXML($request));
    }

    public function FormatXML(Request $request)
    {
        $response = $request->getContent();
        $xmlString = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
        $xmlString = preg_replace('/([a-zA-Z0-9_-]+):([a-zA-Z0-9_-]+)/', '$1$2', $xmlString);
        $xml = simplexml_load_string($xmlString);
        // Pasar de XML String a Array
        $str_json = json_encode($xml);
        $arrayData = json_decode($str_json, true);

        return $arrayData;
    }

    public function BuildXMLResponse($action, $unique_id, $source)
    {
        $success = [
            '_attributes' => [
                'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
                'xmlns:soap' => 'http://schemas.xmlsoap.org/soap/envelope/',
            ],
            'soap:Heade',
            'soap:Body' => [
                $action => [
                    '_attributes' => [
                        'xmlns' => 'http://htng.org/PWS/2008B/SingleGuestItinerary/Name/Types',
                    ],
                    'Result' => [
                        '_attributes' => [
                            'resultStatusFlag' => 'SUCCESS',
                            'code' => 'OPERA',
                        ],
                        'IDs' => [
                            '_attributes' => [
                                'xmlns' => 'http://htng.org/PWS/2008B/SingleGuestItinerary/Common/Types',
                            ],
                            'UniqueID' => [
                                '_attributes' => [
                                    'source' => 'OPERA',
                                ],
                                '_value' => $unique_id,

                            ],
                        ],
                    ],
                ],
            ],
        ];

        return ArrayToXml::convert($success, 'soap:Envelope');
    }
}
