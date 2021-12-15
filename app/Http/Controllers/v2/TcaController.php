<?php

namespace App\Http\Controllers\v2;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Spatie\ArrayToXml\ArrayToXml;

use \App\Models\GuestRegistration;
use \App\Models\GuestCheckinDetails;
use \App\Models\RoomMove;

use DB;

class TcaController extends Controller
{
    public function index(Request $request) {
        \Log::info('TCA');
        \Log::info($request->getContent());

        $response = $request->getContent();                
        $xmlString = preg_replace("/(<\/?)([a-zA-Z0-9_-]+):([^>]*>)/", "$1$2$3", $response);        
        $xml = SimpleXML_Load_String($xmlString);                
        $xml = new \SimpleXMLElement($xml->asXML());
        
        $parse = $xml->{"SOAP-ENVBody"};
        $parse = utf8_decode(json_encode($parse));
        $parse = str_replace("HTNG_","",$parse);
        $parse = str_replace("@attributes","attributes",$parse);
        $parse = str_replace("htng","",$parse);
        $parse = str_replace("ota","",$parse);
        $parse = json_decode($parse);

        $_action = "";
        if(isset($parse->HotelStayUpdateNotifRQ)) {
            $_action = "HotelStayUpdateNotifRQ";
        } else if(isset($parse->HotelCheckInNotifRQ)) {
            $_action = "HotelCheckInNotifRQ";
        } else if(isset($parse->HotelCheckOutNotifRQ)) {
            $_action = "HotelCheckOutNotifRQ";
        } else if(isset($parse->HotelRoomMoveNotifRQ)) {
            $_action = "HotelRoomMoveNotifRQ";
        }

        if(!empty($_action)) {
            $hotel_id = 204;
            $user_id = 1;
            //$this->writeLog("TCA", $hotel_id, "XML:".json_encode($xmlString));
            //$this->writeLog("TCA", $hotel_id, "DATA:".json_encode($parse));
            $rs = $this->action($hotel_id, $user_id, $_action, $parse);
            //$this->writeLog("TCA", $hotel_id, "RS:".json_encode($rs));
            $arrStatus = [];
            if(isset($rs->message)) {
                $arrStatus = [
                    "Warning" => []//$rs->message
                ];
            }
            $_action .= "RS";
            $_action = str_replace("RQRS", "RS", $_action);
            $array = [
                '_attributes' => [
                    'xmlns:s' => 'http://schemas.xmlsoap.org/soap/envelope/'
                ],
                "s:Body" => [
                    "_attributes" => [
                        "xmlns:xsi" => "http://www.w3.org/2001/XMLSchema-instance",
                        "xmlns:xsd" => "http://www.w3.org/2001/XMLSchema"
                    ],
                    "HTNG_$_action" => [
                        "_attributes" => [
                            //"TimeStamp" =>"2018-08-30T20:34:43.0940164-03:00",
                            // "EchoToken" =>"099a911d-fa2e-4bd8-9240-b5002ea88a5b",
                            "Version"=>"1.0",
                            "TargetName"=>"NUVOLA_$hotel_id",
                            "xmlns"=>"http://htng.org/2011B"
                        ],
                        "$rs->status" => [],//$arrStatus //Success, Error
                    ]
                ]
            ];
            $xml =  new ArrayToXml($array, 's:Envelope');
            $dom = $xml->toDom();
            $dom->encoding = 'utf-8';
            $xml_response = $dom->saveXML();
            return response($xml_response, 200)->header('Content-Type', 'text/xml');
        }
    }

    private function action($hotel_id, $user_id, $_action, $data) {
        if(method_exists($this, $_action)) {        
            return $this->$_action($hotel_id, $user_id, $data);
        }
        return null;
    }

    /**
     * esta funcion recibe:
     * 
     * * Reserved
     * * Update information
     * * Canlled
     */
    private function HotelStayUpdateNotifRQ($hotel_id, $user_id, $data) { return $this->__clear($hotel_id, $user_id, $data, "HotelStayUpdateNotifRQ"); }
    private function HotelCheckInNotifRQ($hotel_id, $user_id, $data)    { return $this->__clear($hotel_id, $user_id, $data, "HotelCheckInNotifRQ"); }
    private function HotelCheckOutNotifRQ($hotel_id, $user_id, $data)   { return $this->__clear($hotel_id, $user_id, $data, "HotelCheckOutNotifRQ"); }
    
    private function HotelRoomMoveNotifRQ($hotel_id, $user_id, $data) {
        DB::beginTransaction();
        try {
            $this->configTimeZone($hotel_id);
            $now = date('Y-m-d H:i:s');

            if(isset($data->HotelRoomMoveNotifRQ)) {
                $HotelRoomMoveNotifRQ = $data->HotelRoomMoveNotifRQ;
                if(isset($HotelRoomMoveNotifRQ->SourceRoomInformation) && $HotelRoomMoveNotifRQ->DestinationRoomInformation) {

                    $SourceRoomInformation      = $HotelRoomMoveNotifRQ->SourceRoomInformation;
                    $DestinationRoomInformation = $HotelRoomMoveNotifRQ->DestinationRoomInformation;

                    $reservation_number         = $SourceRoomInformation->HotelReservations->HotelReservation->UniqueID->attributes->ID;
                    $__current_room             = $SourceRoomInformation->Room->RoomType->attributes->RoomID;
                    $__new_room                 = $DestinationRoomInformation->Room->RoomType->attributes->RoomID;
                
                    $room_no = 0;
                    if (!empty($__current_room)) {
                        $room       = $this->getRoom($hotel_id, $user_id, $__current_room);
                        $room_no    = (int)$room["room_id"];
                    }
                
                    $GuestCheckinDetails = GuestCheckinDetails::where('hotel_id', $hotel_id)
                        ->where('room_no', $room_no)
                        ->where('reservation_number', $reservation_number)
                        ->first();

                    if($GuestCheckinDetails) {
                        $check_in = $GuestCheckinDetails->check_in;
                        $check_out = $GuestCheckinDetails->check_out;
    
                        $GuestCheckinDetails->check_out = $now;
                        $GuestCheckinDetails->status = 0;
                        $GuestCheckinDetails->reservation_status = 3;
                        $GuestCheckinDetails->reservation_number = $GuestCheckinDetails->reservation_number."_RM";
                        $GuestCheckinDetails->save();
    
                        $room_no = 0;
                        if (!empty($__current_room)) {
                            $room       = $this->getRoom($hotel_id, $user_id, $__new_room);
                            $room_no    = (int)$room["room_id"];
                        }
    
                        $sno = GuestCheckinDetails::create([
                            'guest_id'              => $GuestCheckinDetails->guest_id,
                            'hotel_id'              => $GuestCheckinDetails->hotel_id,
                            'room_no'               => $room_no,
                            'check_in'              => $check_in,
                            'check_out'             => $check_out,
                            'comment'               => '',
                            'status'                => 1,
                            'main_guest'            => 0,
                            'reservation_status'    => 1,
                            'reservation_number'    => $reservation_number
                        ])->sno;
    
                        RoomMove::create([
                            'guest_id'          => $GuestCheckinDetails->guest_id,
                            'phone'             => '',
                            'current_room_no'   => $GuestCheckinDetails->room_no,
                            'new_room_no'       => $room_no,
                            'comment'           => '',
                            'hotel_id'          => $hotel_id,
                            'created_by'        => $user_id,
                            'created_on'        => $now,
                            'updated_by'        => 0,
                            'updated_on'        => null,
                        ]);
    
                        DB::commit();
                        return (object) [ "status" => "Success" ];
                    }
                }
            }

            DB::rollback();
            return (object) [ "status" => "Success" ];

        } catch (\Exception $e) {
            DB::rollback();
            $warning = $e;
            //$this->writeLog("TCA", $hotel_id, "Error:");
            \Log::info($e);
            return (object)[
                "status"    => "Success",
                "message"   => $warning
            ];
        }
        
    }

    private function __clear($hotel_id, $user_id, $data, $node) {
        $warning = '';
        if(isset($data->{"$node"})) {
            $__da = $data->{"$node"};
            if(isset($__da->HotelReservations)){
                $HotelReservations = $__da->HotelReservations;
                if(isset($HotelReservations->HotelReservation)){
                    $HotelReservation = $HotelReservations->HotelReservation;

                    $__reservation_status = "";
                    $__reservation_number = "";
                    $__room         = "";
                    $__start        = "";
                    $__end          = "";
                    $__firstname    = "";
                    $__lastname     = "";
                    $__zipcode      = "";
                    $__city         = "";
                    $__phone        = "";
                    $__email        = "";

                    if(isset($HotelReservation->attributes)) {
                        $attributes = $HotelReservation->attributes;
                        if(isset($attributes->ResStatus)) {
                            $__reservation_status = $attributes->ResStatus;
                        }
                    }

                    if(isset($HotelReservation->UniqueID)) {
                        $__unique_id = [];
                        $UniqueID = $HotelReservation->UniqueID;
                        if(is_array($UniqueID)) {
                            $__unique_id = $UniqueID;
                        } else {
                            $__unique_id[] = $UniqueID;
                        }

                        foreach ($__unique_id as $key => $value) {
                            if( $value->attributes->ID_Context == "PMSInnsist" ) {
                                $__reservation_number = $value->attributes->ID;
                            }
                        }
                    }

                    if(empty($__reservation_status)) {
                        $warning[] = "ResStatus is not provided";
                    }

                    if(empty($__reservation_number)) {
                        $warning[] = "ID of PMSInnsist is not provided";
                    }

                    if(!empty($__reservation_status) && !empty($__reservation_number)) {

                        if(isset($HotelReservation->RoomStays)) {
                            $RoomStays = $HotelReservation->RoomStays;
                            if(isset($RoomStays->RoomStay)) {
                                $RoomStay = $RoomStays->RoomStay;

                                if(isset($RoomStay->RoomTypes)) {
                                    $RoomTypes = $RoomStay->RoomTypes;
                                    if(isset($RoomTypes->RoomType)) {
                                        $RoomType = $RoomTypes->RoomType;
                                        if(isset($RoomType->attributes)) {
                                            $attributes = $RoomType->attributes;
                                            if(isset($attributes->RoomID)) {
                                                $__room = $attributes->RoomID;
                                            }
                                        }
                                    }
                                }

                                if(isset($RoomStay->TimeSpan)) {
                                    $TimeSpan = $RoomStay->TimeSpan;
                                    if(isset($TimeSpan->attributes)) {
                                        $attributes = $TimeSpan->attributes;
                                        $__start = $attributes->Start;
                                        $__end = $attributes->End;
                                    }
                                }
                            }
                        }
                        
                        if(isset($HotelReservation->ResGuests)) {
                            $ResGuests = $HotelReservation->ResGuests;
                            if(isset($ResGuests->ResGuest)) {
                                $ResGuest = $ResGuests->ResGuest;
                                if(isset($ResGuest->Profiles)) {
                                    $Profiles = $ResGuest->Profiles;
                                    if(isset($Profiles->ProfileInfo)) {
                                        $ProfileInfo = $Profiles->ProfileInfo;
                                        if(isset($ProfileInfo->Profile)){
                                            $Profile = $ProfileInfo->Profile;
                                            if(isset($Profile->Customer)) {
                                                $Customer = $Profile->Customer;
    
                                                if(isset($Customer->PersonName)) {
                                                    $PersonName = $Customer->PersonName;
                                                    if(isset($PersonName->GivenName)) {
                                                        $__firstname = $PersonName->GivenName;
                                                    }
                                                    if(isset($PersonName->Surname)) {
                                                        $__lastname = $PersonName->Surname;
                                                    }
                                                }
  
                                                if(isset($Customer->Telephone)){
                                                    $Telephone = $Customer->Telephone;
                                                    if(isset($Telephone->attributes) && isset($Telephone->attributes->PhoneNumber) && !empty($Telephone->attributes->PhoneNumber)) {
                                                        $__phone = $Telephone->attributes->PhoneNumber;
                                                    }
                                                }

                                                if(isset($Customer->Email) && !empty($Customer->Email)){
                                                    $__email = $Customer->Email;
                                                }
                                                
                                                if(isset($Customer->Address)) {
                                                    $Address = $Customer->Address;
                                                    if(isset($Address->PostalCode)) { $__zipcode = $Address->PostalCode; }
                                                    if(isset($Address->CityName)) { $__city = $Address->CityName; }
                                                }
                                            }                                        
                                        }
                                    }
                                }
                            }                            
                        }

                        $__data = (object)[
                            "reservation_status"    => $__reservation_status,
                            "reservation_number"    => $__reservation_number,
                            "room"                  => $__room,
                            "start"                 => $__start,
                            "end"                   => $__end,
                            "firstname"             => $__firstname,
                            "lastname"              => $__lastname,
                            "zipcode"               => $__zipcode,
                            "city"                  => $__city,
                            "phone_no"              => $__phone,
                            "email_address"         => $__email
                        ];

                        $rs = $this->__processData($hotel_id,$user_id, $__data);
                        return $rs;
                    }

                    return (object)[
                        "status"    => "Success"
                    ];
                }
            }
        }

        return (object)[
            "status"    => "Success",
            "message"   => $warning
        ];
    }

    private function __processData($hotel_id, $user_id, $data) {
        //Validar que la estadia no exista
        $GuestCheckinDetails = GuestCheckinDetails::where('hotel_id', $hotel_id)->where('reservation_number', $data->reservation_number)->first();
        //Si existe, guardar la estadia
        if($GuestCheckinDetails) {
            return $this->__updateStay($hotel_id, $user_id, $data, $GuestCheckinDetails);
        } else {
            return $this->__saveStay($hotel_id, $user_id, $data);
        }
    }

    private function __saveStay($hotel_id, $user_id, $data) {
        DB::beginTransaction();
        try {
            $this->configTimeZone($hotel_id);
            $now = date('Y-m-d H:i:s');
            $guest_registration = [
                'hotel_id'      => $hotel_id,
                'is_active'     => 1,
                'firstname'     => $data->firstname,
                'lastname'      => $data->lastname,
                'zipcode'       => $data->zipcode,
                'email_address' => $data->email_address,
                'city'          => $data->city,
                'phone_no'      => $data->phone_no,
                'comment'       => '',
                'created_on'    => $now,
                'created_by'    => $user_id,
                'updated_on'    => null,
                'updated_by'    => null,
                'id_device'     => null,
                'dob'           => null,
                'language'      => '',
                'address'       => '',
                'state'         => '',
                'angel_status'  => $this->validateAngelStatus($hotel_id)
            ];

            $GuestRegistration = GuestRegistration::where('hotel_id',$hotel_id)
                ->whereRaw("lower(firstname) = '".strtolower($guest_registration["firstname"])."'")
                ->whereRaw("lower(lastname) = '".strtolower($guest_registration["lastname"])."'")
                ->first();

            if($GuestRegistration) {
                $guest_id = $GuestRegistration->guest_id;
                $GuestRegistration->fill($guest_registration);
                $GuestRegistration->save();
            } else {
                $guest_id = GuestRegistration::create($guest_registration)->guest_id;
            }

            $room_no = 0;
            if (!empty($data->room)) {
                $room       = $this->getRoom($hotel_id, $user_id, $data->room);
                $room_no    = (int)$room["room_id"];
            }
            
            switch ($data->reservation_status) {
                case 'Reserved':
                    $resStatus = 0;
                    $status = 1;
                    break;
                case 'In-house': 
                    $resStatus = 1;
                    $status = 1;
                    break;
                case 'Cancelled': 
                    $resStatus = 2;
                    $status = 0;
                    break;
                case 'Checked out': 
                    $resStatus = 3;
                    $status = 0;
                    break;
                default:
                    $resStatus = 4;
                    $status = 0;
                    break;
            }

            $guest_checkin_details = [
                "guest_id"              => $guest_id,
                "hotel_id"              => $hotel_id,
                "room_no"               => $room_no,
                "check_in"              => "$data->start 00:00:00",
                "check_out"             => "$data->end 00:00:00",
                "comment"               => "",
                "status"                => $status,
                "reservation_status"    => $resStatus,
                "reservation_number"    => "$data->reservation_number",
            ];
            
            $GuestCheckinDetails = GuestCheckinDetails::where('hotel_id', $hotel_id)        
                ->where('reservation_number', $guest_checkin_details["reservation_number"])
                ->first();
            if($GuestCheckinDetails) {
                if($GuestCheckinDetails->guest_id != $guest_id) {
                    DB::rollback();
                    $warning = [
                        "Reservation number is registered with another guest"
                    ];
                    return (object)[
                        "status"    => "Success",
                        "message"   => $warning
                    ];
                }
                $GuestCheckinDetails->fill($guest_checkin_details);            
                $GuestCheckinDetails->save();
            } else {
                GuestCheckinDetails::create($guest_checkin_details);                
            }

            

            DB::commit();
            return (object)[
                "status"    => "Success"
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            $warning = $e;
            return (object)[
                "status"    => "Success",
                "message"   => $warning
            ];
        }
        
    }

    private function __updateStay($hotel_id, $user_id, $data, $GuestCheckinDetails) {
        DB::beginTransaction();
        try {
            $this->configTimeZone($hotel_id);
            $now = date('Y-m-d H:i:s');
            $guest_registration = [
                'hotel_id'      => $hotel_id,
                'is_active'     => 1,
                'firstname'     => $data->firstname,
                'lastname'      => $data->lastname,
                'zipcode'       => $data->zipcode,
                'email_address' => $data->email_address,
                'city'          => $data->city,
                'phone_no'      => $data->phone_no,
                'comment'       => '',
                'created_on'    => $now,
                'created_by'    => $user_id,
                'updated_on'    => null,
                'updated_by'    => null,
                'id_device'     => null,
                'dob'           => null,
                'language'      => '',
                'address'       => '',
                'state'         => '',
                'angel_status'  => $this->validateAngelStatus($hotel_id)
            ];

            $GuestRegistration = GuestRegistration::where('hotel_id',$hotel_id)->where('guest_id', $GuestCheckinDetails->guest_id)->first();
            $guest_id = $GuestRegistration->guest_id;
            $GuestRegistration->fill($guest_registration);
            $GuestRegistration->save();

            switch ($data->reservation_status) {
                case 'Reserved':
                    $resStatus = 0;
                    $status = 1;
                    break;
                case 'In-house': 
                    $resStatus = 1;
                    $status = 1;
                    break;
                case 'Cancelled': 
                    $resStatus = 2;
                    $status = 0;
                    break;
                case 'Checked out': 
                    $resStatus = 3;
                    $status = 0;
                    break;
                default:
                    $resStatus = 4;
                    $status = 0;
                    break;
            }
            
            $update = false;

            $room_no = 0;
            //$this->writeLog("TCA", $hotel_id, "-->:".json_encode($data->room));
            if (!empty($data->room)) {
                $room       = $this->getRoom($hotel_id, $user_id, $data->room);
                $room_no    = (int)$room["room_id"];
            }

            //$this->writeLog("TCA", $hotel_id, "room_no -->:".json_encode($room_no));

            $guest_checkin_details = [
                "room_no"               => $room_no,
                "check_in"              => "$data->start 00:00:00",
                "check_out"             => "$data->end 00:00:00",
                "status"                => $status,
                "reservation_status"    => $resStatus,
                "reservation_number"    => "$data->reservation_number",
            ];

            if($resStatus == 2 || $resStatus == 3) {
                if( strtotime("$data->end 00:00:00") > strtotime($now) ) {
                    $update = true;
                    $guest_checkin_details["check_out"] = $now;
                }
            }

            if($GuestCheckinDetails->reservation_status == 1 && $resStatus == 1) {
                if($GuestCheckinDetails->room_no > 0 && $GuestCheckinDetails->room_no != $room_no) {
                    $GuestCheckinDetails->status = 0;
                    $GuestCheckinDetails->check_out = $now;
                    $GuestCheckinDetails->reservation_number = $GuestCheckinDetails->reservation_number."_RM";
                    $GuestCheckinDetails->save();

                    $guest_checkin_details = [
                        "guest_id"              => $guest_id,
                        "hotel_id"              => $hotel_id,
                        "room_no"               => $room_no,
                        "check_in"              => "$data->start 00:00:00",
                        "check_out"             => "$data->end 00:00:00",
                        "comment"               => "",
                        "status"                => $status,
                        "reservation_status"    => $resStatus,
                        "reservation_number"    => "$data->reservation_number",
                    ];
                    RoomMove::create([
                        'guest_id'          => $guest_id,
                        'phone'             => '',
                        'current_room_no'   => $GuestCheckinDetails->room_no,
                        'new_room_no'       => $room_no,
                        'comment'           => '',
                        'hotel_id'          => $hotel_id,
                        'created_by'        => $user_id,
                        'created_on'        => date('Y-m-d H:i:s'),
                        'updated_by'        => 0,
                        'updated_on'        => null,
                    ]);

                    GuestCheckinDetails::create($guest_checkin_details);

                    DB::commit();
                    return (object)[
                        "status"    => "Success"
                    ];
                }
            }

            $GuestCheckinDetails->fill($guest_checkin_details);
            $GuestCheckinDetails->save();

            
            //$this->writeLog("TCA", $hotel_id, "GuestCheckinDetails -->:".json_encode($GuestCheckinDetails));
            DB::commit();
            return (object)[
                "status"    => "Success"
            ];
        } catch (\Exception $e) {
            DB::rollback();
            $warning = $e;
            return (object)[
                "status"    => "Success",
                "message"   => $warning
            ];
        }
    }

}
