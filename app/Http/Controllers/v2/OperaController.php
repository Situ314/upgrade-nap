<?php

namespace App\Http\Controllers\v2;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Spatie\ArrayToXml\ArrayToXml;
use App\Models\HousekeepingCleanings;
use App\Models\GuestCheckinDetails;

use DB;

class OperaController extends Controller
{
    private $hsk_config = [];

    public function index(Request $request)
    {
        
        try {
            $response   = $request->getContent();   
            $xmlString  = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
        } catch (\Throwable $th) {
            \Log::error('TEST INTEGRATION FAILS');
            \Log::error($th);
        }
        
        $data       = $request->data;
        $keys       = array_keys(array_get($data, 'Body', []));
        $reps = null;

        switch ($keys[0]) {
            case 'GuestStatusNotificationRequest':
            case 'GuestStatusNotificationExtRequest':
            case 'RoomStatusUpdateBERequest':
                $resp = $this->reservationService($request);
                break;
            case 'NewProfileRequest':
            case 'UpdateProfileRequest':
                $resp = $this->nameService($request);
                break;
                // MESSAGE
            case 'QueueRoomBERequest':
                $resp = $this->QueueService($request);
                break;
        }
        return response($resp, 200)->header('Content-Type', 'application/soap+xml; charset=utf-8');
    }

    public function nameService(Request $request)
    {
        $hotel_id   = $request->hotel_id;
        $staff_id   = $request->staff_id;
        $data       = $request->data;

        if ($hotel_id == 281) {
            // \Log::info('nameService');
            // \Log::info(json_encode($data));
        }

        $config     = $request->config;
        $type       = 'ProfileRegistration';
        $keys       = array_keys(array_get($data, 'Body', []));
        $unique_id  = array_get($data, 'Body.' . $keys[0] . '.Profile.IDs.UniqueID', '');
        $this->dispatch(new \App\Jobs\Opera($hotel_id, $staff_id, $type, array_get($data, 'Body.' . $keys[0]), $config));
        $action = str_replace('Request', 'Response', $keys[0]);
        $resp   = $this->BuildXMLProfileResponse($action, $unique_id);

        return $resp;
    }

    public function QueueService(Request $request)
    {
        $hotel_id   = $request->hotel_id;
        $data       = $request->data;
        $config     = $request->config;
        $staff_id   = $request->staff_id;

        $type       = 'QueueRoomStatus';
        $keys       = array_keys(array_get($data, 'Body', []));
        $this->dispatch(new \App\Jobs\Opera($hotel_id, $staff_id, $type, array_get($data, 'Body.' . $keys[0]), $config));
        $action = str_replace('Request', 'Response', $keys[0]);
        return $this->BuildXMLQueueResponse($action);
    }

    public function reservationService(Request $request)
    {
        $hotel_id   = $request->hotel_id;
        $staff_id   = $request->staff_id;
        $data       = $request->data;

        // if ($hotel_id == 281) {
        //     \Log::info('reservationService');
        //     \Log::info(json_encode($data));
        // }

        $config     = $request->config;
        $unique_id  = '';
        $keys       = array_keys(array_get($data, 'Body', []));
        $resp       = '';

        switch ($keys[0]) {
            case 'GuestStatusNotificationRequest':
                $type       = 'GuestStatusNotificationRequest';
                $action     = str_replace('Request', 'Response', $keys[0]);
                $message_id = array_get($data, 'Header.MessageID');
                $created    = array_get($data, 'Header.Security.Timestamp.Created');
                $expired    = array_get($data, 'Header.Security.Timestamp.Expires');
                $unique_id  = array_get($data, 'Body.GuestStatusNotificationRequest.GuestStatus.ProfileIDs.UniqueID', '');
                $this->dispatch(new \App\Jobs\Opera($hotel_id, $staff_id, $type, $data, $config));

                $resp = $this->BuildXMLResponse($action, $unique_id, $created, $expired, $message_id);

                break;
            case 'GuestStatusNotificationExtRequest':
                $type       = 'GuestStatusNotificationExtRequest';
                $action     = str_replace('Request', 'Response', $keys[0]);
                $message_id = array_get($data, 'Header.MessageID');
                $created    = array_get($data, 'Header.Security.Timestamp.Created');
                $expired    = array_get($data, 'Header.Security.Timestamp.Expires');
                $unique_id  = array_get($data, 'Body.GuestStatusNotificationExtRequest.GuestStatus.ProfileIDs.UniqueID', '');

                $this->dispatch(new \App\Jobs\Opera($hotel_id, $staff_id, $type, $data, $config));

                $resp = $this->BuildXMLResponse($action, $unique_id, $created, $expired, $message_id);

                break;

            case 'RoomStatusUpdateBERequest':
                $type       = 'RoomStatusUpdateBERequest';
                $action     = str_replace('Request', 'Response', $keys[0]);
                $data       = array_get($data, 'Body.RoomStatusUpdateBERequest');
                $resp       = $this->BuildXMLRoomResponse($action);

                $this->dispatch(new \App\Jobs\Opera($hotel_id, $staff_id, $type, $data, $config));

                break;
        }

        return $resp;
    }

    public function BuildXMLResponse($action, $unique_id, $created, $expired, $message_id)
    {
        $success = [
            "_attributes" => [
                "xmlns:xsi"  => "http://www.w3.org/2001/XMLSchema-instance",
                "xmlns:xsd"  => "http://www.w3.org/2001/XMLSchema",
                "xmlns:soap" => "http://schemas.xmlsoap.org/soap/envelope/",
            ],
            "soap:Body" => [
                $action => [
                    "_attributes" => [
                        "xmlns" => "http://webservices.micros.com/htng/2008B/SingleGuestItinerary/Reservation/Types"
                    ],
                    "Result" => [
                        "_attributes" => [
                            "resultStatusFlag" => "SUCCESS"
                        ],
                        "Text" => [
                            "_attributes" => [
                                "xmlns" => "http://htng.org/PWS/2008B/SingleGuestItinerary/Common/Types"
                            ],
                            "TextElement" => [
                                "_attributes" => [
                                    "language" => "en"
                                ]
                            ]
                        ],
                        "IDs" => [
                            "_attributes" => [
                                "xmlns" => "http://htng.org/PWS/2008B/SingleGuestItinerary/Common/Types"
                            ],
                            "UniqueID" => [
                                "_attributes" => [
                                    "source" => "OPERA"
                                ],
                                "_value" => $unique_id

                            ]
                        ]
                    ]
                ]
            ]
        ];
        return ArrayToXml::convert($success, 'soap:Envelope');
    }

    public function BuildXMLProfileResponse($action, $unique_id)
    {
        $success = [
            "_attributes" => [
                "xmlns:xsi"  => "http://www.w3.org/2001/XMLSchema-instance",
                "xmlns:xsd"  => "http://www.w3.org/2001/XMLSchema",
                "xmlns:soap" => "http://schemas.xmlsoap.org/soap/envelope/",
                "xmlns:a"    => "http://schemas.xmlsoap.org/ws/2004/08/addressing",
                "xmlns:u"    => "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"
            ],
            "soap:Body" => [
                $action => [
                    "_attributes" => [
                        "xmlns" => "http://htng.org/PWS/2008B/SingleGuestItinerary/Name/Types"
                    ],
                    "Result" => [
                        "_attributes" => [
                            "resultStatusFlag" => "SUCCESS",
                            "code" => "OPERA"
                        ],
                        "Text" => [
                            "_attributes" => [
                                "xmlns" => "http://htng.org/PWS/2008B/SingleGuestItinerary/Common/Types"
                            ],
                            "TextElement" => [
                                "_attributes" => [
                                    "language" => "en"
                                ]
                            ]
                        ],
                        "IDs" => [
                            "_attributes" => [
                                "xmlns" => "http://htng.org/PWS/2008B/SingleGuestItinerary/Common/Types"
                            ],
                            "UniqueID" => [
                                "_attributes" => [
                                    "source" => "OPERA"
                                ],
                                "_value" => $unique_id

                            ]
                        ]
                    ]
                ]
            ]
        ];
        return ArrayToXml::convert($success, 'soap:Envelope');
    }


    public function BuildXMLQueueResponse($action)
    {
        $success = [
            "_attributes" => [
                "xmlns:xsi"  => "http://www.w3.org/2001/XMLSchema-instance",
                "xmlns:xsd"  => "http://www.w3.org/2001/XMLSchema",
                "xmlns:soap" => "http://schemas.xmlsoap.org/soap/envelope/",
                "xmlns:a"    => "http://schemas.xmlsoap.org/ws/2004/08/addressing",
                "xmlns:u"    => "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"
            ],
            "soap:Body" => [
                $action => [
                    "_attributes" => [
                        "xmlns" => "http://htng.org/PWS/2008B/SingleGuestItinerary/Name/Types"
                    ],
                    "Result" => [
                        "_attributes" => [
                            "resultStatusFlag" => "SUCCESS",
                            "code" => "OPERA"
                        ],
                        "Text" => [
                            "_attributes" => [
                                "xmlns" => "http://htng.org/PWS/2008B/SingleGuestItinerary/Common/Types"
                            ],
                            "TextElement" => [
                                "_attributes" => [
                                    "language" => "en"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        return ArrayToXml::convert($success, 'soap:Envelope');
    }

    public function BuildXMLRoomResponse($action)
    {
        $success = [
            "_attributes" => [
                "xmlns:xsi" => "http://www.w3.org/2001/XMLSchema-instance",
                "xmlns:xsd" => "http://www.w3.org/2001/XMLSchema",
                "xmlns:soap" => "http://schemas.xmlsoap.org/soap/envelope/"
            ],
            "soap:Body" => [
                $action => [
                    "_attributes" => [
                        "xmlns" => "http://webservices.micros.com/htng/2008B/SingleGuestItinerary/Housekeeping/Types"
                    ],
                    "Result" => [
                        "_attributes" => [
                            "resultStatusFlag" => "SUCCESS",
                        ],
                        "Text" => [
                            "_attributes" => [
                                "xmlns" => "http://htng.org/PWS/2008B/SingleGuestItinerary/Common/Types"
                            ],
                            "TextElement" => [
                                "_attributes" => [
                                    "language" => "en"
                                ]
                            ]
                        ],
                        "IDs" => [
                            "_attributes" => [
                                "xmlns" => "http://htng.org/PWS/2008B/SingleGuestItinerary/Common/Types"
                            ]
                        ]
                    ]
                ]
            ]
        ];
        return ArrayToXml::convert($success, 'soap:Envelope');
    }

    public function GetOracleRoomSync($config, $pms_hotel_id)
    {
        $timestamp  = date('Y-m-d\TH:i:s\Z');
        $username   = $config['username_send'];
        $password   = $config['password_send'];
        $url        = $config['url_send'];
        $from       = $config['from_send'];

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:wsa="http://schemas.xmlsoap.org/ws/2004/08/addressing" xmlns:wsse="http://docs.oasisopen.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasisopen.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
            <soap:Header>
                <wsa:Action>http://webservices.micros.com/htng/2008B/SingleGuestItinerary#FetchRoomStatus
                    </wsa:Action>
                <wsa:From>
                    <wsa:Address>urn:' . $from . '</wsa:Address>
                </wsa:From>
                <wsa:MessageID>urn:uuid:09a2b665-41d0-4654-b49d-86e7d437e371</wsa:MessageID>
                <wsa:ReplyTo>
                    <wsa:Address>http://schemas.xmlsoap.org/ws/2004/08/addressing/role/anonymous</wsa:Address>
                </wsa:ReplyTo>
                <wsa:To>http://www.micros.com/HTNGActivity/</wsa:To>
                <wsse:Security>
                    <wsu:Timestamp wsu:Id="Timestamp-fbd379f8-e4b5-4219-a498-85cfefb9afa5">
                        <wsu:Created>' . $timestamp . '</wsu:Created>
                        <wsu:Expires>' . date('Y-m-d\TH:i:s\Z', strtotime($timestamp . ' +5 minutes')) . '</wsu:Expires>
                    </wsu:Timestamp>
                    <wsse:UsernameToken xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis200401-wss-wssecurity-utility-1.0.xsd" wsu:Id="SecurityToken-35e70b4f-57e8-4ebf-b9e2-eaf8269587f2">
                        <wsse:Username>' . $username . '</wsse:Username>
                        <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis200401-wss-username-token-profile-1.0#PasswordText">' . $password . '</wsse:Password>
                        <wsu:Created>' . $timestamp . '</wsu:Created>
                    </wsse:UsernameToken>
                </wsse:Security>
            </soap:Header>
            <soap:Body>
                <FetchRoomStatusRequest xmlns="http://webservices.micros.com/htng/2008B/SingleGuestItinerary/Housekeeping/Types">
                    <ResortId>' . $pms_hotel_id . '</ResortId>
                </FetchRoomStatusRequest>
            </soap:Body>
        </soap:Envelope>
        ';

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL             => $url,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_ENCODING        => "",
            CURLOPT_MAXREDIRS       => 10,
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST   => "POST",
            CURLOPT_POSTFIELDS      => $xml,
            CURLOPT_HTTPHEADER      => ["Content-Type: text/xml; charset=utf-8; action=http://webservices.micros.com/htng/2008B/SingleGuestItinerary#FetchRoomStatus"],
        ));
        $response   = curl_exec($curl);
        $err        = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return $err;
        } else {
            $xmlString  = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
            $xml        = simplexml_load_string($xmlString);
            $str_json   = json_encode($xml);
            $json       = json_decode($str_json, true);

            return array_get($json, 'Body.FetchRoomStatusResponse');
        }
    }

    public function getReservationRoom($room_id, $config, $pms_hotel_id)
    {
        $timestamp  = date('Y-m-d\TH:i:s\Z');
        $username   = $config['username_send'];
        $password   = $config['password_send'];
        $url        = $config['url_sync'];
        $from       = $config['from_send'];

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:wsa="http://schemas.xmlsoap.org/ws/2004/08/addressing" xmlns:wsse="http://docs.oasisopen.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasisopen.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
            <soap:Header>
                <wsa:Action>http://webservices.micros.com/htng/2008B/SingleGuestItinerary#ReservationLookup
                    </wsa:Action>
                <wsa:From>
                    <wsa:Address>urn:' . $from . '</wsa:Address>
                </wsa:From>
                <wsa:MessageID>urn:uuid:09a2b665-41d0-4654-b49d-86e7d437e371</wsa:MessageID>
                <wsa:ReplyTo>
                    <wsa:Address>http://schemas.xmlsoap.org/ws/2004/08/addressing/role/anonymous</wsa:Address>
                </wsa:ReplyTo>
                <wsa:To>http://www.micros.com/HTNGActivity/</wsa:To>
                <wsse:Security>
                    <wsu:Timestamp wsu:Id="Timestamp-fbd379f8-e4b5-4219-a498-85cfefb9afa5">
                        <wsu:Created>' . $timestamp . '</wsu:Created>
                        <wsu:Expires>' . date('Y-m-d\TH:i:s\Z', strtotime($timestamp . ' +5 minutes')) . '</wsu:Expires>
                    </wsu:Timestamp>
                    <wsse:UsernameToken xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis200401-wss-wssecurity-utility-1.0.xsd" wsu:Id="SecurityToken-35e70b4f-57e8-4ebf-b9e2-eaf8269587f2">
                        <wsse:Username>' . $username . '</wsse:Username>
                        <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis200401-wss-username-token-profile-1.0#PasswordText">' . $password . '</wsse:Password>
                        <wsu:Created>' . $timestamp . '</wsu:Created>
                    </wsse:UsernameToken>
                </wsse:Security>
            </soap:Header>
            <soap:Body>
                <ReservationLookupRequest xmlns="http://htng.org/PWS/2008B/SingleGuestItinerary/Reservation/Types" xmlns:a="http://htng.org/PWS/2008B/SingleGuestItinerary/Activity/Types" xmlns:c="http://htng.org/PWS/2008B/SingleGuestItinerary/Common/Types">
                    <ReservationLookupData reservationStatus="CHECKED_IN">
                        <RoomNumber>' . $room_id . '</RoomNumber>
                        <ResortId>' . $pms_hotel_id . '</ResortId>
                    </ReservationLookupData>
                </ReservationLookupRequest>
            </soap:Body>
        </soap:Envelope>
        ';
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_HTTPHEADER     => array(
                "Content-Type: text/xml; charset=utf-8; action=http://webservices.micros.com/htng/2008B/SingleGuestItinerary#ReservationLookup",
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return null;
        } else {
            $xmlString  = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
            $xml        = simplexml_load_string($xmlString);
            $str_json   = json_encode($xml);
            $json       = json_decode($str_json, true);
            return array_get($json, 'Body.ReservationLookupResponse.ReservationLookups.ReservationLookup');
        }
    }

    public function SyncOracleHSKOne($hotel_id, $room_id = null)
    {
        $IntegrationsActive = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)
            ->where('int_id', 5)
            ->where('state', 1)
            ->first();
        $location = null;

        if ($room_id) {
            $HotelRoom = \App\Models\HotelRoom::where('hotel_id', $hotel_id)->where('room_id', $room_id)->first();
            $location = $HotelRoom->location;
            $this->dispatch((new \App\Jobs\Opera($hotel_id, $IntegrationsActive->created_by, 'SyncOracleHSK', [], $IntegrationsActive->config, $location)));
        } else {
            $HotelRoom = \App\Models\HotelRoom::where('hotel_id', $hotel_id)->get();
            foreach ($HotelRoom as  $room) {
                $location = $room->location;
                if ($location > 3000) {
                    break;
                }
                $this->dispatch((new \App\Jobs\Opera($hotel_id, $IntegrationsActive->created_by, 'SyncOracleHSK', [], $IntegrationsActive->config, $location)));
            }
        }

        return response()->json([
            'Sync' => true
        ], 200);
    }

    public function SyncOracleHSK($hotel_id, $room_id = null, $skip = 0)
    {
        $IntegrationsActive = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)
            ->where('int_id', 5)
            ->where('state', 1)
            ->first();

        $location = null;
        echo('start sync Oracle');
        
        if ($room_id) {
            $HotelRoom = \App\Models\HotelRoom::where('hotel_id', $hotel_id)->where('room_id', $room_id)->first();
            #$location = (strlen($HotelRoom->location) > 3 && ($hotel_id == 296 || $hotel_id == 238 || $hotel_id == 289  || $hotel_id == 314 || $hotel_id == 303)) ? $HotelRoom->location : "0$HotelRoom->location";
            #$location = $hotel_id== 314 ||  $hotel_id== 303 ||  $hotel_id== 266 ? $HotelRoom->location : $location;

            if($hotel_id == 314){
                $location = $HotelRoom->location;
            }else if($hotel_id == 207){
                $location = str_pad($HotelRoom->location, 3, "0", STR_PAD_LEFT);
            }else{
                $location = str_pad($HotelRoom->location, 4, "0", STR_PAD_LEFT);
            }

            $this->dispatch((new \App\Jobs\Opera($hotel_id, $IntegrationsActive->created_by, 'SyncOracleHSK', [], $IntegrationsActive->config, $location)));
            // $this->check_out_reserve($hotel_id);
        } else {
            $HotelRoom = \App\Models\HotelRoom::where('hotel_id', $hotel_id)->where('is_common_area', 0)->where('active', 1)->orderBy('location', 'ASC')->get();
            foreach ($HotelRoom as $key=>$room) {
		if($skip != 0 && $skip >= $key ){
	            continue;
		}		
                $location = (strlen($room->location) > 3 && ($hotel_id == 296 || $hotel_id == 238 || $hotel_id == 289  || $hotel_id == 314 || $hotel_id == 303)) ? $room->location : "0$room->location";
                $location = $hotel_id== 314 ||  $hotel_id== 303 ||  $hotel_id== 266 ? $room->location : $location;
                $this->dispatch((new \App\Jobs\Opera($hotel_id, $IntegrationsActive->created_by, 'SyncOracleHSK', [], $IntegrationsActive->config, $location)));
		
                if($location > 8000 and $hotel_id == 238){
                    break;
                }else{
                    if ($location > 4000 && $hotel_id != 238) {
                        break;
                    }
                }
            }
            // $this->check_out_reserve($hotel_id);
        }

        return response()->json([
            'Sync' => true
        ], 200);
    }


    public function syncProfileData($hotel_id, $room_id = null)
    {
        $IntegrationsActive = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)
            ->where('int_id', 5)
            ->where('state', 1)
            ->first();
        $this->dispatch((new \App\Jobs\Opera($hotel_id, $IntegrationsActive->created_by, 'syncProfileData', [], $IntegrationsActive->config, null)));

        return response()->json([
            'Sync' => true
        ], 200);
    }

    public function SyncOracleHSKLite($hotel_id, $room_id = null)
    {
        $IntegrationsActive = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)
            ->where('int_id', 5)
            ->where('state', 1)
            ->first();

        $location = null;
        $this->hsk_config = $IntegrationsActive->config['housekeeping'];
        if ($room_id) {
            $HotelRoom = \App\Models\HotelRoom::where('hotel_id', $hotel_id)->where('room_id', $room_id)->first();
            #$location = (strlen($HotelRoom->location) > 3 && $hotel_id == 296) ? $HotelRoom->location : "0$HotelRoom->location";
            #$location = $HotelRoom->location;

            $location = str_pad($HotelRoom->location, 4, "0", STR_PAD_LEFT);

        
            $this->dispatch((new \App\Jobs\Opera($hotel_id, $IntegrationsActive->created_by, 'SyncOracleHSK', [], $IntegrationsActive->config, $location)));
        } else {
            $data = [];
            $HotelRoom = \App\Models\HotelRoom::where('hotel_id', $hotel_id)->where('is_common_area', 0)->where('active', 1)->orderBy('location', 'ASC')->get();
            $rooms_fetch = $this->formatFetchRoomStatus($hotel_id);
            // return response()->json($rooms_fetch);
            foreach ($HotelRoom as  $room) {
                $location = (strlen($room->location) > 3 && ($hotel_id == 296 || $hotel_id == 238 || $hotel_id == 289 || $hotel_id == 314)) ? $room->location : "0$room->location";
                if (!$this->validateHskCleanning($hotel_id,array_get($rooms_fetch,$location,0),$room->room_id)) {
                    // dd($hotel_id,array_get($rooms_fetch,$location,0),$room->room_id, $location);
                    // $this->dispatch((new \App\Jobs\Opera($hotel_id, $IntegrationsActive->created_by, 'SyncOracleHSK', [], $IntegrationsActive->config, $location)));
                    $data[$room->location] = [array_get($rooms_fetch,$location,0), $room->room_id];
                }
            if($location > 8000 and $hotel_id == 238){
                break;
            }else{
                if ($location > 4000 && $hotel_id != 238) {
                    break;
                }
            }
            }
        }

        return response()->json([
            'Sync' => true,
            'data' => $data
        ], 200);
    }
    public function verify($hotel_id, Request $request){
        dd($request);
    }
    public function fetch($hotel_id)
    {
        $IntegrationsActive = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)
            ->where('int_id', 5)
            ->where('state', 1)
            ->first();

        $timestamp = date('Y-m-d\TH:i:s\Z');
        $username = $IntegrationsActive->config['username_send'];
        $password = $IntegrationsActive->config['password_send'];
        $url      = $IntegrationsActive->config['url_send'];
        $from     = $IntegrationsActive->config['from_send'];

        $pms_hotel_id = $IntegrationsActive->pms_hotel_id;
        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" 
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
            xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
            xmlns:wsa="http://schemas.xmlsoap.org/ws/2004/08/addressing" 
            xmlns:wsse="http://docs.oasisopen.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" 
            xmlns:wsu="http://docs.oasisopen.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
            <soap:Header>
                <wsa:Action>http://webservices.micros.com/htng/2008B/SingleGuestItinerary#FetchRoomStatus
                </wsa:Action>
                <wsa:From>
                    <wsa:Address>urn:' . $from . '</wsa:Address>
                </wsa:From>
                <wsa:MessageID>urn:uuid:09a2b665-41d0-4654-b49d-86e7d437e371</wsa:MessageID>
                <wsa:ReplyTo>
                    <wsa:Address>http://schemas.xmlsoap.org/ws/2004/08/addressing/role/anonymous</wsa:Address>
                </wsa:ReplyTo>
                <wsa:To>http://www.micros.com/HTNGActivity/</wsa:To>
                <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
                    <wsu:Timestamp wsu:Id="TS-1DB19FB15198FE10A2159249621088842">
                        <wsu:Created>' . $timestamp . '</wsu:Created>
                        <wsu:Expires>' . date('Y-m-d\TH:i:s\Z', strtotime($timestamp . ' +5 minutes')) . '</wsu:Expires>
                    </wsu:Timestamp>
                    <wsse:UsernameToken wsu:Id="UsernameToken-1DB19FB15198FE10A2159249621088841">
                        <wsse:Username>' . $username . '</wsse:Username>
                        <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">' . $password . '</wsse:Password>
                        <wsu:Created>' . $timestamp . '</wsu:Created>
                    </wsse:UsernameToken>
                </wsse:Security>
            </soap:Header>
            <soap:Body>
                <FetchRoomStatusRequest xmlns="http://webservices.micros.com/htng/2008B/SingleGuestItinerary/Housekeeping/Types">
                    <ResortId>' . $pms_hotel_id . '</ResortId>
                </FetchRoomStatusRequest>
            </soap:Body>
        </soap:Envelope>';
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL             => $url,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_ENCODING        => "",
            CURLOPT_MAXREDIRS       => 10,
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST   => "POST",
            CURLOPT_POSTFIELDS      => $xml,
            // CURLOPT_SSL_VERIFYPEER  => 0,
            // CURLOPT_SSL_VERIFYHOST  => 0,
            CURLOPT_HTTPHEADER      => [
                "SOAPAction: http://webservices.micros.com/htng/2008B/SingleGuestItinerary#FetchRoomStatus",
                "Content-Type: text/xml; charset=utf-8",
            ],
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return $err;
        } else {
            $xmlString  = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
            $xml        = simplexml_load_string($xmlString);
            $str_json   = json_encode($xml);
            $json       = json_decode($str_json, true);
            return array_get($json, 'Body.FetchRoomStatusResponse');
        }
    }

    public function check_out_reserve($hotel_id)
    {
        $reservation = GuestCheckinDetails::where('hotel_id', $hotel_id)->where('status',1)->where('reservation_status',0)
            ->whereDate('check_out', '<' , date('Y-m-d'))->get();
        foreach ($reservation as $r){
            $r->reservation_status =  3;
            $r->status = 0;
            $r->save();
            //\Log::info($r->sno);
        }
    }

    public function profile($hotel_id)
    {
        $IntegrationsActive = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)
            ->where('int_id', 5)
            ->where('state', 1)
            ->first();

            $timestamp = date('Y-m-d\TH:i:s\Z');
        $username   = $IntegrationsActive->config['username_send'];
        $password   = $IntegrationsActive->config['password_send'];
        $url        = $IntegrationsActive->config['url_sync'];
        $from       = $IntegrationsActive->config['from_send'];
        $pms_hotel_id = $IntegrationsActive->pms_hotel_id;

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" 
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
            xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
            xmlns:wsa="http://schemas.xmlsoap.org/ws/2004/08/addressing" 
            xmlns:wsse="http://docs.oasisopen.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" 
            xmlns:wsu="http://docs.oasisopen.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
            <soap:Header>
                <wsa:Action>http://webservices.micros.com/htng/2008B/SingleGuestItinerary#ReservationLookup
                </wsa:Action>
                <wsa:From>
                    <wsa:Address>urn:' . $from . '</wsa:Address>
                </wsa:From>
                <wsa:MessageID>urn:uuid:09a2b665-41d0-4654-b49d-86e7d437e371</wsa:MessageID>
                <wsa:ReplyTo>
                    <wsa:Address>http://schemas.xmlsoap.org/ws/2004/08/addressing/role/anonymous</wsa:Address>
                </wsa:ReplyTo>
                <wsa:To>http://www.micros.com/HTNGActivity/</wsa:To>
                <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
                    <wsu:Timestamp wsu:Id="TS-1DB19FB15198FE10A2159249621088842">
                        <wsu:Created>' . $timestamp . '</wsu:Created>
                        <wsu:Expires>' . date('Y-m-d\TH:i:s\Z', strtotime($timestamp . ' +5 minutes')) . '</wsu:Expires>
                    </wsu:Timestamp>
                    <wsse:UsernameToken wsu:Id="UsernameToken-1DB19FB15198FE10A2159249621088841">
                        <wsse:Username>' . $username . '</wsse:Username>
                        <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">' . $password . '</wsse:Password>
                        <wsu:Created>' . $timestamp . '</wsu:Created>
                    </wsse:UsernameToken>
                </wsse:Security>
            </soap:Header>
            <soap:Body>
                <ReservationLookupRequest xmlns="http://htng.org/PWS/2008B/SingleGuestItinerary/Reservation/Types" xmlns:a="http://htng.org/PWS/2008B/SingleGuestItinerary/Activity/Types" xmlns:c="http://htng.org/PWS/2008B/SingleGuestItinerary/Common/Types">
                    <ReservationLookupData reservationStatus="RESERVED">
                        <DateRange>
                            <Start xmlns="http://htng.org/PWS/2008B/SingleGuestItinerary/Activity/Types">2021-10-14T00:00:00</Start>
                            <End xmlns="http://htng.org/PWS/2008B/SingleGuestItinerary/Activity/Types">2021-10-15T00:00:00</End>
                        </DateRange>
                        <ResortId>' . $pms_hotel_id . '</ResortId>
                    </ReservationLookupData>
                </ReservationLookupRequest>
            </soap:Body>
        </soap:Envelope>
        ';
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: text/xml;charset=UTF-8",
                "Action: http://webservices.micros.com/htng/2008B/SingleGuestItinerary#ReservationLookup",
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return $err;
        } else {
            return $response;
            $xmlString  = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
            $xml        = simplexml_load_string($xmlString);
            $str_json   = json_encode($xml);
            $json       = json_decode($str_json, true);
            return array_get($json, 'Body');
        }
    }

    public function formatFetchRoomStatus($hotel_id)
    {

        $data = $this->fetch($hotel_id);
        $data = array_get($data, 'FetchRoomStatus', []);
        $rooms = [];
        foreach ($data as $key => $value) {
            if((array_get($value, 'RoomNumber') >= 4000 && $hotel_id != 238) || (array_get($value, 'RoomNumber') >= 8000 && $hotel_id == 238) ){
                break;
            }else{
                
                if(
                    array_get($value, 'RoomStatus') != 'OutOfOrder' &&
                    array_get($value, 'RoomStatus') != 'Out of Order' &&
                    array_get($value, 'RoomStatus') != 'Out of Service' &&
                    array_get($value, 'RoomStatus') != 'OutOfService'
                ){
                    $rooms[array_get($value, 'RoomNumber')] = (array_get($value, 'HouseKeepingStatus') == 'OCC' && array_get($value, 'RoomStatus') == 'Inspected') ? 'Clean' : array_get($value, 'RoomStatus');
                    $rooms[array_get($value, 'RoomNumber')] = $this->hsk_config[$rooms[array_get($value, 'RoomNumber')]]["codes"][0]["hk_status"];
                }
            }
        }
        return $rooms;

    }

    public function validateHskCleanning($hotel_id,$hsk_status,$room_id){ 
        if($hsk_status != 0){
            $hsk_cleanning = HousekeepingCleanings::where('hotel_id', $hotel_id)
            ->where('room_id', $room_id)
            ->orderBy('assigned_date', 'DESC')->orderBy('cleaning_id', 'DESC')->first();
            if ($hsk_cleanning) {
                return $hsk_cleanning->hk_status == $hsk_status;
            }else{
                return true;
            }
        }else{
            return true;
        }
    }

    #Prueba
    public function testingoperaSync($hotel_id, $room_id = null, $skip = 0)
    {
        $IntegrationsActive = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)
            ->where('int_id', 5)
            ->where('state', 1)
            ->first();

        $location = null;
        echo('start sync Oracle');
        
        if ($room_id) {
            $HotelRoom = \App\Models\HotelRoom::where('hotel_id', $hotel_id)->where('room_id', $room_id)->first();

            $location = str_pad($HotelRoom->location, 4, "0", STR_PAD_LEFT);


            #$location = (strlen($HotelRoom->location) > 3 && ($hotel_id == 296 || $hotel_id == 238 || $hotel_id == 289  || $hotel_id == 314 || $hotel_id == 303)) ? $HotelRoom->location : "0$HotelRoom->location";
            #$location = $hotel_id== 314 ||  $hotel_id== 303 ||  $hotel_id== 266 ? $HotelRoom->location : $location;

            echo('Location::::');
            echo $location;

            //$this->dispatch((new \App\Jobs\Opera($hotel_id, $IntegrationsActive->created_by, 'SyncOracleHSK', [], $IntegrationsActive->config, $location)));

            // $this->check_out_reserve($hotel_id);
        } else {
            $HotelRoom = \App\Models\HotelRoom::where('hotel_id', $hotel_id)->where('is_common_area', 0)->where('active', 1)->orderBy('location', 'ASC')->get();
            foreach ($HotelRoom as $key=>$room) {
		if($skip != 0 && $skip >= $key ){
	            continue;
		}		
                $location = (strlen($room->location) > 3 && ($hotel_id == 296 || $hotel_id == 238 || $hotel_id == 289  || $hotel_id == 314 || $hotel_id == 303)) ? $room->location : "0$room->location";
                $location = $hotel_id== 314 ||  $hotel_id== 303 ||  $hotel_id== 266 ? $room->location : $location;
                $this->dispatch((new \App\Jobs\Opera($hotel_id, $IntegrationsActive->created_by, 'SyncOracleHSK', [], $IntegrationsActive->config, $location)));
		
                if($location > 8000 and $hotel_id == 238){
                    break;
                }else{
                    if ($location > 4000 && $hotel_id != 238) {
                        break;
                    }
                }
            }
            // $this->check_out_reserve($hotel_id);
        }

        return response()->json([
            'Sync' => true
        ], 200);
    }
}
