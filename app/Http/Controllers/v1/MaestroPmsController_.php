<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\CompanyIntegration;
use App\Models\GuestCheckinDetails;
use App\Models\GuestRegistration;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\HotelRoomsOut;
use App\Models\HotelRoomTypes;
use App\Models\HousekeepingCleanings;
use App\Models\HousekeepingReasons;
use App\Models\MaestroPmsSalt;
use App\Models\RoomMove;
use DateTime;
use DB;
use Illuminate\Http\Request;
use Spatie\ArrayToXml\ArrayToXml;

class MaestroPmsController extends Controller
{
    private $hsk_status = null;

    public function index(Request $request)
    {
        try {

            // Convert XML to JSON

            $xml = simplexml_load_string($request->getContent());
            $str_json = json_encode($xml);
            $json = json_decode($str_json);

            // Search in the records where the company is equal 'Maestro pms' then filter
            // by hotel_id in the field Sync
            $company = CompanyIntegration::where(function ($query) {
                $query
                    ->where('int_id', 1)
                    ->where('state', 1);
            })
            ->get();

            $company = $company->where('config.hotel_id', $json->HotelId)->first();

            if ($company) {
                $hotel_id = $company->hotel_id;
            }

            //$this->writeLog("maestro_pms_data", $hotel_id, $request->getContent());

            // Validate if HotelId is active in anything records of the integrations
            $status = 'failure';
            $message = '';
            if ($company) {
                $hotel_id = $company['hotel_id'];
                $user_id = $company['created_by'];
                $auk = $company['config']['agreed_upon_key'];

                // Validate a GetSalt is requested
                if (isset($json->GetSalt)) {
                    $salt = $this->getSalt($hotel_id);
                    $result = ArrayToXml::convert([
                        'HotelId' => $json->HotelId,
                        'Salt' => $salt,
                    ], 'Response');

                    return response($result, 200)->header('Content-Type', 'text/xml');
                } else {
                    // else, is a normal request
                    $haveAccess = $this->validatePasswordHash($hotel_id, $json->PasswordHash, $auk);

                    //Validate PasswordHash, if result == true, the request is valid
                    if ($haveAccess['result']) {
                        if (method_exists($this, $json->Action)) {
                            if (isset($company['config']['housekeeping'])) {
                                $this->hsk_status = $company['config']['housekeeping'];
                            }

                            $method = $json->Action;

                            if ($this->$method($hotel_id, $user_id, $json)) {
                                $status = 'Success';
                            }

                            $result = ArrayToXml::convert([
                                'HotelId' => $json->HotelId,
                                'PasswordHash' => $json->PasswordHash,
                                'Status' => $status,
                                'Message' => '',
                            ], 'Response');

                            return response($result, 200)->header('Content-Type', 'text/xml');
                        }
                    } else {
                        /**
                         * else, the respons is a error
                         */
                        $result = ArrayToXml::convert([
                            'HotelId' => $json->HotelId,
                            'PasswordHash' => $json->PasswordHash,
                            'Status' => 'failure',
                            'Message' => $haveAccess['error'],
                        ], 'Response');

                        return response($result, 200)->header('Content-Type', 'text/xml');
                    }
                }
            } else {
                if (isset($json->PasswordHash)) {
                    $result = ArrayToXml::convert([
                        'HotelId' => $json->HotelId,
                        'PasswordHash' => $json->PasswordHash,
                        'Status' => 'failure',
                        'Message' => 'There is no active integration in this hotel',
                    ], 'Response');
                } else {
                    $result = ArrayToXml::convert([
                        'Status' => 'failure',
                        'Message' => 'Inactive integration',
                    ], 'Response');
                }

                return response($result, 200)->header('Content-Type', 'text/xml');
            }
        } catch (\Exception $e) {
            //$this->writeLog("maestro_pms_error", null , "Error in index: ".$e);
            $success = false;
        }
    }

    private function ReservationList($hotel_id, $user_id, $data)
    {
        try {
            $reservation_data = [];
            $is_array = is_array($data->Reservations->ReservationData);

            if ($is_array) {
                $reservation_data = $data->Reservations->ReservationData;
            } else {
                $reservation_data[] = $data->Reservations->ReservationData;
            }

            foreach ($reservation_data as $data) {
                switch ($data->ReservationStatus) {
                    case 'reserved':
                        /**
                         * @hotel_id            int
                         * @guest_id            int
                         * @data_information    object
                         * @reservation_status  boolean [ 0 => reserved, 1 => checked_in, 2 => cancelled, 3 => checked_out ]
                         */
                        $this->CheckInReservationList($hotel_id, $user_id, $data, 0);
                        break;
                    case 'checked_in':
                        /**
                         * @hotel_id            int
                         * @guest_id            int
                         * @data_information    object
                         * @reservation_status  boolean [ 0 => reserved, 1 => checked_in, 2 => cancelled, 3 => checked_out ]
                         */
                        $this->CheckInReservationList($hotel_id, $user_id, $data, 1);
                        break;
                    case 'cancelled':
                        /**
                         * @hotel_id            int
                         * @guest_id            int
                         * @data_information    object
                         * @reservation_status  boolean [ 0 => reserved, 1 => checked_in, 2 => cancelled, 3 => checked_out ]
                         */
                        $this->CheckOutReservationList($hotel_id, $user_id, $data, 2);
                        break;
                    case 'checked_out':
                        /**
                         * @hotel_id            int
                         * @guest_id            int
                         * @data_information    object
                         * @reservation_status  boolean [ 0 => reserved, 1 => checked_in, 2 => cancelled, 3 => checked_out ]
                         */
                        $this->CheckOutReservationList($hotel_id, $user_id, $data, 3);
                        break;
                }
            }
            $success = true;
        } catch (\Exception $e) {
            //$this->writeLog("maestro_pms", $hotel_id, "Error in reservation list function: ".$e);
            $success = false;
        }

        return $success;
    }

    private function CheckInReservationList($hotel_id, $user_id, $data, $reservation_status)
    {
        $success = false;
        try {
            //$this->writeLog("maestro_pms", $hotel_id, "CheckInReservationList Start: $reservation_status:: Data::".json_encode($data));

            $phone = $this->proccessString(isset($data->Cell) ? $data->Cell : (isset($data->Phone) ? $data->Phone : ''), ['replace' => ['-', '.'], 'by' => '']);
            $comment = '';
            if (isset($data->ReservationText) && isset($data->ReservationText->Text)) {
                $arr_comment = [];
                if (is_array($data->ReservationText->Text)) {
                    $arr_comment = $data->ReservationText->Text;
                } else {
                    $arr_comment[] = $data->ReservationText->Text;
                }

                foreach ($arr_comment as $key => $value) {
                    $comment .= (is_string($value) && ! empty($value)) ? "$value " : '';
                }
                if (! empty($comment)) {
                    $comment = substr($comment, 0, 250);
                }
            }

            $this->configTimeZone($hotel_id);
            $now = date('Y-m-d H:i:s');
            $guest_registration = [
                'hotel_id' => $hotel_id,
                'is_active' => 1,
                'lastname' => $this->proccessString(isset($data->LastName) ? $data->LastName : ''),
                'firstname' => $this->proccessString(isset($data->FirstName) ? $data->FirstName : ''),
                'zipcode' => $this->proccessString(isset($data->ZipCode) ? $data->ZipCode : ''),
                'email_address' => $this->proccessString(isset($data->EmailAddress) ? (filter_var($data->EmailAddress, FILTER_VALIDATE_EMAIL) ? $data->EmailAddress : '') : ''),
                'city' => $this->proccessString(isset($data->Country) ? $data->Country : ''),
                'phone_no' => ! empty($phone) ? "+$phone" : '',
                'comment' => $comment,
                'created_on' => $now,
                'created_by' => $user_id,
                'updated_on' => null,
                'updated_by' => null,
                'id_device' => null,
                'dob' => null,
                'language' => '',
                'address' => '',
                'state' => '',
                'angel_status' => $this->validateAngelStatus($hotel_id),
            ];

            $reservation_number_key = '';

            if (isset($data->ReservationNumberKey) && is_string($data->ReservationNumberKey) && ! empty($data->ReservationNumberKey)) {
                $reservation_number_key = $data->ReservationNumberKey;
            }

            //Validar si ya existe el huesped usando el numero de reserva
            if (! empty($reservation_number_key)) {
                $guest_checkin_details = GuestCheckinDetails::where('hotel_id', $hotel_id)
                ->where('reservation_number', $reservation_number_key)
                ->first();

                if ($guest_checkin_details) {
                    $guest_id = $guest_checkin_details->guest_id;
                    $guest_registration_find = GuestRegistration::find($guest_id);
                } else {
                    //para los registros anteriores que no guaradaban el numero de reserva, se busca por nombre del huesped
                    $guest_registration_find = GuestRegistration::where('hotel_id', $hotel_id)
                    ->where('lastname', $guest_registration['lastname'])
                    ->where('firstname', $guest_registration['firstname'])
                    ->first();
                }
            } else {
                $guest_registration_find = GuestRegistration::where('hotel_id', $hotel_id)
                ->where('lastname', $guest_registration['lastname'])
                ->where('firstname', $guest_registration['firstname'])
                ->first();
            }

            DB::beginTransaction();
            //Actualizar o crear el huesped, y capturar el guest_id

            if ($guest_registration_find) {
                $guest_registration['created_on'] = $guest_registration_find->created_on;
                $guest_registration['created_by'] = $guest_registration_find->created_by;
                $guest_registration['updated_on'] = $now;
                $guest_registration['created_by'] = $user_id;

                $guest_registration_find->fill($guest_registration);
                $guest_registration_find->save();
                $guest_id = $guest_registration_find->guest_id;

                $this->saveLogTracker([
                    'module_id' => 8,
                    'action' => 'update',
                    'prim_id' => $guest_id,
                    'staff_id' => $user_id,
                    'date_time' => $now,
                    'comments' => 'Update Guest information',
                    'hotel_id' => $hotel_id,
                    'type' => 'API-maestro_pms',
                ]);
            } else {
                $guest_id = GuestRegistration::create($guest_registration)->guest_id;
                $this->saveLogTracker([
                    'module_id' => 8,
                    'action' => 'add',
                    'prim_id' => $guest_id,
                    'staff_id' => $user_id,
                    'date_time' => $now,
                    'comments' => 'Add Guest information',
                    'hotel_id' => $hotel_id,
                    'type' => 'API-maestro_pms',
                ]);
            }

            //Preparar informacion para la tabla guest_checkin_details
            $room_no = 0;

            //Validar si el registro tienen una habitacion relacioanda
            if (isset($data->Room) && isset($data->Room->RoomCode) && is_string($data->Room->RoomCode) && ! empty($data->Room->RoomCode)) {
                $room_code = $data->Room->RoomCode;
                $room = $this->findRoomId($hotel_id, $user_id, $room_code);
                $room_no = (int) $room['room_id'];
            }

            $check_in = ( new DateTime($data->ArrivalDate) )->format('Y-m-d H:i:s');
            $check_out = ( new DateTime($data->DepartureDate) )->format('Y-m-d H:i:s');

            $guest_checkin_details = [
                'guest_id' => $guest_id,
                'hotel_id' => $hotel_id,
                'room_no' => $room_no,
                'check_in' => $check_in,
                'check_out' => $check_out,
                'status' => (($reservation_status == 0 && $room_no == 0) ? -1 : 1),
                'comment' => '',
                'main_guest' => 0,
                'reservation_status' => $reservation_status,
                'reservation_number' => $reservation_number_key,
            ];

            $find_guest_checkin_details = GuestCheckinDetails::where('hotel_id', $hotel_id)
                ->where('reservation_number', $reservation_number_key)
                ->first();

            if ($reservation_status == 0) {
                if ($find_guest_checkin_details) {
                    //$this->writeLog("maestro_pms", $hotel_id, "CheckInReservationList new: ".json_encode($guest_checkin_details));
                    //$this->writeLog("maestro_pms", $hotel_id, "CheckInReservationList old: ".json_encode($find_guest_checkin_details));

                    $sno = $find_guest_checkin_details->sno;
                    $find_guest_checkin_details->fill($guest_checkin_details);
                    $find_guest_checkin_details->save();

                    //$this->writeLog("maestro_pms", $hotel_id, "CheckInReservationList #1-1 updated: guest_id: $guest_id, sno: $sno");

                    $success = true;
                    $this->saveLogTracker([
                        'module_id' => 8,
                        'action' => 'update',
                        'prim_id' => $guest_id,
                        'staff_id' => $user_id,
                        'date_time' => date('Y-m-d H:i:s'),
                        'comments' => "Stay $sno updated",
                        'hotel_id' => $hotel_id,
                        'type' => 'API-maestro_pms',
                    ]);
                } else {
                    $sno = GuestCheckinDetails::create($guest_checkin_details)->sno;
                    //$this->writeLog("maestro_pms", $hotel_id, "CheckInReservationList #1 create: guest_id: $guest_id, sno: $sno");
                    $success = true;
                    $this->saveLogTracker([
                        'module_id' => 8,
                        'action' => 'add',
                        'prim_id' => $guest_id,
                        'staff_id' => $user_id,
                        'date_time' => date('Y-m-d H:i:s'),
                        'comments' => "Stay $sno created",
                        'hotel_id' => $hotel_id,
                        'type' => 'API-maestro_pms',
                    ]);
                }
                DB::commit();
            } else {
                $main_guest = 0;
                // validar si es el primer registro, para colocarlo como titular de la Habitación,
                // Se busca todos los registros activos en el rango de fecha estipulado,
                // Si el resultado es 0 quiere decir que es el primer registro y por lo tanto es el titular de la estadia
                $range = GuestCheckinDetails::where('status', 1)
                ->where('room_no', $room_no)
                ->where(function ($query) use ($check_in, $check_out) {
                    $query
                        ->whereRaw("'$check_in' BETWEEN check_in and check_out")
                        ->orWhereRaw("'$check_out' BETWEEN check_in and check_out");
                })->get();

                if (count($range) == 0) {
                    $main_guest = 1;
                }

                $guest_checkin_details['main_guest'] = $main_guest;
                $guest_checkin_details['status'] = 1;

                $send_email = false;

                if ($find_guest_checkin_details) {
                    $current_room = (int) $find_guest_checkin_details->room_no;
                    $new_room = (int) $guest_checkin_details['room_no'];

                    $sno = $find_guest_checkin_details->sno;
                    $old_status = $find_guest_checkin_details->reservation_statu;
                    if ($old_status == 0) {
                        $send_email = true;
                    }

                    $find_guest_checkin_details->fill($guest_checkin_details);
                    $find_guest_checkin_details->save();

                    if ($current_room != $new_room && $current_room > 0) {

                        // $_current_room  = HotelRoom::find($current_room)->location;
                        // $_new_room = 'NO ROOM';
                        // if($new_room > 0) {
                        //     $_new_room      = HotelRoom::find($new_room)->location;
                        // }

                        if ($old_status == 1) {
                            RoomMove::create([
                                'guest_id' => $guest_id,
                                'phone' => '',
                                'current_room_no' => $current_room,
                                'new_room_no' => $new_room,
                                'comment' => '',
                                'hotel_id' => $hotel_id,
                                'created_by' => $user_id,
                                'created_on' => date('Y-m-d H:i:s'),
                                'updated_by' => 0,
                                'updated_on' => null,
                            ]);
                        }
                    }

                    $success = true;
                    //$this->writeLog("maestro_pms", $hotel_id, "CheckInReservationList Update guest_checkin_details: guest_id: $guest_id, sno: $sno");
                    $this->saveLogTracker([
                        'hotel_id' => $hotel_id,
                        'staff_id' => $user_id,
                        'prim_id' => $guest_id,
                        'module_id' => 8,
                        'action' => 'update',
                        'date_time' => $now,
                        'comments' => "Update Stay, reservation to check in. sno: $sno",
                        'type' => 'API-maestro_pms',
                    ]);
                    DB::commit();
                } else {
                    $sno = GuestCheckinDetails::create($guest_checkin_details)->sno;
                    //$this->writeLog("maestro_pms", $hotel_id, "CheckInReservationList #2 create: guest_id: $guest_id, sno: $sno");
                    $success = true;
                    $this->saveLogTracker([
                        'module_id' => 8,
                        'action' => 'add',
                        'prim_id' => $guest_id,
                        'staff_id' => $user_id,
                        'date_time' => date('Y-m-d H:i:s'),
                        'comments' => "Stay $sno created",
                        'hotel_id' => $hotel_id,
                        'type' => 'API-maestro_pms',
                    ]);
                    DB::commit();
                    $send_email = true;
                }

                if ($send_email) {
                    if (! empty($guest_registration['email_address'])) {
                        $rs = $this->sendAngelInvitation($guest_registration['email_address'], $hotel_id, $guest_registration['phone_no']);
                        //$this->writeLog("maestro_pms", $hotel_id, "   Send angel invitation 1 rs: ".json_encode($rs));
                    }
                }
            }
        } catch (\Exception $e) {
            $success = false;
            DB::rollback();
            //$this->writeLog("maestro_pms", $hotel_id, "CheckInReservationList Error: ".$e);
        }

        return $success;
    }

    public function CheckOutReservationList($hotel_id, $user_id, $data, $reservation_status)
    {
        $room_code = '';
        if (isset($data->Room) && isset($data->Room->RoomCode) && is_string($data->Room->RoomCode) && ! empty($data->Room->RoomCode)) {
            $room_code = $data->Room->RoomCode;
        }

        $FirstName = '';
        if (isset($data->FirstName) && is_string($data->FirstName) && ! empty($data->FirstName)) {
            $FirstName = $data->FirstName;
        }

        $LastName = '';
        if (isset($data->LastName) && is_string($data->LastName) && ! empty($data->LastName)) {
            $LastName = $data->LastName;
        }

        $EmailAddress = '';
        if (isset($data->EmailAddress) && is_string($data->EmailAddress) && ! empty($data->EmailAddress)) {
            $EmailAddress = $data->EmailAddress;
        }

        $ArrivalDate = '';
        if (isset($data->ArrivalDate) && is_string($data->ArrivalDate) && ! empty($data->ArrivalDate)) {
            $ArrivalDate = $data->ArrivalDate;
        }

        $DepartureDate = '';
        if (isset($data->DepartureDate) && is_string($data->DepartureDate) && ! empty($data->DepartureDate)) {
            $DepartureDate = $data->DepartureDate;
        }

        $ReservationNumberKey = '';
        if (isset($data->ReservationNumberKey) && is_string($data->ReservationNumberKey) && ! empty($data->ReservationNumberKey)) {
            $ReservationNumberKey = $data->ReservationNumberKey;
        }

        $check_out =
        (object)
        [
            (object)
            [
                'FirstName' => $FirstName,
                'LastName' => $LastName,
                'EmailAddress' => $EmailAddress,
                'ArrivalDate' => $ArrivalDate,
                'DepartureDate' => $DepartureDate,
                'ReservationNumberKey' => $ReservationNumberKey,
                'RoomCode' => $room_code,
            ],
        ];

        $success = $this->CheckOut($hotel_id, $user_id, $check_out, $reservation_status);

        return $success;
    }

    public function CheckOut($hotel_id, $user_id, $checkOut, $reservation_status = 3)
    {
        $check_out = $checkOut;
        if (isset($checkOut->CheckOutData)) {
            $check_out = $checkOut->CheckOutData->GuestInfo;
        }
        $this->configTimeZone($hotel_id);

        foreach ($check_out as $data) {
            try {
                DB::beginTransaction();

                //$this->writeLog("maestro_pms", $hotel_id, "Data CheckOut:: ".json_encode($data));

                $email_address = $this->proccessString(isset($data->EmailAddress) ? $data->EmailAddress : '');
                $lastname = $this->proccessString(isset($data->LastName) ? $data->LastName : '');
                $firstname = $this->proccessString(isset($data->FirstName) ? $data->FirstName : '');
                $RoomCode = $this->proccessString(isset($data->RoomCode) ? $data->RoomCode : '');
                $reservation_number_key = $this->proccessString(isset($data->ReservationNumberKey) ? $data->ReservationNumberKey : '');

                if (empty($roomCode)) {
                    $room_no = 0;
                } else {
                    $room = HotelRoom::where('hotel_id', $hotel_id)
                    ->where('location', $RoomCode)
                    ->get();

                    $room_no = 0;

                    if ($room) {
                        $room_no = $room->room_id;
                    }
                }

                $now = date('Y-m-d H:i:s');

                $guest_checkin_details = GuestCheckinDetails::where('hotel_id', $hotel_id)
                ->where('reservation_number', $reservation_number_key)
                ->first();

                if ($guest_checkin_details) {
                    $guest_checkin_details->check_out = $now;
                    $guest_checkin_details->status = 0;
                    $guest_checkin_details->reservation_status = $reservation_status;
                    $guest_checkin_details->save();

                    //$this->writeLog("maestro_pms", $hotel_id, "Check out: guest_id: $guest_checkin_details->guest_id, sno: $guest_checkin_details->sno");
                    DB::commit();
                    $success = true;
                } else {
                    $guest = GuestRegistration::select(['guest_id'])
                    ->where('email_address', $email_address)
                    ->Where('lastname', $lastname)
                    ->where('firstname', $firstname)
                    ->first();

                    if ($guest) {
                        $guest_id = $guest->guest_id;
                        $check_in = ( new DateTime($data->ArrivalDate) )->format('Y-m-d');
                        $check_out = ( new DateTime($data->DepartureDate) )->format('Y-m-d');

                        $guest_checkin_details = GuestCheckinDetails::where('status', 1)
                        ->where('hotel_id', $hotel_id)
                        ->where('guest_id', $guest_id)
                        ->where('reservation_status', ($reservation_status == 2 ? 0 : 1))
                        ->where(function ($query) use ($check_in, $check_out) {
                            $query
                                ->where(DB::raw("(DATE_FORMAT(check_in,'%Y-%m-%d'))"), $check_in)
                                ->orWhere(DB::raw("(DATE_FORMAT(check_out,'%Y-%m-%d'))"), $check_out);
                        })
                        ->first();

                        if ($guest_checkin_details) {
                            $guest_checkin_details->check_out = $now;
                            $guest_checkin_details->status = 0;
                            $guest_checkin_details->reservation_status = $reservation_status;
                            $guest_checkin_details->save();

                            //$this->writeLog("maestro_pms", $hotel_id, "Check out: guest_id: $guest_checkin_details->guest_id, sno: $guest_checkin_details->sno");

                            DB::commit();
                            $success = true;
                        } else {
                            //$this->writeLog("maestro_pms", $hotel_id, "Check out record not found");
                            DB::rollback();
                            $success = false;
                        }
                    } else {
                        //$this->writeLog("maestro_pms", $hotel_id, "Check out guest not found");
                        $success = false;
                        DB::rollback();
                    }
                }
            } catch (\Exception $e) {
                $success = false;
                DB::rollback();
                //$this->writeLog("maestro_pms", $hotel_id, "Check out error: $e");
            }
        }

        return $success;
    }

    public function CheckIn($hotel_id, $user_id, $data)
    {
        try {
            $arr_check_in = [];
            if (is_array($data->CheckInData->GuestInfo)) {
                $arr_check_in = $data->CheckInData->GuestInfo;
            } else {
                $arr_check_in[] = $data->CheckInData->GuestInfo;
            }

            //$arr_check_in = $this->unique_inArray($hotel_id, $arr_check_in);

            foreach ($arr_check_in as $data) {

                //$this->writeLog("maestro_pms", $hotel_id, "CheckIn Start::".json_encode(($data)));

                // Preparar información para la tabla guest_registration
                $phone = $this->proccessString(isset($data->Cell) ? $data->Cell : '', ['replace' => ['-', '.'], 'by' => '']);
                $comment = '';
                if (isset($data->ReservationText) && isset($data->ReservationText->Text)) {
                    $arr_comment = [];
                    if (is_array($data->ReservationText->Text)) {
                        $arr_comment = $data->ReservationText->Text;
                    } else {
                        $arr_comment[] = $data->ReservationText->Text;
                    }

                    foreach ($arr_comment as $key => $value) {
                        $comment .= (is_string($value) && ! empty($value)) ? "$value " : '';
                    }
                    if (! empty($comment)) {
                        $comment = substr($comment, 0, 250);
                    }
                }
                $this->configTimeZone($hotel_id);
                $now = date('Y-m-d H:i:s');
                $guest_registration = [
                    'hotel_id' => $hotel_id,
                    'is_active' => 1,
                    'lastname' => $this->proccessString(isset($data->LastName) ? $data->LastName : ''),
                    'firstname' => $this->proccessString(isset($data->FirstName) ? $data->FirstName : ''),
                    'zipcode' => $this->proccessString(isset($data->ZipCode) ? $data->ZipCode : ''),
                    'email_address' => $this->proccessString(isset($data->EmailAddress) ? $data->EmailAddress : ''),
                    'city' => $this->proccessString(isset($data->Country) ? $data->Country : ''),
                    'phone_no' => ! empty($phone) ? "+$phone" : '',
                    'comment' => $comment,
                    'created_on' => $now,
                    'created_by' => $user_id,
                    'updated_on' => null,
                    'updated_by' => null,
                    'id_device' => null,
                    'dob' => null,
                    'language' => '',
                    'address' => '',
                    'state' => '',
                    'angel_status' => $this->validateAngelStatus($hotel_id),
                ];

                $reservation_number_key = '';

                if (isset($data->ReservationNumberKey) && is_string($data->ReservationNumberKey) && ! empty($data->ReservationNumberKey)) {
                    $reservation_number_key = $data->ReservationNumberKey;
                }

                //Validar si ya existe el huesped
                if (! empty($reservation_number_key)) {
                    $get_reservation_number = GuestCheckinDetails::where('hotel_id', $hotel_id)
                    ->where('reservation_number', $reservation_number_key)
                    ->where('status', 1)
                    ->first();

                    if ($get_reservation_number) {
                        $guest_id = $get_reservation_number->guest_id;
                        $guest_registration_find = GuestRegistration::find($guest_id);
                    } else {
                        $guest_registration_find = GuestRegistration::where('hotel_id', $hotel_id)
                        ->where('lastname', $guest_registration['lastname'])
                        ->where('firstname', $guest_registration['firstname'])
                        ->where('is_active', 1)
                        ->first();
                    }
                } else {
                    $guest_registration_find = GuestRegistration::where('hotel_id', $hotel_id)
                    ->where('lastname', $guest_registration['lastname'])
                    ->where('firstname', $guest_registration['firstname'])
                    ->where('is_active', 1)
                    ->first();
                }

                DB::beginTransaction();
                //Actualizar o crear el huesped, y capturar el guest_id
                if ($guest_registration_find) {
                    $guest_registration['created_on'] = $guest_registration_find->created_on;
                    $guest_registration['created_by'] = $guest_registration_find->created_by;
                    $guest_registration['updated_on'] = $now;
                    $guest_registration['created_by'] = $user_id;

                    $guest_registration_find->fill($guest_registration);
                    $guest_registration_find->save();
                    $guest_id = $guest_registration_find->guest_id;

                    $this->saveLogTracker([
                        'module_id' => 8,
                        'action' => 'update',
                        'prim_id' => $guest_id,
                        'staff_id' => $user_id,
                        'date_time' => $now,
                        'comments' => 'Update Guest information',
                        'hotel_id' => $hotel_id,
                        'type' => 'API-maestro_pms',
                    ]);
                } else {
                    $guest_id = GuestRegistration::create($guest_registration)->guest_id;
                    $this->saveLogTracker([
                        'module_id' => 8,
                        'action' => 'add',
                        'prim_id' => $guest_id,
                        'staff_id' => $user_id,
                        'date_time' => $now,
                        'comments' => 'Add Guest information',
                        'hotel_id' => $hotel_id,
                        'type' => 'API-maestro_pms',
                    ]);
                }

                //Preparar informacion para la tabla guest_checkin_details
                $room_no = 0;

                //Validar si el registro tienen una habitacion relacioanda
                if (isset($data->RoomCode) && is_string($data->RoomCode) && ! empty($data->RoomCode)) {
                    $room_code = $data->RoomCode;
                    $room = $this->findRoomId($hotel_id, $user_id, $room_code);
                    $room_no = (int) $room['room_id'];
                }

                $check_in = ( new DateTime($data->ArrivalDate) )->format('Y-m-d H:i:s');
                $check_out = ( new DateTime($data->DepartureDate) )->format('Y-m-d H:i:s');

                $guest_checkin_details = [
                    'guest_id' => $guest_id,
                    'hotel_id' => $hotel_id,
                    'room_no' => $room_no,
                    'check_in' => $check_in,
                    'check_out' => $check_out,
                    'status' => 1,
                    'comment' => '',
                    'main_guest' => 0,
                    'reservation_status' => 1,
                    'reservation_number' => $reservation_number_key,
                ];

                if ($room_no > 0) {
                    $main_guest = 0;
                    // validar si es el primer registro, para colocarlo como titular de la Habitación,
                    // Se busca todos los registros activos en el rango de fecha estipulado,
                    // Si el resultado es 0 quiere decir que es el primer registro y por lo tanto es el titular de la estadia.

                    $range = GuestCheckinDetails::where('status', 1)
                    ->where('room_no', $room_no)
                    ->where(function ($query) use ($check_in, $check_out) {
                        $query
                            ->whereRaw("'$check_in' BETWEEN check_in and check_out")
                            ->orWhereRaw("'$check_out' BETWEEN check_in and check_out");
                    })
                    ->get();

                    if (count($range) == 0) {
                        $main_guest = 1;
                    }
                    $guest_checkin_details['main_guest'] = $main_guest;

                    $find_guest_checkin_details = GuestCheckinDetails::where('hotel_id', $hotel_id)
                    ->where('reservation_number', $reservation_number_key)
                    ->first();

                    if ($find_guest_checkin_details) {
                        $find_guest_checkin_details = GuestCheckinDetails::select(['guest_id', 'sno', 'check_in', 'check_out'])
                        ->where('hotel_id', $hotel_id)
                        ->where('guest_id', $guest_id)
                        ->where(function ($query) use ($check_in, $check_out) {
                            $query
                            ->whereRaw("'$check_in' BETWEEN check_in and check_out")
                            ->orWhereRaw("'$check_out' BETWEEN check_in and check_out");
                        })
                        ->orderBy('sno', 'DESC')
                        ->first();
                    }

                    $send_email = false;

                    if ($find_guest_checkin_details) {
                        $sno = $find_guest_checkin_details->sno;
                        if ($find_guest_checkin_details->reservation_status = 0) {
                            $send_email = true;
                        }
                        $find_guest_checkin_details->fill($guest_checkin_details);
                        $find_guest_checkin_details->save();
                        $success = true;
                        //$this->writeLog("maestro_pms", $hotel_id, "CheckIn Update guest_checkin_details: guest_id: $guest_id, sno: $sno");
                        $this->saveLogTracker([
                            'hotel_id' => $hotel_id,
                            'staff_id' => $user_id,
                            'prim_id' => $guest_id,
                            'module_id' => 8,
                            'action' => 'update',
                            'date_time' => $now,
                            'comments' => "Update Stay, reservation to check in. sno: $sno",
                            'type' => 'API-maestro_pms',
                        ]);
                        DB::commit();
                    } else {
                        $sno = GuestCheckinDetails::create($guest_checkin_details)->sno;
                        $success = true;
                        //$this->writeLog("maestro_pms", $hotel_id, "CheckIn Save => guest_id: $guest_id, sno: $sno");
                        $this->saveLogTracker([
                            'module_id' => 8,
                            'action' => 'add',
                            'prim_id' => $guest_id,
                            'staff_id' => $user_id,
                            'date_time' => date('Y-m-d H:i:s'),
                            'comments' => "Stay $sno created",
                            'hotel_id' => $hotel_id,
                            'type' => 'API-maestro_pms',
                        ]);
                        DB::commit();
                        $send_email = true;
                    }

                    if ($send_email) {
                        if (! empty($guest_registration['email_address'])) {
                            $rs = $this->sendAngelInvitation($guest_registration['email_address'], $hotel_id, $guest_registration['phone_no']);
                            //$this->writeLog("maestro_pms", $hotel_id, "   Send angel invitation 1 rs: ".json_encode($rs));
                        }
                    }
                } else {
                    //$this->writeLog("maestro_pms", $hotel_id, "No save CheckIn: no room: ".json_encode($find_guest_checkin_details));
                    DB::rollback();
                    $success = false;
                }
            }
        } catch (\Exception $e) {
            //$this->writeLog("maestro_pms", $hotel_id, "CheckIn Error: ".$e."\n\n");
            $success = false;
            DB::rollback();
        }

        return $success;
    }

    public function unique_inArray($hotel_id, $array)
    {
        try {
            $temp_array = [];
            $i = 0;
            $key_array = [];

            foreach (array_reverse($array) as $val) {
                $firstname = '';
                $lastname = '';
                $email_address = '';
                $room_code = '';
                $reservation_number_key = '';

                if (isset($val->FirstName) && is_string($val->FirstName) && ! empty($val->FirstName)) {
                    $firstname = $this->proccessString($val->FirstName);
                }
                if (isset($val->LastName) && is_string($val->LastName) && ! empty($val->LastName)) {
                    $lastname = $this->proccessString($val->LastName);
                }
                if (isset($val->EmailAddress) && is_string($val->EmailAddress) && ! empty($val->EmailAddress)) {
                    $email_address = $this->proccessString($val->EmailAddress);
                }
                if (isset($val->RoomCode) && is_string($val->RoomCode) && ! empty($val->RoomCode)) {
                    $room_code = $this->proccessString($val->RoomCode);
                }
                if (isset($val->ReservationNumberKey) && is_string($val->ReservationNumberKey) && ! empty($val->ReservationNumberKey)) {
                    $reservation_number_key = $this->proccessString($val->ReservationNumberKey);
                }

                //$this->writeLog("maestro_pms", $hotel_id, "$firstname-$lastname-$email_address-$room_code");

                if (! in_array("$firstname-$lastname-$email_address-$room_code-$reservation_number_key", $key_array)) {
                    $key_array[] = "$firstname-$lastname-$email_address-$room_code-$reservation_number_key";
                    $temp_array[] = $val;
                }
                $i++;
            }

            //$this->writeLog("maestro_pms", $hotel_id, "Array filtered: ".json_encode($temp_array));

            return $temp_array;
        } catch (\Exception $e) {
            return [
                'error' => $e,
            ];
        }
    }

    public function RoomMove($hotel_id, $user_id, $data)
    {
        //$this->writeLog("maestro_pms", $hotel_id, "Start RoomMove");

        DB::beginTransaction();
        try {
            $this->configTimeZone($hotel_id);
            $index = 0;

            $SourceRoomInformation = [];
            $DestinationRoomInformation = [];

            $is_array = is_array($data->SourceRoomInformation);
            if ($is_array) {
                $SourceRoomInformation = $data->SourceRoomInformation;
            } else {
                $SourceRoomInformation[] = $data->SourceRoomInformation;
            }

            $is_array = is_array($data->DestinationRoomInformation);
            if ($is_array) {
                $DestinationRoomInformation = $data->DestinationRoomInformation;
            } else {
                $DestinationRoomInformation[] = $data->DestinationRoomInformation;
            }

            foreach ($SourceRoomInformation as $i) {
                $_guest = $i->GuestInfo;

                $email_address = '';
                $lastname = '';
                $firstname = '';

                if ((isset($_guest->EmailAddress)) && (is_string($_guest->EmailAddress) && ! empty($_guest->EmailAddress))) {
                    $email_address = $_guest->EmailAddress;
                }
                if ((isset($_guest->LastName)) && (is_string($_guest->LastName) && ! empty($_guest->LastName))) {
                    $lastname = $_guest->LastName;
                }
                if ((isset($_guest->FirstName)) && (is_string($_guest->FirstName) && ! empty($_guest->FirstName))) {
                    $firstname = $_guest->FirstName;
                }

                $guest = GuestRegistration::where(function ($query) use ($email_address, $firstname, $lastname) {
                    $query
                        ->where('email_address', $email_address)
                        ->where('lastname', $lastname)
                        ->where('firstname', $firstname);
                })
                ->first();

                if ($guest) {
                    $guest_id = $guest->guest_id;

                    $guest_checkin_details = GuestCheckinDetails::where(function ($query) use ($hotel_id, $guest_id) {
                        $query
                            ->where('hotel_id', $hotel_id)
                            ->where('guest_id', $guest_id);
                    })
                    ->orderBy('sno', 'DESC')
                    ->first();

                    if ($guest_checkin_details) {
                        $location = 'Reservation';

                        if ((isset($DestinationRoomInformation[$index])) && (isset($DestinationRoomInformation[$index]->GuestInfo)) && (is_string($DestinationRoomInformation[$index]->GuestInfo->RoomCode) && ! empty($DestinationRoomInformation[$index]->GuestInfo->RoomCode))) {
                            $location = $DestinationRoomInformation[$index]->GuestInfo->RoomCode;
                        }

                        $room = $this->findRoomId($hotel_id, $user_id, $location);
                        $new_room = $room['room_id'];
                        $location = $room['room'];

                        $comment = '';

                        if (isset($i->GuestInfo->ReservationText) && isset($i->GuestInfo->ReservationText->Text)) {
                            if (is_array($i->GuestInfo->ReservationText->Text)) {
                                foreach ($ch->ReservationText->Text as $key => $value) {
                                    if (is_string($value)) {
                                        $comment .= "$value ";
                                    }
                                }
                            } else {
                                $comment = substr($this->proccessString($ch->ReservationText->Text), 0, 250);
                            }
                        }

                        RoomMove::create([
                            'guest_id' => $guest->guest_id,
                            'phone' => $guest->phone_no,
                            'current_room_no' => $guest_checkin_details->room_no,
                            'new_room_no' => $new_room,
                            'comment' => $comment,
                            'hotel_id' => $hotel_id,
                            'created_by' => $user_id,
                            'created_on' => date('Y-m-d H:i:s'),
                            'updated_by' => 0,
                            'updated_on' => null,
                        ]);

                        $guest_checkin_details->room_no = $new_room;
                        $guest_checkin_details->save();
                        DB::commit();
                    }
                }
                $index++;
            }
            $success = true;
        } catch (\Exception $e) {

            //$this->writeLog("maestro_pms", $hotel_id, "Error RoomMove::".$e);
            $error = $e;
            $success = false;
            DB::rollback();
        }

        return $success;
    }

    public function HousekeepingStatus($hotel_id, $user_id, $data)
    {
        $success = false;

        if (is_array($this->hsk_status)) {
            DB::beginTransaction();
            try {
                $this->configTimeZone($hotel_id);
                $hkd = []; //HousekeepingData
                $HousekeepingData = [];

                $is_array = is_array($data->Rooms->HousekeepingData);
                if ($is_array) {
                    $hkd = $data->Rooms->HousekeepingData;
                } else {
                    $hkd[] = $data->Rooms->HousekeepingData;
                }

                $HousekeepingData['hotel_id'] = $hotel_id;
                $HousekeepingData['staff_id'] = $user_id;

                foreach ($hkd as $h) {
                    if (
                        (isset($h->RoomCode) && (is_string($h->RoomCode) && ! empty($h->RoomCode))) &&
                        (isset($h->RoomStatus) && (is_string($h->RoomStatus) && ! empty($h->RoomStatus))) &&
                        (isset($h->HousekeepingStatus) && (is_string($h->HousekeepingStatus) && ! empty($h->HousekeepingStatus)))
                    ) {
                        $location = $h->RoomCode;
                        $room = $this->findRoomId($hotel_id, $user_id, $location);
                        $_d['room_id'] = $room['room_id'];

                        //$this->writeLog("maestro_pms", $hotel_id, "-->-->-->:".strtoupper($h->HousekeepingStatus));
                        //$this->writeLog("maestro_pms", $hotel_id, "-->-->-->:".json_encode($this->hsk_status));

                        if (isset($this->hsk_status[strtoupper($h->HousekeepingStatus)])) {
                            $_d['hk_status'] = $this->hsk_status[strtoupper($h->HousekeepingStatus)]['codes'][0]['hk_status'];
                            $HousekeepingData['rooms'][] = $_d;
                        }
                    }
                }

                if (count($HousekeepingData['rooms']) > 0) {
                    $curl = curl_init();
                    curl_setopt_array($curl, [
                        CURLOPT_URL => 'https://hotel.mynuvola.com/index.php/housekeeping/pmsHKChange',
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
                    //$this->writeLog("maestro_pms", $hotel_id, "Response HousekeepingStatus::".$response);
                }

                DB::commit();
                $success = true;
            } catch (\Exception $e) {
                //$this->writeLog("maestro_pms", $hotel_id, "Error HousekeepingStatus::".$e);
                $error = $e;
                $success = false;
                DB::rollback();
            }
        }

        return $success;
    }

    public function findRoomId($hotel_id, $staff_id, $location)
    {
        if (is_numeric($location)) {
            $sub1 = substr($location, 0, 1);
            if ($sub1 === '0') {
                $location = substr($location, 1);
            }
        }

        $room = HotelRoom::where(function ($query) use ($hotel_id, $location) {
            $query
                ->where('hotel_id', $hotel_id)
                ->where('location', $location);
        })->first();

        if ($room) {
            if ((int) $room->active == 0) {
                $room->update([
                    'active' => 1,
                ]);
            }

            return [
                'room_id' => $room->room_id,
                'room' => $room->location,
            ];
        } else {
            $room = HotelRoom::create([
                'hotel_id' => $hotel_id,
                'location' => $location,
                'created_by' => $staff_id,
                'created_on' => date('Y-m-d H:i:s'),
                'updated_by' => null,
                'updated_on' => null,
                'active' => 1,
                'angel_view' => 1,
                'device_token' => '',
            ]);

            $this->saveLogTracker([
                'hotel_id' => $hotel_id,
                'staff_id' => $staff_id,
                'prim_id' => $room->room_id,
                'module_id' => 17,
                'action' => 'add',
                'date_time' => date('Y-m-d H:i:s'),
                'comments' => "Location $location created",
                'type' => 'API-maestro_pms',
            ]);

            return [
                'room_id' => $room->room_id,
                'room' => $room->location,
            ];
        }
    }

    public function roomType($hotel_id, $staff_id, $location, $name_type)
    {
        $room = $this->findRoomId($hotel_id, $staff_id, $location);
        $room_id = $room['room_id'];

        $RoomType = HotelRoomTypes::where('hotel_id', $hotel_id)->where('name_type', $name_type)->first();

        if ($RoomType) {
            $room_type_id = $RoomType->room_type_id;
        } else {
            $RoomType = HotelRoomTypes::create([
                'hotel_id' => $hotel_id,
                'name_type' => $name_type,
                'created_by' => $staff_id,
                'created_on' => date('Y-m-d H:i:s'),
                'is_active' => 1,
            ]);
            $room_type_id = $RoomType->room_type_id;
        }

        HotelRoom::find($room_id)->updated(['room_type_id' => $room_type_id]);

        return $room_id;
    }

    public function getSalt($hotel_id)
    {
        $this->configTimeZone($hotel_id);
        $salt = $this->generateRandomString();
        //$this->writeLog("maestro_pms", $hotel_id, "Generate Salt:: $salt");

        $m = MaestroPmsSalt::where('hotel_id', $hotel_id)->delete();

        MaestroPmsSalt::create([
            'hotel_id' => $hotel_id,
            'salt' => $salt,
            'created_on' => date('Y-m-d H:i:s'),
        ]);

        return $salt;
    }

    public function generateRandomString()
    {
        $length = 10;

        $salt = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);

        if (MaestroPmsSalt::where('salt', $salt)->first()) {
            $this->generateRandomString();
        }

        return $salt;
    }

    public function validatePasswordHash($hotel_id, $pass_hash, $agreed_upon_key)
    {
        $result = false;
        $this->configTimeZone($hotel_id);
        $m = MaestroPmsSalt::where('hotel_id', $hotel_id)->first();
        $created_on = $m->created_on;
        $now = date('Y-m-d H:i:s');
        $error = '';
        if ($m) {
            $rest = (strtotime($now)) - (strtotime($created_on));
            if ($rest > 112233445566778899) {
                $result = false;
                $error = 'Invalid password hash';
            }
            $salt = $m->salt;

            if (strcmp(hash('sha256', $agreed_upon_key.$salt), $pass_hash) == 0) {
                $result = true;
            } else {
                $result = false;
                $error = 'Invalid password hash';
            }
        } else {
            $result = false;
            $error = 'The hotel does not have the Maestro PMS integration active';
        }

        return [
            'result' => $result,
            'error' => $error,
        ];
    }

    public function Offmarket($hotel_id, $user_id, $data)
    {
        $success = false;

        DB::beginTransaction();
        if (is_array($this->hsk_status)) {
            try {
                $offMarket = [];
                if (is_array($data->Rooms->OffmarketData)) {
                    $offMarket = $data->Rooms->OffmarketData;
                } else {
                    $offMarket[] = $data->Rooms->OffmarketData;
                }
                foreach ($offMarket as $key => $value) {
                    if (
                        (isset($value->RoomCode) && is_string($value->RoomCode) && ! empty($value->RoomCode)) &&
                        (isset($value->RoomType) && is_string($value->RoomType) && ! empty($value->RoomType)) &&
                        (isset($value->StartDate) && is_string($value->StartDate) && ! empty($value->StartDate)) &&
                        (isset($value->EndDate) && is_string($value->EndDate) && ! empty($value->EndDate)) &&
                        (isset($value->OffmarketFlag) && is_string($value->OffmarketFlag) && ! empty($value->OffmarketFlag)) &&
                        (isset($value->OutOfInventoryFlag) && is_string($value->OutOfInventoryFlag) && ! empty($value->OutOfInventoryFlag)) &&
                        (isset($value->OffmarketKey) && is_string($value->OffmarketKey) && ! empty($value->OffmarketKey)) &&
                        (isset($value->OffmarketText) && isset($value->OffmarketText->Text))
                    ) {
                        $room_id = $this->roomType($hotel_id, $user_id, $value->RoomCode, $value->RoomType);

                        $__data = [];
                        if (is_string($value->OffmarketText->Text) && ! empty($value->OffmarketText->Text)) {
                            $__data[] = $value->OffmarketText->Text;
                        } elseif (is_array($value->OffmarketText->Text) && count($value->OffmarketText->Text) > 0) {
                            $__data = $value->OffmarketText->Text;
                        }

                        $comment = '';

                        if (count($__data) > 0) {
                            $reason = 'Provided by Maestro PMS';
                            foreach ($__data as $key => $value1) {
                                if (isset($value1) && is_string($value1)) {
                                    $comment = "$comment $value1";
                                }
                            }
                        }

                        $reason = 'PROVIDED BY MAESTRO PMS';
                        $HkReason = HousekeepingReasons::where('reason', $reason)->first();
                        if (! $HkReason) {
                            $HkReason = HousekeepingReasons::create([
                                'hotel_id' => $hotel_id,
                                'reason_type' => 3,
                                'reason' => $reason,
                                'creatd_by' => $user_id,
                                'created_on' => date('Y-m-d H:i:s'),
                                'is_default' => 0,
                                'is_active' => 1,
                            ]);
                        }
                        $reason_id = $HkReason->reason_id;
                        $HotelRoomsOut = HotelRoomsOut::where('reservation_number', $value->OffmarketKey)->where('is_close', 0)->first();

                        if ($HotelRoomsOut) {
                            //close
                            if ($value->OffmarketFlag == 'false' && $value->OutOfInventoryFlag == 'false') {
                                $HotelRoomsOut->fill([
                                    'end_date' => date('Y-m-d H:i:s'),
                                    'is_close' => 1,
                                    'updated_by' => $user_id,
                                    'updated_on' => date('Y-m-d H:i:s'),
                                ]);
                                $HotelRoomsOut->save();
                            } elseif ($value->OffmarketFlag == 'true' && $value->OutOfInventoryFlag == 'false') {
                                $HotelRoomsOut->fill([
                                    'hk_reasons_id' => $reason_id,
                                    'is_close' => 0,
                                    'status' => 1,
                                    'start_date' => (new DateTime($value->StartDate))->format('Y-m-d H:i:s'),
                                    'end_date' => (new DateTime($value->EndDate))->format('Y-m-d H:i:s'),
                                    'updated_by' => $user_id,
                                    'updated_on' => date('Y-m-d H:i:s'),
                                ]);
                                $HotelRoomsOut->save();
                            } else {
                                $HotelRoomsOut->fill([
                                    'hk_reasons_id' => $reason_id,
                                    'is_close' => 0,
                                    'status' => 2,
                                    'start_date' => (new DateTime($value->StartDate))->format('Y-m-d H:i:s'),
                                    'end_date' => (new DateTime($value->EndDate))->format('Y-m-d H:i:s'),
                                    'updated_by' => $user_id,
                                    'updated_on' => date('Y-m-d H:i:s'),
                                ]);
                                $HotelRoomsOut->save();
                            }
                            //$this->writeLog("maestro_pms", $hotel_id, "Updated HotelRoomsOut Offmarket::".json_encode($HotelRoomsOut));
                        } else {
                            $is_close = 0;
                            $status = 0;

                            if ($value->OffmarketFlag == 'true' && $value->OutOfInventoryFlag == 'false') {
                                $status = 1;
                            } else {
                                $status = 2;
                            }

                            $HotelRoomsOut = HotelRoomsOut::create([
                                'room_id' => $room_id,
                                'hotel_id' => $hotel_id,
                                'start_date' => $value->StartDate.' 00:00:00',
                                'end_date' => $status == 0 ? date('Y-m-d H:i:s') : $value->EndDate.' 23:59:00',
                                'status' => $status == 0 ? 1 : $status,
                                'is_close' => $is_close,
                                'reservation_number' => $value->OffmarketKey,
                                'hk_reasons_id' => $reason_id,
                                'created_by' => $user_id,
                                'created_on' => date('Y-m-d H:i:s'),
                                'comment' => $comment,
                            ]);

                            //HousekeepingCleanings::where('room_id', $room_id)->where('status', 1);
                            //$this->writeLog("maestro_pms", $hotel_id, "Create HotelRoomsOut Offmarket::".json_encode($HotelRoomsOut));
                        }
                    }
                }
                DB::commit();
            } catch (\Exception $e) {
                //$this->writeLog("maestro_pms", $hotel_id, "Error Offmarket::".$e);
                DB::rollback();
            }
        }

        return $success;
    }

    public function select(Request $request)
    {
        // DB::table('guest_checkin_details')
        // ->whereIn('hotel_id',[ 231, 232, 243 ])
        // ->where('room_no', 0)
        // ->where('status', '!=', -1)
        // ->update([ 'status' => -1 ]);

        //  $sql = DB::table('guest_checkin_details')->whereIn('hotel_id',[198,206,208,216,217,230,231,231])->where('status', -1)
        //  ->where('room_no', '>', 0)->toSql();

        $result = DB::select($request->select);

        return response()->json([
            //"sql" => $sql,
            'result' => $result,

        ], 200);
    }
}
