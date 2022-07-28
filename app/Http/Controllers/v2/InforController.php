<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\GuestCheckinDetails;
use App\Models\GuestRegistration;
use App\Models\IntegrationsGuestInformation;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Spatie\ArrayToXml\ArrayToXml;

class InforController extends Controller
{
    private $hotel_id;

    private $staff_id;

    private $INTERNAL_SERVER;

    private $BAD_REQUEST;

    private $RECORD_NOT_FOUND;

    //
    private $TimeStamp;

    private $UniqueID_Type;

    private $UniqueID_ID;

    private $MessageId;

    private $HotelReservationID;

    private $Lockedroom;

    private $HotelHousekeepingConfig;

    private $route_action;

    private $credentials;

    private $url_send;

    public function __construct()
    {
        /** Estas variables globales indican el tipo de error al cual se debe ingresar en el metodo ErrorXML */
        $this->RECORD_NOT_FOUND = 0;
        $this->BAD_REQUEST = 1;
        $this->INTERNAL_SERVER = 2;
    }

    public function index(Request $request, $hotel_id)
    {
        // \Log::info($request->getContent());
        try {
            $this->staff_id = $request->staff_id;
            $this->hotel_id = $hotel_id;
            $config = $request->config;
            $this->HotelHousekeepingConfig = $config['housekeeping'];
            $this->credentials = $config['auth_send_to_infor'];
            $this->url_send = $config['url_send'];
            // \Log::info('Infor XML');
            // Configurar timezo del hotel
            $this->configTimeZone($hotel_id);
            // Capturar y limpiar los datos del XML
            $response = $request->getContent();
            $xmlString = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
            $xmlString = preg_replace('/([a-zA-Z0-9_-]+):([a-zA-Z0-9_-]+)/', '$1$2', $xmlString);
            $xml = simplexml_load_string($xmlString);
            // Pasar de XML String a Array
            $str_json = json_encode($xml);
            $arrayData = json_decode($str_json, true);
            // Primeros nodos del XML necesarios para iniciar a procesar lainformacion

            $route_action = '';
            $xml_response = '';
            $is_found = false;

            /** Se verifica el tipo de mensaje que se esta recibiendo
             *  Si es un mensaje de tipo "OTA_HotelResNotifRQ" se enviara a su respectivo enrutador.
             *  Si es un mensaje de tipo "HTNG_metodo" se realizara un enrutamiento especial para cada metodo que se recibe de HTNG
             */
            if (Arr::has($arrayData, 'Body.HTNG_HotelRoomMoveNotifRQ')) {
                $route_action = 'HTNG_HotelRoomMoveNotifRQ';
                $this->route_action = 'HTNG_HotelRoomMoveNotifRS';
                $is_found = true;
            }

            if (Arr::has($arrayData, 'Body.OTA_HotelResNotifRQ')) {
                $route_action = 'OTA_HotelResNotifRQ';
                $is_found = true;
            }
            if (Arr::has($arrayData, 'Body.HTNG_HotelRoomStatusUpdateNotifRQ')) {
                $route_action = 'HTNG_HotelRoomStatusUpdateNotifRQ';
                $this->route_action = 'HTNG_HotelRoomStatusUpdateNotifRS';
                $is_found = true;
            }

            if (Arr::has($arrayData, 'Body.HTNG_HotelCheckInNotifRQ')) {
                $route_action = 'HTNG_HotelCheckInNotifRQ';
                $this->route_action = 'HTNG_HotelCheckInNotifRS';
                $is_found = true;
            }

            if (Arr::has($arrayData, 'Body.HTNG_HotelStayUpdateNotifRQ')) {
                $route_action = 'HTNG_HotelStayUpdateNotifRQ';
                $this->route_action = 'HTNG_HotelStayUpdateNotifRS';
                $is_found = true;
            }

            if (Arr::has($arrayData, 'Body.HTNG_HotelCheckOutNotifRQ')) {
                $route_action = 'HTNG_HotelCheckOutNotifRQ';
                $this->route_action = 'HTNG_HotelCheckOutNotifRS';
                $is_found = true;
            }

            /** Si el tipo de mensaje no se identifica en los metodos anteriores este devolvera aun asi un codigo 200 como respuesta
             *       esto con el fin de que el PMS no reciba algun tipo de error */
            if ($is_found == false && Arr::has($arrayData, 'Header.Action') && substr(Arr::get($arrayData, 'Header.Action'), 0, 4) == 'HTNG') {
                $route_action = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', Arr::get($arrayData, 'Header.Action'));
                $this->TimeStamp = Arr::get($arrayData, 'Body.'.$route_action.'.@attributes.TimeStamp');
                $this->route_action = str_replace('NotifRQ', 'NotifRS', $route_action);
                $xml_resp = $this->SuccessChangeHSK();
                $this->CurlHTNG($xml_resp);

                return response($xml_resp, 200)->header('Content-Type', 'application/soap+xml; charset=utf-8');
            }

            /** Se envía el tipo de mensaje y los datos al metodo RouteControl */
            if ($route_action != '') {
                $xml_response = $this->RouteControl($route_action, $arrayData);
            } else {
                $xml_response = $this->ErrorXML($this->BAD_REQUEST);
            }
            date_default_timezone_set('UTC');
            $content = $this->route_action == null ? 'application/xml' : 'application/soap+xml; charset=utf-8';

            return response($xml_response, 200)->header('Content-Type', $content);
        } catch (\Exception $e) {
            \Log::error('Error in inforController > index');
            \log::error("$e");
        }
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @param  object (json) $arrayData contiene los datos enviados en el mensaje xml
     * @return string (xml) retorna como respuesta un xml que puede ser un error o un mensaje de success
     *
     * Se ejecuta este metodo unicamente cuando se envia un mensaje de tipo "OTA_HotelResNotifRQ" y se enruta con respecto a la accion y sub-accion que senvian
     *      en el cuerpo del xml
     */
    public function HotelReservation($arrayData)
    {
        try {
            $validate = [
                'Body.OTA_HotelResNotifRQ.@attributes.ResStatus',
                'Body.OTA_HotelResNotifRQ.@attributes.TimeStamp',
                'Body.OTA_HotelResNotifRQ.HotelReservations.HotelReservation.@attributes.ResStatus',
                'Body.OTA_HotelResNotifRQ.HotelReservations.HotelReservation.UniqueID.@attributes.Type',
                'Body.OTA_HotelResNotifRQ.HotelReservations.HotelReservation.UniqueID.@attributes.ID',
                'Body.OTA_HotelResNotifRQ.HotelReservations.HotelReservation.ResGlobalInfo.HotelReservationIDs.HotelReservationID',
            ];

            if (Arr::has($arrayData, $validate)) {
                $action = Arr::get($arrayData, 'Body.OTA_HotelResNotifRQ.@attributes.ResStatus');
                $data = Arr::get($arrayData, 'Body.OTA_HotelResNotifRQ.HotelReservations.HotelReservation');
                $subAction = Arr::get($arrayData, 'Body.OTA_HotelResNotifRQ.HotelReservations.HotelReservation.@attributes.ResStatus');

                $this->TimeStamp = Arr::get($arrayData, 'Body.OTA_HotelResNotifRQ.@attributes.TimeStamp');
                $this->UniqueID_Type = Arr::get($arrayData, 'Body.OTA_HotelResNotifRQ.HotelReservations.HotelReservation.UniqueID.@attributes.Type');
                $this->UniqueID_ID = Arr::get($arrayData, 'Body.OTA_HotelResNotifRQ.HotelReservations.HotelReservation.UniqueID.@attributes.ID');
                $this->MessageId = Arr::get($arrayData, 'Header.MessageID', '');
                $this->HotelReservationID = Arr::get($arrayData, 'Body.OTA_HotelResNotifRQ.HotelReservations.HotelReservation.ResGlobalInfo.HotelReservationIDs.HotelReservationID');
                $this->Lockedroom = Arr::get($arrayData, 'Body.OTA_HotelResNotifRQ.HotelReservations.HotelReservation.@attributes.RoomNumberLockedIndicator', false);
                if (! is_array($this->HotelReservationID)) {
                    $this->HotelReservationID = [$this->HotelReservationID];
                }

                return $this->route($action, $subAction, $data);
            } else {
                return $this->ErrorXML($this->BAD_REQUEST);
            }
        } catch (\Exception $e) {
            \Log::error($e);

            return $this->ErrorXML($this->INTERNAL_SERVER);
        }
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @param  object (json) $arrayData contiene los datos enviados en el mensaje xml
     * @return string (xml) retorna como respuesta un xml que puede ser un error o un mensaje de success
     *
     *  Este metodo se ejecuta unicamente cuando se recibe un mensaje de tipo "HTNG_HotelRoomMoveNotifRQ"
     *  Ademas se encarga de validar la existencia de los datos recibidos y que son necesarios para hacer un cambio de cuarto a una reservación
     *       y si esto se cumple se envía los datos al metodo RoomMove()
     */
    public function HotelRoom($arrayData)
    {
        try {
            $is_HotelRoom = [
                'Body.HTNG_HotelRoomMoveNotifRQ',
                'Body.HTNG_HotelRoomMoveNotifRQ.DestinationRoomInformation.HotelReservations.HotelReservation.@attributes.ResStatus',
                'Body.HTNG_HotelRoomMoveNotifRQ.@attributes.TimeStamp',
                'Body.HTNG_HotelRoomMoveNotifRQ.DestinationRoomInformation',
                'Body.HTNG_HotelRoomMoveNotifRQ.SourceRoomInformation',
                'Body.HTNG_HotelRoomMoveNotifRQ.DestinationRoomInformation.HotelReservations.HotelReservation.UniqueID.@attributes.ID',
            ];
            if (Arr::has($arrayData, $is_HotelRoom)) {
                $NewRoom = Arr::get($arrayData, 'Body.HTNG_HotelRoomMoveNotifRQ.DestinationRoomInformation.Room');
                $HotelReservation = Arr::get(
                    $arrayData,
                    'Body.HTNG_HotelRoomMoveNotifRQ.DestinationRoomInformation.HotelReservations.HotelReservation'
                );
                $this->TimeStamp = Arr::get($arrayData, 'Body.HTNG_HotelRoomMoveNotifRQ.@attributes.TimeStamp');
                $this->UniqueID_Type = Arr::get($HotelReservation, 'UniqueID.@attributes.Type');
                $this->UniqueID_ID = Arr::get($HotelReservation, 'UniqueID.@attributes.ID');
                $this->MessageId = Arr::get($arrayData, 'Header.MessageID', '');
                $this->HotelReservationID = Arr::get($arrayData, 'Body.OTA_HotelResNotifRQ.HotelReservations.HotelReservation.ResGlobalInfo.HotelReservationIDs.HotelReservationID', []);
                $this->Lockedroom = Arr::get($HotelReservation, '@attributes.RoomNumberLockedIndicator', false);

                return $this->RoomMove($NewRoom);
            }

            return $this->ErrorXML($this->BAD_REQUEST);
        } catch (\Exception $e) {
            \Log::error($e);

            return $this->ErrorXML($this->INTERNAL_SERVER);
        }
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @param  object (json) $arrayData contiene los datos enviados en el mensaje xml
     * @return string (xml) retorna como respuesta un xml que puede ser un error o un mensaje de success
     *
     * Se ejecuta este metodo unicamente cuando se envia un mensaje de tipo "HTNG_HotelRoomStatusUpdateNotifRQ"
     * Se encarga de validar los datos necesarios para realizar una actualización de estado de un cuarto. si la validación es correcta se busca el cuarto a actualizar
     *      y se genera un array con los datos del cuarto y el nuevo estado de ese cuarto
     * Al final se ejecuta el metodo ChangeHousekeepingStatus() el cual se encargará de actualizar el estado del cuarto
     */
    public function HotelHousekeeping($arrayData)
    {
        try {
            $validate = [
                'Body.HTNG_HotelRoomStatusUpdateNotifRQ.@attributes.TimeStamp',
                'Body.HTNG_HotelRoomStatusUpdateNotifRQ.Room',
            ];
            $this->TimeStamp = Arr::get($arrayData, 'Body.HTNG_HotelRoomStatusUpdateNotifRQ.@attributes.TimeStamp');
            if (Arr::has($arrayData, $validate)) {
                $room_data = Arr::get($arrayData, 'Body.HTNG_HotelRoomStatusUpdateNotifRQ.Room');
                if (! Arr::has($arrayData, 'Body.HTNG_HotelRoomStatusUpdateNotifRQ.Room.0')) {
                    $room_data = [$room_data];
                }
                $rooms = [];
                foreach ($room_data as $room) {
                    $rooms[] = [
                        'status' => Arr::get($room, 'HKStatus'),
                        'location' => Arr::get($room, '@attributes.RoomID'),
                    ];
                }

                return $this->ChangeHousekeepingStatus($rooms);
            }
        } catch (\Exception $e) {
            \Log::error($e);

            return $this->ErrorXML($this->INTERNAL_SERVER);
        }
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @param  object (json) $arrayData contiene los datos enviados en el mensaje xml
     * @return string (xml) retorna como respuesta un xml que puede ser un error o un mensaje de success
     * Se ejecuta este metodo unicamente cuando se envia un mensaje de tipo "HTNG_HotelCheckInNotifRQ"
     * Se encarga de realizar el check-in a una reservación, se valida los datos necesarios para realizar la acción
     * Ademas seejecuta el metodo ChangeHousekeepingStatus(), esto con el fin de cambiar el estado del cuarto, con los datos que se reciben en el xml
     */
    public function HTNG_CheckIn($arrayData)
    {
        $validate = [
            'Body.HTNG_HotelCheckInNotifRQ.@attributes.TimeStamp',
            'Body.HTNG_HotelCheckInNotifRQ.HotelReservations.HotelReservation.UniqueID.@attributes.Type',
            'Body.HTNG_HotelCheckInNotifRQ.HotelReservations.HotelReservation.UniqueID.@attributes.ID',
            'Body.HTNG_HotelCheckInNotifRQ.Room.@attributes.RoomID',
            'Body.HTNG_HotelCheckInNotifRQ.Room.HKStatus',
        ];
        if (Arr::has($arrayData, $validate)) {
            DB::beginTransaction();
            try {
                $this->TimeStamp = Arr::get($arrayData, 'Body.HTNG_HotelCheckInNotifRQ.@attributes.TimeStamp');
                $data = Arr::get($arrayData, 'Body.HTNG_HotelCheckInNotifRQ.HotelReservations.HotelReservation');
                $reservation_number = Arr::get($data, 'UniqueID.@attributes.ID');
                $location = Arr::get($arrayData, 'Body.HTNG_HotelCheckInNotifRQ.Room.@attributes.RoomID');
                $reservations = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('reservation_number', $reservation_number)->where('status', 1)->get();
                if ($reservations) {
                    foreach ($reservations as $reservation) {
                        $reservation->reservation_status = 1;
                        $reservation->check_in = date('Y-m-d H:i:s', strtotime($this->TimeStamp));

                        $room_no = 0;
                        if (! empty($location)) {
                            $room = $this->getRoom($this->hotel_id, $this->staff_id, $location);
                            $room_no = $room['room_id'];
                        }
                        $reservation->room_no = $room_no;

                        $reservation->save();
                        date_default_timezone_set('UTC');
                        $this->saveLogTracker([
                            'hotel_id' => $this->hotel_id,
                            'module_id' => 8,
                            'action' => 'Check-In',
                            'prim_id' => $reservation->guest_id,
                            'staff_id' => $this->staff_id,
                            'date_time' => date('Y-m-d H:i:s'),
                            'comments' => 'Check-In to Sno: '.$reservation->sno,
                            'type' => 'API-infor',
                        ]);
                        $this->configTimeZone($this->hotel_id);
                    }
                    $rooms = [];
                    $rooms[] = [
                        'status' => Arr::get($arrayData, 'Body.HTNG_HotelCheckInNotifRQ.Room.HKStatus'),
                        'location' => $location,
                    ];

                    $this->ChangeHousekeepingStatus($rooms);
                    DB::commit();

                    return $this->SuccessChangeHSK();
                } else {
                    DB::rollback();

                    return $this->ErrorXML($this->RECORD_NOT_FOUND);
                }
            } catch (\Exception $e) {
                \Log::error($e);
                DB::rollback();

                return $this->ErrorXML($this->INTERNAL_SERVER);
            }
        } else {
            return $this->ErrorXML($this->BAD_REQUEST);
        }
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @param  object (json) $arrayData contiene los datos enviados en el mensaje xml
     * @return string (xml) retorna como respuesta un xml que puede ser un error o un mensaje de success
     * Se ejecuta este metodo unicamente cuando se envia un mensaje de tipo "HTNG_HotelCheckOutNotifRQ"
     * Se encarga de realizar el check-out a una reservación, se valida los datos necesarios para realizar la acción
     * Ademas seejecuta el metodo ChangeHousekeepingStatus(), esto con el fin de cambiar el estado del cuarto, con los datos que se reciben en el xml
     */
    public function HTNG_CheckOut($arrayData)
    {
        $validate = [
            'Body.HTNG_HotelCheckOutNotifRQ.@attributes.TimeStamp',
            'Body.HTNG_HotelCheckOutNotifRQ.HotelReservations.HotelReservation.UniqueID.@attributes.Type',
            'Body.HTNG_HotelCheckOutNotifRQ.HotelReservations.HotelReservation.UniqueID.@attributes.ID',
            'Body.HTNG_HotelCheckInNotifRQ.Room.HKStatus',
        ];
        if (Arr::has($arrayData, $validate)) {
            DB::beginTransaction();
            try {
                $this->TimeStamp = Arr::get($arrayData, 'Body.HTNG_HotelCheckOutNotifRQ.@attributes.TimeStamp');
                $data = Arr::get($arrayData, 'Body.HTNG_HotelCheckOutNotifRQ.HotelReservations.HotelReservation');
                $reservation_number = Arr::get($data, 'UniqueID.@attributes.ID');
                $location = Arr::get($arrayData, 'Body.HTNG_HotelCheckOutNotifRQ.Room.@attributes.RoomID');
                $reservations = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('reservation_number', $reservation_number)->where('status', 1)->get();
                if ($reservations) {
                    foreach ($reservations as $reservation) {
                        if ($reservation->reservation_status == '1') {
                            $reservation->reservation_status = 3;
                            $reservation->status = 0;
                            $reservation->check_out = date('Y-m-d H:i:s', strtotime($this->TimeStamp));
                            $reservation->save();

                            date_default_timezone_set('UTC');

                            $this->saveLogTracker([
                                'hotel_id' => $this->hotel_id,
                                'module_id' => 8,
                                'action' => 'Check-Out',
                                'prim_id' => $reservation->guest_id,
                                'staff_id' => $this->staff_id,
                                'date_time' => date('Y-m-d H:i:s'),
                                'comments' => 'Check-Out to Sno: '.$reservation->sno,
                                'type' => 'API-infor',
                            ]);

                            $this->configTimeZone($this->hotel_id);
                        }
                    }
                    DB::commit();

                    $rooms = [];
                    $rooms[] = [
                        'status' => Arr::get($arrayData, 'Body.HTNG_HotelCheckInNotifRQ.Room.HKStatus'),
                        'location' => $location,
                    ];

                    $this->ChangeHousekeepingStatus($rooms);

                    return $this->SuccessChangeHSK();
                } else {
                    DB::rollback();

                    return $this->ErrorXML($this->RECORD_NOT_FOUND);
                }
            } catch (\Exception $e) {
                \Log::error($e);
                DB::rollback();

                return $this->ErrorXML($this->INTERNAL_SERVER);
            }
        } else {
            return $this->ErrorXML($this->BAD_REQUEST);
        }
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @param  object (json) $arrayData contiene los datos enviados en el mensaje xml
     * @return string (xml) retorna como respuesta un xml que puede ser un error o un mensaje de success
     * Se ejecuta este metodo unicamente cuando se envia un mensaje de tipo "HTNG_HotelStayUpdateNotifRQ"
     * Se valida los datos necesarios para realizar la actualización de una reservación, si se valida correctamente este envia los datos de la reserva a "HTNG_EditReservation()"
     * Ademas seejecuta el metodo ChangeHousekeepingStatus(), esto con el fin de cambiar el estado del cuarto, con los datos que se reciben en el xml
     */
    public function HTNG_UpdateReservation($arrayData)
    {
        $validate = [
            'Body.HTNG_HotelStayUpdateNotifRQ.@attributes.TimeStamp',
            'Body.HTNG_HotelStayUpdateNotifRQ.HotelReservations.HotelReservation.@attributes.ResStatus',
            'Body.HTNG_HotelStayUpdateNotifRQ.HotelReservations.HotelReservation.UniqueID.@attributes.Type',
            'Body.HTNG_HotelStayUpdateNotifRQ.HotelReservations.HotelReservation.UniqueID.@attributes.ID',
        ];

        if (Arr::has($arrayData, $validate)) {
            $this->TimeStamp = Arr::get($arrayData, 'Body.HTNG_HotelStayUpdateNotifRQ.@attributes.TimeStamp');
            $data = Arr::get($arrayData, 'Body.HTNG_HotelStayUpdateNotifRQ.HotelReservations.HotelReservation');
            $location = Arr::get($arrayData, 'Body.HTNG_HotelStayUpdateNotifRQ.Room.@attributes.RoomID');
            $rooms = [];
            $rooms[] = [
                'status' => Arr::get($arrayData, 'Body.HTNG_HotelStayUpdateNotifRQ.Room.HKStatus'),
                'location' => $location,
            ];

            $this->ChangeHousekeepingStatus($rooms);

            return $this->HTNG_EditReservation($data);
        }
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @param  object (json) $arrayData contiene los datos enviados en el mensaje xml
     * @return string (xml) retorna como respuesta un xml que puede ser un error o un mensaje de success
     * Se encarga de realizar la actualizacion de datos a una reservación y/o a un huesped unicamente cuando el mensaje es de tipo "HTNG_HotelStayUpdateNotifRQ"
     */
    public function HTNG_EditReservation($data)
    {
        DB::beginTransaction();
        try {
            $__guests = null;
            $guests = [];
            if (Arr::has($data, 'ResGuests.ResGuest.Profiles.ProfileInfo')) {
                $ProfileInfo = Arr::get($data, 'ResGuests.ResGuest.Profiles.ProfileInfo');
                if (! Arr::has($ProfileInfo, '0')) {
                    $ProfileInfo = [$ProfileInfo];
                }
                $__guests = Arr::where($ProfileInfo, function ($value) {
                    $isCustomer = false;
                    $UniqueID = Arr::get($value, 'Profile.UserID', []);

                    if (! Arr::has($UniqueID, '0')) {
                        $UniqueID = [$UniqueID];
                    }

                    foreach ($UniqueID as $key => $value) {
                        if ($value['@attributes']['Type'] == '1') {
                            $isCustomer = true;
                        }
                    }

                    return $isCustomer;
                });
                if (count($__guests) > 0) {
                    foreach ($__guests as  $__guest) {
                        $rs = $this->GuestRegistration($__guest, true);
                        if ($rs) {
                            $guests[] = $rs;
                        }
                    }
                    $validate = [
                        'RoomStays.RoomStay.TimeSpan.@attributes.Start',
                        'RoomStays.RoomStay.TimeSpan.@attributes.End',
                        'UniqueID.@attributes.ID',
                    ];

                    if (Arr::has($data, $validate)) {
                        $check_in = Arr::get($data, 'RoomStays.RoomStay.TimeSpan.@attributes.Start');
                        $check_out = Arr::get($data, 'RoomStays.RoomStay.TimeSpan.@attributes.End');
                        $comment = Arr::get($data, 'Services.Service.ServiceDetails.Comments.Comment.Text', '');
                        $locations = Arr::get($data, 'RoomStays.RoomStay.RoomRates.RoomRate', []);

                        if (! Arr::has($locations, '0')) {
                            $locations = [$locations];
                        }

                        if (count($locations) > 0) {
                            $location = Arr::get($locations[0], '@attributes.RoomID', '');
                            if (! empty($location)) {
                                $room = $this->getRoom($this->hotel_id, $this->staff_id, $location);
                                $room_no = $room['room_id'];
                            }
                        }
                        foreach ($guests as  $guest) {
                            $__update = '';
                            $reservation = GuestCheckinDetails::where('hotel_id', $this->hotel_id)
                                ->where('reservation_number', Arr::get($data, 'UniqueID.@attributes.ID', ''))
                                ->where('guest_id', $guest->guest_id)
                                ->where('status', 1)
                                ->first();
                            if ($reservation) {
                                if ($reservation->check_out != $check_out) {
                                    $__update .= "check_out: $reservation->check_out to $check_out, ";
                                    $reservation->check_out = $check_out;
                                }
                                if ($reservation->check_in != $check_in) {
                                    $__update .= "check_out: $reservation->check_in to $check_in, ";
                                    $reservation->check_in = $check_in;
                                }
                                if ($reservation->comment != $comment) {
                                    $__update .= "comment: $reservation->comment to $comment, ";
                                    $reservation->comment = $comment;
                                }
                                if ($__update != '') {
                                    $reservation->save();
                                    date_default_timezone_set('UTC');
                                    $this->saveLogTracker([
                                        'hotel_id' => $this->hotel_id,
                                        'module_id' => 8,
                                        'action' => 'update',
                                        'prim_id' => $guest->guest_id,
                                        'staff_id' => $this->staff_id,
                                        'date_time' => date('Y-m-d H:i:s'),
                                        'comments' => "Update Reservation sno: '.$reservation->sno.' information: $__update",
                                        'type' => 'API-infor',
                                    ]);
                                    $this->configTimeZone($this->hotel_id);
                                }
                            }
                        }
                        DB::commit();

                        return $this->SuccessChangeHSK();
                    }
                }
            }
            DB::rollback();

            return $this->ErrorXML($this->RECORD_NOT_FOUND);
        } catch (\Exception $e) {
            \Log::error($e);
            DB::rollback();

            return $this->ErrorXML($this->INTERNAL_SERVER);
        }
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @param  string (action) $action contiene el tipo de mensaje que se recibió en el index
     * @param  object (json) $arrayData contiene los datos enviados en el mensaje xml
     * @return string (xml)
     * Se realiza el enrutamiento del mensaje que se recibe en el metodo index y se envia al metodo necesario para cada mensaje.
     */
    public function RouteControl($action, $arrayData)
    {
        switch ($action) {
            case 'HTNG_HotelRoomMoveNotifRQ':
                return $this->HotelRoom($arrayData);
                break;
            case 'HTNG_HotelStayUpdateNotifRQ':
                return $this->HTNG_UpdateReservation($arrayData);
                break;
            case 'OTA_HotelResNotifRQ':
                return $this->HotelReservation($arrayData);
                break;
            case 'HTNG_HotelRoomStatusUpdateNotifRQ':
                return $this->HotelHousekeeping($arrayData);
                break;
            case 'HTNG_HotelCheckOutNotifRQ':
                return $this->HTNG_CheckOut($arrayData);
                break;
            case 'HTNG_HotelCheckInNotifRQ':
                return $this->HTNG_CheckIn($arrayData);
                break;
            default:
                return $this->ErrorXML($this->BAD_REQUEST);
                break;
        }
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @param  string (action) $action contiene la accion general del mensaje que se recibió en el index
     * @param  string (sub-action) $subAction contiene el tipo de acción que se debe realizar a la reservación
     * @param  object (json) $arrayData contiene los datos enviados en el mensaje xml
     * @return string (xml)
     * Se realiza el enrutamiento del mensaje de tipo "OTA_HotelResNotifRQ" que se recibe en el metodo index y se envia al metodo necesario para cada mensaje.
     */
    private function route($action, $subAction, $data)
    {
        switch ($action) {
            case 'Commit':
                switch ($subAction) {
                    case 'Reserved':
                        return $this->CreateReservation($data);
                        break;
                    default:
                        return $this->ErrorXML($this->BAD_REQUEST);
                        break;
                }
                break;
            case 'Modify':
                switch ($subAction) {
                    case 'Reserved':
                        return $this->EditReservation($data);
                        break;
                    case 'In-house':
                        return $this->CheckInReservation($data);
                        break;
                    case 'Checked-out':
                        return $this->CheckOutReservation($data);
                        break;
                    default:
                        return $this->ErrorXML($this->BAD_REQUEST);
                        break;
                }
                break;
            case 'Cancel':
                switch ($subAction) {
                    case 'Cancelled':
                        return $this->CancelledReservation(2, 'Cancell');
                        break;

                    case 'No-Show':
                        return $this->CancelledReservation(4, 'No Show');
                        break;
                    default:
                        return $this->ErrorXML($this->BAD_REQUEST);
                        break;
                }
                break;
            default:
                return $this->ErrorXML($this->BAD_REQUEST);
                break;
        }
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @param  object (json) $data contiene los datos enviados en el mensaje xml
     * @return string (xml)
     * Se realiza la creación de la reserva cuando el mensaje es de tipo OTA_HotelResNotifRQ
     * Ademas se ejecuta el metodo GuestRegistration() el cual crea, actualiza y/o solo devuelve los datos del huesped.
     */
    private function CreateReservation($data)
    {
        DB::beginTransaction();
        try {
            $__guests = null;
            $guests = [];
            if (Arr::has($data, 'ResGuests.ResGuest.Profiles.ProfileInfo')) {
                $ProfileInfo = Arr::get($data, 'ResGuests.ResGuest.Profiles.ProfileInfo');
                if (! Arr::has($ProfileInfo, '0')) {
                    $ProfileInfo = [$ProfileInfo];
                }
                $__guests = Arr::where($ProfileInfo, function ($value, $key) {
                    $isCustomer = false;
                    $UniqueID = Arr::get($value, 'UniqueID', []);

                    if (! Arr::has($UniqueID, '0')) {
                        $UniqueID = [$UniqueID];
                    }
                    foreach ($UniqueID as $key => $value) {
                        if ($value['@attributes']['Type'] == '1') {
                            $id = $value['@attributes']['ID'];
                            $isCustomer = true;
                        }
                    }

                    return $isCustomer;
                });
                if (count($__guests) > 0) {
                    $__guests = array_values(Arr::sort($__guests, function ($value) {
                        return Arr::get($value, 'Profile.@attributes.ProfileType');
                    }));
                    foreach ($__guests as  $__guest) {
                        $rs = $this->GuestRegistration($__guest);
                        if ($rs) {
                            $guests[] = $rs;
                        }
                    }
                    $validate = [
                        'RoomStays.RoomStay.TimeSpan.@attributes.Start',
                        'RoomStays.RoomStay.TimeSpan.@attributes.End',
                        'UniqueID.@attributes.ID',
                    ];

                    if (Arr::has($data, $validate)) {
                        $is_reservation = GuestCheckinDetails::where('reservation_number', Arr::get($data, 'UniqueID.@attributes.ID'))
                            ->where('hotel_id', $this->hotel_id)->first();
                        if (! $is_reservation) {
                            $room_no = 0;
                            $locations = Arr::get($data, 'RoomStays.RoomStay.RoomRates.RoomRate', []);

                            if (! Arr::has($locations, '0')) {
                                $locations = [$locations];
                            }

                            if (count($locations) > 0) {
                                $location = Arr::get($locations[0], '@attributes.RoomID', '');
                                if (! empty($location)) {
                                    $room = $this->getRoom($this->hotel_id, $this->staff_id, $location);
                                    $room_no = $room['room_id'];
                                }
                            }

                            $main_guest = '0';
                            foreach ($guests as $key => $guest) {
                                $guestCheckinDetails = GuestCheckinDetails::create([
                                    'guest_id' => $guest->guest_id,
                                    'hotel_id' => $this->hotel_id,
                                    'room_no' => $room_no,
                                    'check_in' => Arr::get($data, 'RoomStays.RoomStay.TimeSpan.@attributes.Start'),
                                    'check_out' => Arr::get($data, 'RoomStays.RoomStay.TimeSpan.@attributes.End'),
                                    'comment' => Arr::get($data, 'Services.Service.ServiceDetails.Comments.Comment.Text', ''),
                                    'status' => 1,
                                    'reservation_status' => 0,
                                    'reservation_number' => Arr::get($data, 'UniqueID.@attributes.ID', ''),
                                    'main_guest' => $main_guest,
                                ]);
                                if ($key == 0) {
                                    $main_guest = $guestCheckinDetails->sno;
                                }
                            }
                        }

                        DB::commit();

                        return $this->ReservationSuccess();
                    }
                }

                return $this->ErrorXML($this->BAD_REQUEST);
            }

            return $this->ErrorXML($this->BAD_REQUEST);
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error in inforController > CreateReservation');
            \log::error("$e");

            return $this->ErrorXML($this->INTERNAL_SERVER);
        }
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @param  object (json) $guest contiene los datos del huesped enviados por el mensaje xml
     * @param  bool Valida si el registro del huesped viene de un mensaje HTNG o un mensaje OTA, esto con el fin de hacer la busqueda de los datos del huesped en las
     *  ubicaciones correctas del objeto json
     * @return string (xml)
     * Se realiza la creación, actualización y/o consulta del huesped
     */
    private function GuestRegistration($guest, $is_HTNG = false)
    {
        try {
            $guest_number = 0;
            $UniqueID = $is_HTNG ? $guest['Profile']['UserID'] : $guest['UniqueID'];
            if (! Arr::has($UniqueID, '0')) {
                $UniqueID = [$UniqueID];
            }
            $guest_number = $UniqueID[0]['@attributes']['ID'];
            $guestRelation = IntegrationsGuestInformation::where('hotel_id', $this->hotel_id)->where('guest_number', $guest_number)->first();

            $firstname = Arr::get($guest, 'Profile.Customer.PersonName.GivenName', ' ');
            if (is_array($firstname)) {
                $firstname = '';
            }
            $lastname = Arr::get($guest, 'Profile.Customer.PersonName.Surname', ' ');
            if (is_array($lastname)) {
                $lastname = '';
            }
            $email_address = Arr::get($guest, 'Profile.Customer.Email', ' ');
            if (is_array($email_address)) {
                $email_address = '';
            }
            $phone_no = Arr::get($guest, 'Profile.Customer.Telephone', []);
            if (! is_array($phone_no)) {
                $phone_no[] = $phone_no;
            }
            $phone_no = Arr::get($phone_no, '0.@attributes.PhoneNumber', Arr::get($phone_no, '@attributes.PhoneNumber', ''));
            $address = Arr::get($guest, 'Profile.Customer.Address.AddressLine', '');
            if (is_array($address)) {
                $address = '';
            }
            $state = Arr::get($guest, 'Profile.Customer.Address.StateProv.@attributes.StateCode', ' ');
            if (is_array($state)) {
                $state = '';
            }
            $zipcode = Arr::get($guest, 'Profile.Customer.Address.PostalCode', ' ');
            if (is_array($zipcode)) {
                $zipcode = '';
            }
            $city = Arr::get($guest, 'Profile.Customer.Address.CityName', ' ');
            if (is_array($city)) {
                $city = '';
            }

            if ($guestRelation) {
                $__update = '';
                $guestRegistration = GuestRegistration::find($guestRelation->guest_id);

                if ($guestRegistration->firstname != $firstname) {
                    $__update .= "firstname: $guestRegistration->firstname to $firstname, ";
                    $guestRegistration->firstname = $firstname;
                }
                if ($guestRegistration->lastname != $lastname) {
                    $__update .= "lastname: $guestRegistration->lastname to $lastname, ";
                    $guestRegistration->lastname = $lastname;
                }
                if ($guestRegistration->email_address != $email_address) {
                    $__update .= "email_address: $guestRegistration->email_address to $email_address, ";
                    $guestRegistration->email_address = $email_address;
                }
                if ($guestRegistration->phone_no != $phone_no) {
                    $__update .= "phone_no: $guestRegistration->phone_no to $phone_no, ";
                    $guestRegistration->phone_no = $phone_no;
                }
                if ($guestRegistration->address != $address) {
                    $__update .= "address: $guestRegistration->address to $address, ";
                    $guestRegistration->address = $address;
                }
                if ($guestRegistration->state != $state) {
                    $__update .= "state: $guestRegistration->state to $state, ";
                    $guestRegistration->state = $state;
                }
                if ($guestRegistration->zipcode != $zipcode) {
                    $__update .= "zipcode: $guestRegistration->zipcode to $zipcode, ";
                    $guestRegistration->zipcode = $zipcode;
                }
                if ($guestRegistration->city != $city) {
                    $__update .= "city: $guestRegistration->city to $city, ";
                    $guestRegistration->city = $city;
                }
                $now = date('Y-m-d H:i:s');
                if (! empty($__update)) {
                    $guestRegistration->updated_on = $now;
                    $guestRegistration->updated_by = $this->staff_id;
                    $guestRegistration->save();
                    date_default_timezone_set('UTC');
                    $this->saveLogTracker([
                        'hotel_id' => $this->hotel_id,
                        'module_id' => 8,
                        'action' => 'update',
                        'prim_id' => $guestRegistration->guest_id,
                        'staff_id' => $this->staff_id,
                        'date_time' => $now,
                        'comments' => "Update Guest information: $__update",
                        'type' => 'API-infor',
                    ]);
                    $this->configTimeZone($this->hotel_id);
                }
            } else {
                $guestRegistration = GuestRegistration::create([
                    'hotel_id' => $this->hotel_id,
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'email_address' => $email_address,
                    'phone_no' => $phone_no,
                    'address' => $address,
                    'state' => $state,
                    'zipcode' => $zipcode,
                    'city' => $city,
                    'comment' => ' ',
                    'language' => 'en',
                    'created_by' => 1,
                    'created_on' => date('Y-m-d H:i:s'),
                ]);
                IntegrationsGuestInformation::create([
                    'hotel_id' => $this->hotel_id,
                    'guest_id' => $guestRegistration->guest_id,
                    'guest_number' => $guest_number,
                ]);
            }

            return $guestRegistration;
        } catch (\Exception $e) {
            \Log::error('Error in inforController > GuestRegistration');
            \Log::error("$e");

            return null;
        }
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @param  int  $__status Valida el tipo de error que se esta solicitando, a partir de las variables globales que se reciben en el metodo
     * @return string (xml)
     * Se realiza la creación de la reserva cuando el mensaje es de tipo OTA_HotelResNotifRQ
     */
    public function ErrorXML($__status = 0)
    {
        $message = '';
        $status = '';
        switch ($__status) {
            case $this->RECORD_NOT_FOUND:
                $message = 'Record Not Found';
                $status = 404;
                break;
            case $this->BAD_REQUEST:
                $message = 'Bad Request Required field missing';
                $status = 400;
                break;
            case $this->INTERNAL_SERVER:
                $message = 'Internal Server Error';
                $status = 500;
                break;
        }
        $xml_response = ArrayToXml::convert([
            'soap:Body' => [
                'm:Response' => [
                    'm:Status' => $status,
                    'm:Message' => $message,
                ],
                '_attributes' => [
                    'xmlns:m' => '',
                ],
            ],
            '_attributes' => [
                'xmlns:soap' => 'http://www.w3.org/2003/05/soap-envelope/',
                'soap:encodingStyle' => 'http://www.w3.org/2003/05/soap-encoding',
            ],
        ], 'soap:Envelope');

        return $xml_response;
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @return string (xml)
     * Se retorna la plantilla xml para una respuesta satisfactoria cuando el mensaje es de tipo OTA_HotelResNotifRQ
     */
    public function ReservationSuccess()
    {
        $__HotelReservationID = [];
        foreach ($this->HotelReservationID as $value) {
            $__HotelReservationID[] = [
                '_attributes' => [
                    'ResID_Type' => Arr::get($value, '@attributes.ResID_Type', ''),
                    'ResID_Value' => Arr::get($value, '@attributes.ResID_Value', ''),
                    'ResID_Source' => 'HMS',
                    'ResID_SourceContext' => 'PMS',
                    'ForGuest' => 'true',
                ],
                '_value' => '',
            ];
        }

        $arrayXML = [
            's:Header' => [
                'wsa:ReplyTo' => [
                    'wsa:Address' => 'http://www.w3.org/2005/08/addressing/none',
                    'wsa:ReferenceParameters' => [
                        'axis2:ServiceGroupId' => [
                            '_attributes' => [
                                'xmlns:axis2' => 'http://ws.apache.org/namespaces/axis2',
                            ],
                            '_value' => 'urn:uuid:9d4eeedc-d643-428e-b71f-78dc4cb389a2',
                        ],
                    ],
                ],
                'wsa:RelatesTo' => 'HMS1566904317',
                'wsa:MessageID' => $this->MessageId,
                'wsa:Action' => [
                    '_attributes' => [
                        's:mustUnderstand' => '1',
                    ],
                    '_value' => 'OTA_HotelResNotifRQ',
                ],
                '_attributes' => [
                    'xmlns:wsa' => 'http://www.w3.org/2005/08/addressing',
                ],
            ],
            's:Body' => [
                'OTA_HotelResNotifRS' => [
                    'Success' => [],
                    'HotelReservations' => [
                        'HotelReservation' => [
                            'UniqueID' => [
                                '_attributes' => [
                                    'Type' => $this->UniqueID_Type,
                                    'ID' => $this->UniqueID_ID,
                                    'Instance' => 'HMS',
                                    'ID_Context' => 'PMS',
                                ],
                            ],
                            'ResGlobalInfo' => [
                                'HotelReservationIDs' => [
                                    'HotelReservationID' => $__HotelReservationID,
                                ],
                            ],
                        ],
                    ],
                    '_attributes' => [
                        'xmlns' => 'http://www.opentravel.org/OTA/2003/05',
                        'TimeStamp' => $this->TimeStamp,
                        'Version' => '0',
                        'ResResponseType' => 'Modified',
                    ],
                ],
                '_attributes' => [
                    'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                    'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
                ],
            ],
            '_attributes' => [
                'xmlns:s' => 'http://www.w3.org/2003/05/soap-envelope',
                'xmlns:a' => 'http://www.w3.org/2005/08/addressing',
            ],
        ];

        $xml_response = new ArrayToXml($arrayXML, 's:Envelope');
        $dom = $xml_response->toDom();
        $dom->encoding = 'utf-8';
        $xml_response = $dom->saveXML();
        $xml_response = str_replace('></HotelReservationID>', '/>', $xml_response);

        return $xml_response;
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @param  object (json) $data contiene los datos enviados en el mensaje xml
     * @return string (xml) retorna como respuesta un xml que puede ser un error o un mensaje de success
     * Se encarga de realizar la actualizacion de datos a una reservación y/o a un huesped unicamente cuando el mensaje es de tipo "OTA_HotelResNotifRQ"
     * Ademas, si el estado de la reserva es Check-in ( reservation_status  = 1 ) y ademas el cuarto se cambia cuando este estado esta activo, se realiza los cambios
     * respectivos en esta situación.
     */
    public function EditReservation($data)
    {
        DB::beginTransaction();
        try {
            $__guests = null;
            $guests = [];
            if (Arr::has($data, 'ResGuests.ResGuest.Profiles.ProfileInfo')) {
                $ProfileInfo = Arr::get($data, 'ResGuests.ResGuest.Profiles.ProfileInfo');
                if (! Arr::has($ProfileInfo, '0')) {
                    $ProfileInfo = [$ProfileInfo];
                }
                $__guests = Arr::where($ProfileInfo, function ($value) {
                    $isCustomer = false;
                    $UniqueID = Arr::get($value, 'UniqueID', []);
                    if (! Arr::has($UniqueID, '0')) {
                        $UniqueID = [$UniqueID];
                    }
                    foreach ($UniqueID as $key => $value) {
                        if ($value['@attributes']['Type'] == '1') {
                            $isCustomer = true;
                        }
                    }

                    return $isCustomer;
                });
                if (count($__guests) > 0) {
                    $__guests = array_values(Arr::sort($__guests, function ($value) {
                        return Arr::get($value, 'Profile.@attributes.ProfileType');
                    }));
                    foreach ($__guests as  $__guest) {
                        $rs = $this->GuestRegistration($__guest);
                        if ($rs) {
                            $guests[] = $rs;
                        }
                    }
                    $validate = [
                        'RoomStays.RoomStay.TimeSpan.@attributes.Start',
                        'RoomStays.RoomStay.TimeSpan.@attributes.End',
                        'UniqueID.@attributes.ID',
                    ];

                    if (Arr::has($data, $validate)) {
                        $check_in = Arr::get($data, 'RoomStays.RoomStay.TimeSpan.@attributes.Start');
                        $check_out = Arr::get($data, 'RoomStays.RoomStay.TimeSpan.@attributes.End');
                        $comment = Arr::get($data, 'Services.Service.ServiceDetails.Comments.Comment.Text', '');
                        $room_no = 0;
                        $locations = Arr::get($data, 'RoomStays.RoomStay.RoomRates.RoomRate', []);

                        if (! Arr::has($locations, '0')) {
                            $locations = [$locations];
                        }

                        if (count($locations) > 0) {
                            $location = Arr::get($locations[0], '@attributes.RoomID', '');
                            if (! empty($location)) {
                                $room = $this->getRoom($this->hotel_id, $this->staff_id, $location);
                                $room_no = $room['room_id'];
                            }
                        }
                        $sw = false;
                        foreach ($guests as  $guest) {
                            $__update = '';
                            $reservation = GuestCheckinDetails::where('hotel_id', $this->hotel_id)
                                ->where('reservation_number', Arr::get($data, 'UniqueID.@attributes.ID', ''))
                                ->where('guest_id', $guest->guest_id)
                                ->where('status', 1)
                                ->first();
                            $main_guest = '0';
                            if ($reservation) {
                                $sw = true;
                                if ($reservation->check_out != $check_out) {
                                    $__update .= "check_out: $reservation->check_out to $check_out, ";
                                    $reservation->check_out = $check_out;
                                }

                                if ($reservation->comment != $comment) {
                                    $__update .= "comment: $reservation->comment to $comment, ";
                                    $reservation->comment = $comment;
                                }
                                if ($reservation->reservation_status == 1) {
                                    if ($reservation->room_no != $room_no ? true : false) {
                                        $new_check_in = date('Y-m-d H:i:s');
                                        $new_data = [
                                            'guest_id' => $reservation->guest_id,
                                            'hotel_id' => $this->hotel_id,
                                            'room_no' => $room_no,
                                            'check_in' => $new_check_in,
                                            'check_out' => $reservation->check_out,
                                            'comment' => $reservation->comment ? $reservation->comment : ' ',
                                            'status' => 1,
                                            'reservation_status' => 1,
                                            'reservation_number' => $reservation->reservation_number,
                                            'main_guest' => $main_guest,
                                        ];
                                        $NewReservation = GuestCheckinDetails::create($new_data);
                                        if ($reservation->main_guest == '0') {
                                            $main_guest = $NewReservation->sno;
                                        }
                                        $reservation->reservation_number = $reservation->reservation_number.'_RM';
                                        $reservation->check_out = $new_check_in;
                                        $reservation->status = 0;
                                        $reservation->reservation_status = 5;
                                        $room_old = $this->getRoom($this->hotel_id, $this->staff_id, $reservation->room_no);
                                        $room_move = [
                                            'guest_id' => $reservation->guest_id,
                                            'current_room_no' => $room_old['room_id'],
                                            'new_room_no' => $room_no,
                                            'hotel_id' => $this->hotel_id,
                                            'created_by' => $this->staff_id,
                                            'created_on' => date('Y-m-d H:i:s'),
                                            'status' => 1,
                                            'active' => 1,
                                            'updated_by' => $this->staff_id,
                                        ];
                                        \App\Models\RoomMove::create($room_move);
                                        $__update .= "Change_Room: $reservation->room_no to $room_no, ";
                                    }
                                } else {
                                    if ($reservation->check_in != $check_in) {
                                        $__update .= "check_in: $reservation->check_in to $check_in, ";
                                        $reservation->check_in = $check_in;
                                    }
                                    if ($this->Lockedroom && $reservation->room_no != $room_no) {
                                        $__update .= "room_no: $reservation->room_no to $room_no, ";
                                        $reservation->room_no = $room_no;
                                    }
                                }
                                if ($__update != '') {
                                    $reservation->save();
                                    date_default_timezone_set('UTC');
                                    $this->saveLogTracker([
                                        'hotel_id' => $this->hotel_id,
                                        'module_id' => 8,
                                        'action' => 'update',
                                        'prim_id' => $guest->guest_id,
                                        'staff_id' => $this->staff_id,
                                        'date_time' => date('Y-m-d H:i:s'),
                                        'comments' => "Update Reservation sno: '.$reservation->sno.' information: $__update",
                                        'type' => 'API-infor',
                                    ]);
                                    $this->configTimeZone($this->hotel_id);
                                }
                            }
                            if (! $sw) {
                                $this->CreateReservation($data);
                            }
                        }
                        DB::commit();

                        return $this->ReservationSuccess();
                    }
                }
            }

            DB::rollback();

            return $this->ErrorXML($this->RECORD_NOT_FOUND);
        } catch (\Exception $e) {
            \Log::error($e);
            DB::rollback();

            return $this->ErrorXML($this->INTERNAL_SERVER);
        }
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @param  object (json) $data contiene los datos enviados en el mensaje xml
     * @return string (xml) retorna como respuesta un xml que puede ser un error o un mensaje de success
     * Se encarga de realizar el cambio de estado de la reservación a check-in
     * Ademas realiza la actualizacion de datos de la reservación y/o a un huesped unicamente cuando el mensaje es de tipo "OTA_HotelResNotifRQ"
     */
    public function CheckInReservation($data)
    {
        DB::beginTransaction();
        try {
            $reservation_number = $this->UniqueID_ID;
            $validate = [
                'RoomStays.RoomStay.TimeSpan.@attributes.Start',
            ];

            if (Arr::has($data, $validate)) {
                $reservations = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('reservation_number', $reservation_number)->where('status', 1)->get();
                if ($reservations) {
                    foreach ($reservations as $reservation) {
                        if ($reservation->reservation_status != '1') {
                            $reservation->reservation_status = 1;

                            $reservation->check_in = date('Y-m-d H:i:s');
                            $room_no = 0;
                            $locations = Arr::get($data, 'RoomStays.RoomStay.RoomRates.RoomRate', []);
                            if (! Arr::has($locations, '0')) {
                                $locations = [$locations];
                            }
                            if (count($locations) > 0) {
                                $location = Arr::get($locations[0], '@attributes.RoomID', '');
                                if (! empty($location)) {
                                    $room = $this->getRoom($this->hotel_id, $this->staff_id, $location);
                                    $room_no = $room['room_id'];
                                }
                            }
                            $reservation->room_no = $room_no;

                            $reservation->save();
                            date_default_timezone_set('UTC');
                            $this->saveLogTracker([
                                'hotel_id' => $this->hotel_id,
                                'module_id' => 8,
                                'action' => 'Check-Out',
                                'prim_id' => $reservation->guest_id,
                                'staff_id' => $this->staff_id,
                                'date_time' => date('Y-m-d H:i:s'),
                                'comments' => 'Check-In to Sno: '.$reservation->sno,
                                'type' => 'API-infor',
                            ]);
                            $this->configTimeZone($this->hotel_id);
                        }
                    }
                    DB::commit();

                    return $this->EditReservation($data);
                } else {
                    return $this->ErrorXML($this->RECORD_NOT_FOUND);
                }
            }
            DB::rollback();

            return $this->ErrorXML($this->BAD_REQUEST);
        } catch (\Exception $e) {
            \Log::error('Error in inforController > CheckInReservation');
            \Log::error("$e");

            return $this->ErrorXML($this->INTERNAL_SERVER);
        }
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @param  object (json) $data contiene los datos enviados en el mensaje xml
     * @return string (xml) retorna como respuesta un xml que puede ser un error o un mensaje de success
     * Se encarga de realizar el cambio de estado de la reservación a check-out
     */
    public function CheckOutReservation($data)
    {
        DB::beginTransaction();
        try {
            $reservation_number = $this->UniqueID_ID;
            $validate = [
                'RoomStays.RoomStay.TimeSpan.@attributes.End',
            ];
            if (Arr::has($data, $validate)) {
                $reservations = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('reservation_number', $reservation_number)->where('status', 1)->get();
                if ($reservations) {
                    foreach ($reservations as $reservation) {
                        if ($reservation->reservation_status != '3') {
                            $reservation->reservation_status = 3;
                            $reservation->status = 0;
                            $reservation->check_out = date('Y-m-d H:i:s');
                            $reservation->save();
                            date_default_timezone_set('UTC');
                            $this->saveLogTracker([
                                'hotel_id' => $this->hotel_id,
                                'module_id' => 8,
                                'action' => 'Check-Out',
                                'prim_id' => $reservation->guest_id,
                                'staff_id' => $this->staff_id,
                                'date_time' => date('Y-m-d H:i:s'),
                                'comments' => 'Check-Out to Sno: '.$reservation->sno,
                                'type' => 'API-infor',
                            ]);
                            $this->configTimeZone($this->hotel_id);
                        }
                    }
                    DB::commit();

                    return $this->ReservationSuccess();
                } else {
                    return $this->ErrorXML($this->RECORD_NOT_FOUND);
                }
            }
            DB::rollback();

            return $this->ErrorXML($this->BAD_REQUEST);
        } catch (\Exception $e) {
            \Log::error('Error in inforController > CheckInReservation');
            \Log::error("$e");

            return $this->ErrorXML($this->INTERNAL_SERVER);
        }
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @param  int (status) $new_status contiene el nuevo estado de la reservación (2: cancelled 4:no-show)
     * @return string (xml) retorna como respuesta un xml que puede ser un error o un mensaje de success
     * Se encarga de realizar el cambio de estado de la reservación a candelado o no-show a partir de la variable $new_status
     */
    public function CancelledReservation($new_status, $title)
    {
        DB::beginTransaction();
        try {
            $reservation_number = $this->UniqueID_ID;
            $reservations = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('reservation_number', $reservation_number)->where('status', 1)->get();
            if ($reservations) {
                foreach ($reservations as $reservation) {
                    $reservation->reservation_status = $new_status;
                    $reservation->status = 0;
                    $reservation->save();
                    date_default_timezone_set('UTC');
                    $this->saveLogTracker([
                        'hotel_id' => $this->hotel_id,
                        'module_id' => 8,
                        'action' => 'Check-Out',
                        'prim_id' => $reservation->guest_id,
                        'staff_id' => $this->staff_id,
                        'date_time' => date('Y-m-d H:i:s'),
                        'comments' => $title.' to Sno: '.$reservation->sno,
                        'type' => 'API-infor',
                    ]);
                    $this->configTimeZone($this->hotel_id);
                }
                DB::commit();

                return $this->ReservationSuccess();
            } else {
                return $this->ErrorXML($this->RECORD_NOT_FOUND);
            }
            DB::rollback();
        } catch (\Exception $e) {
            \Log::error('Error in inforController > CheckInReservation');
            \Log::error("$e");

            return $this->ErrorXML($this->INTERNAL_SERVER);
        }
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @param  object (json) $NewRoom contiene los datos necesarios para realizar el cambio de habitación a una reserva, este metodo es usado unicamente
     *      cuando el tipo de mensaje es HTNG
     * @return string (xml) retorna como respuesta un xml que puede ser un error o un mensaje de success
     */
    public function RoomMove($NewRoom)
    {
        DB::beginTransaction();
        try {
            $reservations = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('reservation_number', $this->UniqueID_ID)
                ->where('status', 1)->orderBy('main_guest', 'ASC')->get();

            $new_room = $this->getRoom($this->hotel_id, $this->staff_id, Arr::get($NewRoom, '@attributes.RoomID'));
            $main_guest = 0;
            $__update = '';
            $new_check_in = date('Y-m-d H:i:s');
            foreach ($reservations as $reservation) {
                if ($reservation->reservation_status == 1) {
                    if (! $this->Lockedroom && $reservation->room_no != $new_room['room_id']) {
                        $new_data = [
                            'guest_id' => $reservation->guest_id,
                            'hotel_id' => $this->hotel_id,
                            'room_no' => $new_room['room_id'],
                            'check_in' => $new_check_in,
                            'check_out' => $reservation->check_out,
                            'comment' => $reservation->comment ? $reservation->comment : ' ',
                            'status' => 1,
                            'reservation_status' => 1,
                            'reservation_number' => $reservation->reservation_number,
                            'main_guest' => $main_guest,
                        ];
                        $NewReservation = GuestCheckinDetails::create($new_data);
                        if ($reservation->main_guest == '0') {
                            $main_guest = $NewReservation->sno;
                        }
                        $reservation->reservation_number = $reservation->reservation_number.'_RM';
                        $reservation->check_out = $new_check_in;
                        $reservation->status = 0;
                        $reservation->reservation_status = 3;
                        $room_old = $this->getRoom($this->hotel_id, $this->staff_id, $reservation->room_no);
                        $room_move = [
                            'guest_id' => $reservation->guest_id,
                            'current_room_no' => $room_old['room_id'],
                            'new_room_no' => $new_room['room_id'],
                            'hotel_id' => $this->hotel_id,
                            'created_by' => $this->staff_id,
                            'created_on' => date('Y-m-d H:i:s'),
                            'status' => 1,
                            'active' => 1,
                            'updated_by' => $this->staff_id,
                        ];
                        \App\Models\RoomMove::create($room_move);
                    }
                } else {
                    if ($this->Lockedroom && $reservation->room_no != $new_room->room_id) {
                        $__update .= "room_no: $reservation->room_no to $new_room->room_id, ";
                        $reservation->room_no = $new_room->room_id;
                    }
                    if ($__update) {
                        $this->saveLogTracker([
                            'hotel_id' => $this->hotel_id,
                            'module_id' => 8,
                            'action' => 'update',
                            'prim_id' => $reservation->guest_id,
                            'staff_id' => $this->staff_id,
                            'date_time' => date('Y-m-d H:i:s'),
                            'comments' => "Update Reservation sno: '.$reservation->sno.' information: $__update",
                            'type' => 'API-infor',
                        ]);
                    }
                }
                $reservation->save();
                $rooms = [];
                $rooms[] = [
                    'status' => Arr::get($NewRoom, 'HKStatus'),
                    'location' => $new_room['room'],
                ];

                $this->ChangeHousekeepingStatus($rooms);
                DB::commit();
            }

            return $this->SuccessChangeHSK();
        } catch (\Exception $e) {
            \Log::error($e);
            DB::rollback();

            return $this->ErrorXML($this->INTERNAL_SERVER);
        }
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @param  array  $rooms contiene un array con los datos de las habitaciones y sus nuevos estados
     *      cuando el tipo de mensaje es HTNG
     * Ademas se realiza el envio de una petición mediante CURL a la aplicación de nuvola con el fin de realizar el cambio de estado a una habitación
     * @return string (xml) retorna como respuesta un xml que puede ser un error o un mensaje de success
     */
    public function ChangeHousekeepingStatus($rooms)
    {
        try {
            $HousekeepingData = [];
            $HousekeepingData['hotel_id'] = $this->hotel_id;
            $HousekeepingData['staff_id'] = $this->staff_id;
            $HousekeepingData['rooms'] = [];
            foreach ($rooms as $room_data) {
                $room = $this->getRoom($this->hotel_id, $this->staff_id, $room_data['location']);
                $_d['room_id'] = $room['room_id'];
                $_d['hk_status'] = $this->HotelHousekeepingConfig[strtoupper($room_data['status'])]['codes'][0]['hk_status'];
                $HousekeepingData['rooms'][] = $_d;
            }

            if (count($HousekeepingData['rooms']) > 0) {
                $url = '';
                if (strpos(url('/'), 'api-dev') !== false) {
                    $url = 'https://dev4.mynuvola.com/index.php/housekeeping/pmsHKChange';
                } else {
                    $url = 'https://hotel.mynuvola.com/index.php/housekeeping/pmsHKChange';
                }
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 2,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => json_encode($HousekeepingData),
                ]);
                $response = curl_exec($curl);
                $err = curl_error($curl);
                curl_close($curl);
            }
            if ($err) {
                return $this->ErrorXML($this->INTERNAL_SERVER);
            }

            return $this->SuccessChangeHSK();
        } catch (\Throwable $e) {
            \Log::error($e);
            DB::rollback();

            return $this->ErrorXML($this->INTERNAL_SERVER);
        }
    }

    /**
     * @author Jose David Acevedo Camacho
     *
     * @return string (xml)
     * Se retorna la plantilla xml para una respuesta satisfactoria cuando el mensaje es de tipo HTNG
     */
    public function SuccessChangeHSK()
    {
        $success = [
            's:Header' => [
                'wsa:ReplyTo' => [
                    'wsa:Address' => 'http://www.w3.org/2005/08/addressing/none',
                    'wsa:ReferenceParameters' => [
                        'axis2:ServiceGroupId' => [
                            '_attributes' => [
                                'xmlns:axis2' => 'http://ws.apache.org/namespaces/axis2',
                            ],
                            '_value' => 'urn:uuid:9d4eeedc-d643-428e-b71f-78dc4cb389a2',
                        ],
                    ],
                ],
                'Security' => [
                    '_attributes' => [
                        'xmlns' => 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd',
                    ],
                    'UsernameToken' => [
                        'Username' => $this->credentials['user'],
                        'Password' => $this->credentials['password'],
                    ],
                ],
                'wsa:Action' => [
                    '_attributes' => [
                        's:mustUnderstand' => '1',
                    ],
                    '_value' => $this->route_action,
                ],
                '_attributes' => [
                    'xmlns:wsa' => 'http://www.w3.org/2005/08/addressing',
                ],
            ],
            's:Body' => [
                $this->route_action => [
                    'Success' => [],
                    '_attributes' => [
                        'TimeStamp' => $this->TimeStamp,
                        'Version' => '2.0',
                    ],
                ],
                '_attributes' => [
                    'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                    'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
                ],
            ],
            '_attributes' => [
                'xmlns:s' => 'http://www.w3.org/2003/05/soap-envelope',
                'xmlns:a' => 'http://www.w3.org/2005/08/addressing',
            ],
        ];

        $xml_response = new ArrayToXml($success, 's:Envelope');
        $dom = $xml_response->toDom();
        $dom->encoding = 'utf-8';
        $xml_response = $dom->saveXML();
        // $this->CurlHTNG($xml_response);
        return $xml_response;
    }

    public function CurlHTNG($xml)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->url_send,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/soap+xml; charset=utf-8',
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
    }
}
