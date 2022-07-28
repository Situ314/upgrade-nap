<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\GuestCheckinDetails;
use App\Models\GuestRegistration;
use App\Models\IntegrationsGuestInformation;
use DateTime;
use DB;
use Illuminate\Http\Request;

class ComtrolController extends Controller
{
    private $uhll;

    private $hotel_id;

    private $staff_id;

    public function __construct()
    {
        // codigo que representan en comtrol las acciones a procesar
        // Mas detalles: http://my.comtrol.com/support/uhll-specification/index.php
        $this->uhll = [
            // Tipo de mensajes,
            'type' => [
                '14' => 'checkInRoom',
                '48' => 'checkInGuest',
                '15' => 'checkOutRoom',
                '49' => 'checkOutGuest',
                '18' => 'roomMove',
                '17' => 'maidCode',
            ],
            'data_fiel' => [
                // Tipo de datos
                //001
                '001' => 'text_string',
                '005' => 'arrival_date',
                '008' => 'full_name',
                //010
                '012' => 'departure_date',
                '018' => 'business_name',
                //020
                '023' => 'zip_code',
                '025' => 'phoone_number',
                //030
                //040
                //050
                '059' => 'vip_status',
                //060
                //070
                //080
                '089' => 'restriction_limit',
                //090
                '094' => 'first_name',
                '095' => 'last_name',
                //100
                '106' => 'account_number',
                '109' => 'group_number',
                //110
                //120
                '121' => 'group_directory_status',
                //130
                //140
                '144' => 'generic_status',
                //150
                '150' => 'balance_amount',
                //160
                '163' => 'guest_id',
                '164' => 'language',
                '165' => 'new_station_number',
                '166' => 'voice_did',
                '167' => 'data_did',
                '168' => 'resync_flag',
                '169' => 'new_room_number',
                //170
                '174' => 'station_number',
                '175' => 'room_number',
                '177' => 'inhibit',
                //180
                '182' => 'password',
                '183' => 'access_level',
                '186' => 'checkout_time',
                '196' => 'date',
                '197' => 'time',
                //200
                '200' => 'rate',
                '201' => 'vendor_specific_field',
                '278' => 'arrival_time',
                //300
                '305' => 'guest_pin',
                '306' => 'movie_access',
                '307' => 'billing_access',
                '308' => 'game_access',
                '319' => 'group_name',
                '321' => 'music_preference',
                '323' => 'excluded_dmms',
                '328' => 'email_address',
            ],
        ];
    }

    /**
     * Funcion para servir datos a la integración, la configuracion asociada a la integración Nuvola --> Comtrol,
     * no utiliza este endpoint, ya que la direccion que se planteo inicialmente fue unidireccional. De comtrol a Nuvola
     */
    public function inbound(Request $request)
    {
        //\Log::info('inbound-comtrol');
        return '';
    }

    /**
     * Vea guia de desarrollo:
     * url: http://my.comtrol.com/support/gsshttp-developer/http-development.php
     * usuario: asanchez@mynuvola.com
     * password: Mynuvola2019
     */
    public function outbound(Request $request)
    {
        //\Log::info('outbound');
        //\Log::info(json_encode($request));
        // Capturar datos previo a validacion de autenticación,
        // ver el archivo AuthBasic.php
        $this->hotel_id = $request->hotel_id;
        $this->staff_id = $request->staff_id;

        $body = [];
        // El mensaje enviado, tiene en su interior un array con mensajes en UHLL
        foreach ($request->UHLL as $uhll) {
            // llamar funcion para convertir de uhll a json
            $rs = $this->processUhll($uhll);
            // dd($rs);
            if (empty($body)) {
                $body = $rs;
            } else {
                $body->sequenceNumber[] = $rs->sequenceNumber[0];
                foreach ($rs->data as $key => $value) {
                    $body->data->{"$key"} = $value;
                }
            }
            //\Log::info(json_encode($body->data));

            // los mensajes de UHLL se nevian cortados, el sequenceNumber me da informacion de la cantidad
            // de paquetes que hacen parte de un solo mensaje, el ultimo mensaje de un paquete siempre tendra como valor en el  sequenceNumbe 9999
            if ($rs->sequenceNumber[0] === '9999') {
                if (method_exists($this, $rs->action)) {
                    $method = $rs->action;
                    $this->$method($body->data);
                }
                $body = [];
            }
        }

        return '';
    }

    /**
     * Funcion que traducir de lenguaje UHLL a JSON object
     * ver mas detalles: http://my.comtrol.com/support/gsshttp-developer/http-development.php
     */
    private function processUhll($data)
    {
        try {
            // Mas detalle de la estructura de UHLL,
            // Ver: http://my.comtrol.com/support/gss-developer/downloads.php
            $__header = substr($data, 0, 15);
            $__body = substr($data, 15);

            $__messageType = substr($__header, 0, 2);
            $__DMM = substr($__header, 2, 3);
            $__reserved1 = substr($__header, 5, 1);
            $__reserved2 = substr($__header, 6, 1);
            $__transactionId = substr($__header, 7, 4);
            $__sequenceNumber = substr($__header, 11, 4);

            $body = [];
            $__index = 0;
            $__DFID = '';
            $__length = 0;
            $__data = '';

            if (isset($this->uhll['type'][$__messageType])) {
                while ($__index < strlen($__body)) {
                    $__DFID = substr($__body, $__index, 3);
                    $__length = intval(substr($__body, ($__index + 3), 3));
                    $__data = substr($__body, $__index + 6, $__length);

                    if (isset($this->uhll['data_fiel'][substr($__body, $__index, 3)])) {
                        $body[$this->uhll['data_fiel'][substr($__body, $__index, 3)]] = substr($__body, $__index + 6, $__length);
                    }

                    $__index = $__index + $__length + 6;
                }
                //\Log::info($__reserved1);
                //\Log::info($__reserved2);

                return (object) [
                    'action' => $this->uhll['type'][$__messageType],
                    'DMM' => $__DMM,
                    'reserved1' => $__reserved1,
                    'reserved2' => $__reserved2,
                    'transactionId' => $__transactionId,
                    'sequenceNumber' => [$__sequenceNumber],
                    'data' => (object) $body,
                ];
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    // Evento para realizar un checkIn, a un huesped inicial
    private function checkInRoom($data)
    {
        return $this->checkIn($data, 1);
    }

    // Evento para realizar un checkIn, a un huesped secundario
    private function checkInGuest($data)
    {
        return $this->checkIn($data, 0);
    }

    /**
     * Funcion base para realizar un checkin, utilizando por checkInRoom y checkInGuest
     */
    private function checkIn($data, $main_guest)
    {
        // iniciar transaccion, evitando que se guarde parcialmente la informacion al haber un error
        DB::beginTransaction();
        try {
            // configurar la hora a la del hotel
            $this->configTimeZone($this->hotel_id);
            $now = date('Y-m-d H:i:s');
            // Validar datos base y necesarios para poder realizar un check in,
            // un huesped y una habitación
            if (
                (isset($data->guest_id) && ! empty($data->guest_id)) && (isset($data->account_number) && ! empty($data->account_number))
            ) {
                $IntegrationsGuestInformation = IntegrationsGuestInformation::where('hotel_id', $this->hotel_id)
                    ->where('guest_number', $data->guest_id)
                    ->first();

                //  Si existe el huesped en el sistema
                if ($IntegrationsGuestInformation) {
                    $GuestRegistration = GuestRegistration::find($IntegrationsGuestInformation->guest_id);
                    if ($GuestRegistration) {
                        $update = '';
                        if (
                            (isset($data->first_name) && ! empty($data->first_name)) && ($GuestRegistration->firstname != $data->first_name)
                        ) {
                            $update .= "firstname: $GuestRegistration->firstname to $data->first_name, ";
                            $GuestRegistration->firstname = $data->first_name;
                        }

                        if (
                            (isset($data->last_name) && ! empty($data->last_name)) && ($GuestRegistration->lastname != $data->last_name)
                        ) {
                            $update .= "lastname: $GuestRegistration->lastname to $data->last_name, ";
                            $GuestRegistration->lastname = $data->last_name;
                        }

                        if (
                            (
                                (! isset($data->first_name) || empty($data->first_name)) && (! isset($data->last_name) || empty($data->last_name))) && (isset($data->full_name) && ! empty($data->full_name))
                        ) {
                            $full_name = explode(',', $data->full_name);
                            $count = count($full_name);

                            if ($count == 2) {
                                $firstname = $full_name[0];
                                if ($GuestRegistration->fistname != $firstname) {
                                    $update .= "firstname: $GuestRegistration->firstname to $firstname, ";
                                    $GuestRegistration->fistname = $firstname;
                                }

                                $lastname = $full_name[1];
                                if ($GuestRegistration->lastname != $lastname) {
                                    $update .= "lastname: $GuestRegistration->lastname to $lastname, ";
                                    $GuestRegistration->lastname = $lastname;
                                }
                            } elseif ($count == 1) {
                                $firstname = $data->full_name;
                                if ($GuestRegistration->fistname != $firstname) {
                                    $update .= "firstname: $GuestRegistration->firstname to $firstname, ";
                                    $GuestRegistration->fistname = $firstname;
                                }
                            }
                        }

                        if (
                            (isset($data->email_address) && ! empty($data->email_address)) && ($GuestRegistration->email_address != $data->email_address)
                        ) {
                            $update .= "email_address: $GuestRegistration->email_address to $data->email_address, ";
                            $GuestRegistration->email_address = $data->email_address;
                        }

                        if (
                            (isset($data->phone_number) && ! empty($data->phone_number)) && ($GuestRegistration->phone_no != $data->phone_number)
                        ) {
                            $phone_no = $data->phone_number;
                            $phone_no = str_replace(['-', '.', ' ', '(', ')', '*', '/', 'na'], '', $phone_no);
                            $update .= "phone_no: $GuestRegistration->phone_no to $data->phone_no, ";
                            $GuestRegistration->phone_no = $phone_no;
                        }

                        if (
                            (isset($data->zip_code) && ! empty($data->zip_code)) && ($GuestRegistration->zipcode != $data->zip_code)
                        ) {
                            $update .= "zipcode: $GuestRegistration->zipcode to $data->zip_code, ";
                            $GuestRegistration->zipcode = $data->zip_code;
                        }

                        $language = 'en';
                        if (isset($data->language) && ! empty($data->language)) {
                            if ($data->language == '1') {
                                $language = 'es';
                            }
                        }

                        if ($GuestRegistration->language != $language) {
                            $update .= "language: $GuestRegistration->language to $data->language, ";
                            $GuestRegistration->language = $language;
                        }

                        $guest_id = $GuestRegistration->guest_id;

                        if (! empty($update)) {
                            $GuestRegistration->save();
                            $this->saveLogTracker([
                                'module_id' => 8,
                                'action' => 'update',
                                'prim_id' => $guest_id,
                                'staff_id' => $this->staff_id,
                                'date_time' => $now,
                                'comments' => "Update Guest information: $update",
                                'hotel_id' => $this->hotel_id,
                                'type' => 'comtrol',
                            ]);
                        }
                    }
                } else {
                    // Si no existe, el huesped en el sistema
                    $firstname = '';
                    if (isset($data->first_name) && ! empty($data->first_name)) {
                        $firstname = $data->first_name;
                    }

                    $lastname = '';
                    if (isset($data->last_name) && ! empty($data->last_name)) {
                        $lastname = $data->last_name;
                    }

                    if ((! empty($firstname) && ! empty($lastname)) && (! isset($data->full_name) && ! empty($data->full_name))) {
                        $count = count(explode(',', $data->full_name));
                        if ($count == 2) {
                            $full_name = explode(',', $data->full_name);
                            $firstname = $full_name[0];
                            $lastname = $full_name[1];
                        } elseif ($count == 1) {
                            $firstname = $data->full_name;
                        }
                    }

                    $email_address = '';
                    if (isset($data->email_address) && ! empty($data->email_address)) {
                        $email_address = $data->email_address;
                    }

                    $phone_no = '';
                    if (isset($data->phone_number) && ! empty($data->phone_number)) {
                        $phone_no = $data->phone_number;
                        $phone_no = str_replace(['-', '.', ' ', '(', ')', '*', '/', 'na'], '', $phone_no);
                    }

                    $zipcode = '';
                    if (isset($data->zip_code) && ! empty($data->zip_code)) {
                        $zipcode = $data->zip_code;
                    }

                    $language = 'en';
                    if (isset($data->language) && ! empty($data->language)) {
                        if ($data->language == '1') {
                            $language = 'es';
                        }
                    }

                    $GuestRegistration = [
                        'hotel_id' => $this->hotel_id,
                        'firstname' => $firstname,
                        'lastname' => $lastname,
                        'email_address' => $email_address,
                        'phone_no' => $phone_no,
                        'address' => '',
                        'zipcode' => $zipcode,
                        'dod' => null,
                        'language' => $language,
                        'angel_status' => $this->validateAngelStatus($this->hotel_id),
                        'city' => '',
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $this->staff_id,
                        'state' => '',
                        'comment' => '',
                    ];

                    $guest_id = GuestRegistration::create($GuestRegistration)->guest_id;
                    IntegrationsGuestInformation::create([
                        'hotel_id' => $this->hotel_id,
                        'guest_id' => $guest_id,
                        'guest_number' => $data->guest_id,
                    ]);
                }

                // Buscar por numero de la reserva
                $GuestCheckinDetails = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('reservation_number', $data->account_number)->first();

                $room_no = 0;
                if (
                    (isset($data->room_number) && ! empty($data->room_number)) || (isset($data->station_number) && ! empty($data->station_number))
                ) {
                    $room_code = (isset($data->room_number) && ! empty($data->room_number)) ? $data->room_number : '';
                    if (empty($room_code)) {
                        $room_code = (isset($data->station_number) && ! empty($data->station_number)) ? $data->station_number : '';
                    }
                    if (! empty($room_code)) {
                        $room = $this->getRoom($this->hotel_id, $this->staff_id, $room_code);
                        $room_no = (int) $room['room_id'];
                    }
                }

                if ($GuestCheckinDetails) {
                    //$this->writeLog("comtrol", $this->hotel_id, "Error GuestCheckinDetails: " . json_encode($data));
                    DB::rollback();
                } else {
                    $sno = GuestCheckinDetails::create([
                        'guest_id' => $guest_id,
                        'hotel_id' => $this->hotel_id,
                        'room_no' => $room_no,
                        'check_in' => $now,
                        'check_out' => (new DateTime($data->departure_date.' '.$data->checkout_time))->format('Y-m-d H:i:s'),
                        'comment' => '',
                        'status' => 1,
                        'main_guest' => $main_guest, //default 0
                        'reservation_status' => 1, //default 0
                        'reservation_number' => $data->account_number, //default ''
                    ])->sno;

                    DB::commit();

                    $this->saveLogTracker([
                        'module_id' => 8,
                        'action' => 'add',
                        'prim_id' => $guest_id,
                        'staff_id' => $this->staff_id,
                        'date_time' => $now,
                        'comments' => "Stay $sno created",
                        'hotel_id' => $this->hotel_id,
                        'type' => 'comtrol',
                    ]);
                }
            } else {
                //$this->writeLog("comtrol", $this->hotel_id, "Record not processed: " . json_encode($data));
                DB::rollback();
            }
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error($e);
        }
    }

    // Realizar un checkout a un huesped
    private function checkOutGuest($data)
    {
        return $this->checkOut($data, true);
    }

    // Realizar un checkout a toda una habitación
    private function checkOutRoom($data)
    {
        return $this->checkOut($data, false);
    }

    private function checkOut($data, $individual)
    {
        DB::beginTransaction();
        try {
            $this->configTimeZone($this->hotel_id);
            $now = date('Y-m-d H:i:s');
            if (
                (isset($data->room_number) && ! empty($data->room_number)) || (isset($data->station_number) && ! empty($data->station_number))
            ) {
                $room_code = (isset($data->room_number) && ! empty($data->room_number)) ? $data->room_number : '';
                if (empty($room_code)) {
                    $room_code = (isset($data->station_number) && ! empty($data->station_number)) ? $data->station_number : '';
                }
                if (! empty($room_code)) {
                    $room = $this->getRoom($this->hotel_id, $this->staff_id, $room_code);
                    $room_no = (int) $room['room_id'];

                    $GuestCheckinDetails = GuestCheckinDetails::where('hotel_id', $this->hotel_id)
                        ->where('status', 1)
                        ->where(DB::raw("(DATE_FORMAT(check_out,'%Y-%m-%d'))"), date('Y-m-d'));

                    if ($individual) {
                        $full_name = $data->full_name;
                        $GuestCheckinDetails->whereHas('Guest', function ($q) use ($full_name) {
                            $q->whereRaw("'$full_name' = CONCAT(firstname,', ',lastname)");
                        });
                    }

                    $GuestCheckinDetails = $GuestCheckinDetails->get();

                    if ($GuestCheckinDetails) {
                        $GuestCheckinDetails->status = 0;
                        $GuestCheckinDetails->reservation_status = 3;
                        $GuestCheckinDetails->check_out = $now;

                        // $GuestCheckinDetails->update([
                        //     'status'                => 0,
                        //     'reservation_status'    => 3,
                        //     'check_out'             => $now
                        // ]);
                        DB::commit();
                    }
                } else {
                    //$this->writeLog("comtrol", $this->hotel_id, "No room: " . json_encode($data));
                    DB::rollback();
                }
            } else {
                //$this->writeLog("comtrol", $this->hotel_id, "Error checkOut: " . json_encode($data));
                DB::rollback();
            }
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error($e);
        }
    }

    public function RoomMove($data)
    {
        $room_code = (isset($data->room_number) && ! empty($data->room_number)) ? $data->room_number : '';
        if (empty($room_code)) {
            $room_code = (isset($data->station_number) && ! empty($data->station_number)) ? $data->station_number : '';
        }

        $new_room_code = (isset($data->new_room_number) && ! empty($data->new_room_number)) ? $data->new_room_number : '';
        if (empty($room_code)) {
            $new_room_code = (isset($data->new_station_number) && ! empty($data->new_station_number)) ? $data->new_station_number : '';
        }

        if (! empty($room_code) && ! empty($new_room_code)) {
            $room = $this->getRoom($this->hotel_id, $this->staff_id, $new_room_code);
            $account_number = $data->account_number;
            $full_name = $data->full_name;
            $reservation = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('reservation_number', $account_number)->where('status', 1)->where('main_guest', 1)->first();
            if ($reservation) {
                $guest = GuestRegistration::find($reservation->guest_id);
                $reservation_full_name = $guest->firstname.' '.$guest->lastname;

                if ($reservation_full_name === $full_name) {
                    return $this->RoomMoveMain($room['room_id'], $reservation);
                }
                $sno = $reservation->sno;
                $reservations = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('reservation_number', $account_number)->where('status', 1)->where('main_guest', $sno)->get();
                foreach ($reservations as $reservation) {
                    $guest = GuestRegistration::find($reservation->guest_id);
                    $reservation_full_name = $guest->firstname.' '.$guest->lastname;

                    if ($reservation_full_name === $full_name) {
                        $resp = $this->RoomMoveNoMain($room['room_id'], $reservation, $sno);
                        if ($resp) {
                            return $resp;
                        }
                    }
                }
            }
        }
    }

    // Validar si se movera un huesped
    public function RoomMoveMain($room_id, $reservation)
    {
        DB::beginTransaction();
        try {
            $now = date('Y-m-d H:m:s');
            $new_reservation = [
                'guest_id' => $reservation->guest_id,
                'hotel_id' => $this->hotel_id,
                'room_no' => $room_id,
                'check_in' => $now,
                'check_out' => $reservation->check_out,
                'comment' => '',
                'status' => 1,
                'main_guest' => 0,
                'reservation_status' => 1, //default 0
                'reservation_number' => $reservation->reservation_number, //default ''
            ];

            $reservation->status = 0;
            $reservation->reservation_status = 3;
            $reservation->check_out = $now;
            $reservation->reservation_number = $reservation->reservation_number.'_RM';
            $reservation->save();

            $new_reservation = GuestCheckinDetails::create($new_reservation);

            $reservations = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('reservation_number', $reservation->reservation_number)
                ->where('status', 1)->where('main_guest', $reservation->sno)->get();

            $this->RegisterRoomMove($reservation, $new_reservation);
            foreach ($reservations as $_reservation) {
                $reservations->main_guest = $new_reservation->sno;
                $reservations->save();
            }
            DB::commit();

            return true;
        } catch (Exception $e) {
            DB::rollback();

            return false;
        }
    }

    // Validar si se movera todos los huesped de una habitación
    public function RoomMoveNoMain($room_id, $reservation, $sno)
    {
        DB::beginTransaction();
        try {
            $now = date('Y-m-d H:m:s');
            $new_reservation = [
                'guest_id' => $reservation->guest_id,
                'hotel_id' => $this->hotel_id,
                'room_no' => $room_id,
                'check_in' => $now,
                'check_out' => $reservation->check_out,
                'comment' => '',
                'status' => 1,
                'main_guest' => $sno,
                'reservation_status' => 1, //default 0
                'reservation_number' => $reservation->reservation_number, //default ''
            ];

            $reservation->status = 0;
            $reservation->reservation_status = 3;
            $reservation->check_out = $now;
            $reservation->reservation_number = $reservation->reservation_number.'_RM';
            $reservation->save();

            $new_reservation = GuestCheckinDetails::create($new_reservation);

            $this->RegisterRoomMove($reservation, $new_reservation);
            DB::commit();

            return true;
        } catch (Exception $e) {
            DB::rollback();

            return false;
        }
    }

    public function RegisterRoomMove($reservation, $new_reservation)
    {
        $room_move = [
            'guest_id' => $reservation->guest_id,
            'current_room_no' => $reservation->room_no,
            'new_room_no' => $new_reservation['room_no'],
            'hotel_id' => $this->hotel_id,
            'created_by' => $this->staff_id,
            'created_on' => date('Y-m-d H:i:s'),
            'status' => 1,
            'active' => 1,
            'updated_by' => $this->staff_id,
        ];
        \App\Models\RoomMove::create($room_move);
    }

    public function maidCode($data)
    {
        //\Log::info('HSK Comtrol');
        //\Log::info(json_encode($data));
    }
}
