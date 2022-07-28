<?php

namespace App\Jobs;

use App\Models\GuestCheckinDetails;
use App\Models\GuestRegistration;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\HotelRoomsOut;
use App\Models\HotelRoomTypes;
use App\Models\HousekeepingReasons;
use App\Models\IntegrationsGuestInformation;
use App\Models\LogTracker;
use App\Models\RoomMove;
use App\Models\SmsChat;
use DateTime;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\ArrayToXml\ArrayToXml;

class MaestroPms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $maestroIntegration;

    private $data;

    private $hsk_status;

    private $sync_route;

    private $room_id;

    private $syncReservation;

    public function __construct($maestroIntegration, $data, $sync_route = false, $room_id = null, $syncReservation = false)
    {
        $this->data = $data;
        // \Log::info(json_encode($data));
        $this->sync_route = $sync_route;
        $this->maestroIntegration = $maestroIntegration;
        $this->room_id = $room_id;
        $this->syncReservation = $syncReservation;
        if (isset($this->maestroIntegration->config['housekeeping'])) {
            $this->hsk_status = $this->maestroIntegration->config['housekeeping'];
        }
    }

    public function handle()
    {
        $hotel_id = $this->maestroIntegration->hotel_id;

        $staff_id = $this->maestroIntegration->created_by;
        $timezone = Hotel::find($hotel_id)->time_zone;
        date_default_timezone_set($timezone);
        if ($this->sync_route) {
            $__data = $this->GenerateSync($hotel_id, $this->room_id);

            // \Log::info(json_encode($__data));

            foreach ($__data as $room) {
                $this->data = $room['response'];
                if (isset($this->data->Reservations)) {
                    // if( $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
                    //     \Log::info('------------------Maestro entro al reservation list ----------------------');
                    //     \Log::info(json_encode($__data));
                    //     \Log::info('----------------------------------------');
                    // }
                    $this->ReservationList($hotel_id, $staff_id, $this->data);
                } else {
                    // if( $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
                    //     \Log::info('------------------Maestro entro al UpdateStateReservation ----------------------');
                    //     \Log::info(json_encode($__data));
                    //     \Log::info('----------------------------------------');
                    // }
                    $this->UpdateStateReservation($hotel_id, $staff_id, $this->data);
                }
                // if( $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
                //     \Log::info('------------------Maestro entro al HousekeepingStatusSync ----------------------');
                //     \Log::info(json_encode($__data));
                //     \Log::info('----------------------------------------');
                // }
                $this->HousekeepingStatusSync($hotel_id, $staff_id, $this->data);
            }
        } elseif ($this->syncReservation) {
            if (isset($this->data->Reservations)) {
                // if( $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
                //     \Log::info('------------------Maestro entro al reservation list elseif ----------------------');
                //     \Log::info(json_encode($__data));
                //     \Log::info('----------------------------------------');
                // }
                $this->ReservationList($hotel_id, $staff_id, $this->data);
            }
        } else {
            if (method_exists($this, $this->data->Action)) {
                $method = $this->data->Action;
                $this->$method($hotel_id, $staff_id, $this->data);
            }
        }
    }

    private function ReservationList($hotel_id, $staff_id, $data)
    {
        try {
            // if( $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
            //     \Log::info('------------------Maestro entro al reservation list ----------------------');
            //     \Log::info(json_encode($data));
            //     \Log::info('----------------------------------------');
            // }

            $reservation_data = [];
            $is_array = is_array($data->Reservations->ReservationData);

            if ($is_array) {
                $reservation_data = $data->Reservations->ReservationData;
            } else {
                $reservation_data[] = $data->Reservations->ReservationData;
            }
            $reservation_data = $this->unique_inArray($reservation_data);

            foreach ($reservation_data as $data) {
                switch ($data->Status) {
                    case 'reserved':
                        $this->CheckInReservationList($hotel_id, $staff_id, $data, 0);
                        break;
                    case 'checked_in':
                        $this->CheckInReservationList($hotel_id, $staff_id, $data, 1);
                        break;
                    case 'cancelled':
                        $this->CheckOutReservationList($hotel_id, $staff_id, $data, 2);
                        break;
                    case 'checked_out':
                        $this->CheckOutReservationList($hotel_id, $staff_id, $data, 3);
                        break;
                }
            }
            $success = true;
        } catch (\Exception $e) {
            $success = false;
            \Log::info('Error in reservation list function:');
            \Log::info($e);
        }

        return $success;
    }

    private function CheckInReservationList($hotel_id, $staff_id, $data, $reservation_status)
    {
        // if($hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
        //     \Log::info('------------------Maestro entro al CheckInReservationList ----------------------');
        //     \Log::info('reservation status');
        //     \Log::info($reservation_status);
        //     \Log::info('data');
        //     \Log::info(json_encode($data));
        //     \Log::info('----------------------------------------');
        // }
        $success = false;
        DB::beginTransaction();
        try {
            //firstname
            $firstname = (isset($data->FirstName) && is_string($data->FirstName) && ! empty($data->FirstName)) ? $data->FirstName : '';
            $firstname = addslashes($firstname);
            $firstname = utf8_encode($firstname);
            //lastname
            $lastname = (isset($data->LastName) && is_string($data->LastName) && ! empty($data->LastName)) ? $data->LastName : '';
            $lastname = addslashes($lastname);
            $lastname = utf8_encode($lastname);
            //email
            $email_address = (isset($data->EmailAddress) && is_string($data->EmailAddress) && ! empty($data->EmailAddress) && filter_var($data->EmailAddress, FILTER_VALIDATE_EMAIL)) ? $data->EmailAddress : '';
            $email_address = addslashes($email_address);
            $email_address = utf8_encode($email_address);
            //phone
            $phone_no = (isset($data->Cell) && is_string($data->Cell) && ! empty($data->Cell)) ? $data->Cell : '';
            if (empty($phone_no)) {
                $phone_no = (isset($data->Phone) && is_string($data->Phone) && ! empty($data->Phone)) ? $data->Phone : '';
            }
            $phone_no = str_replace(['-', '.', ' ', '(', ')', '*', '/', 'na', '+'], '', $phone_no);
            $phone_no = preg_replace('/[^0-9]/', '', $phone_no);
            if (! empty($phone_no) && is_numeric($phone_no)) {
                $phone_no = "+$phone_no";
            }
            //state
            $state = (isset($data->State) && is_string($data->State) && ! empty($data->State)) ? $data->State : '';
            $state = addslashes($state);
            $state = utf8_encode($state);
            //zipcode
            $zipcode = (isset($data->ZipCode) && is_string($data->ZipCode) && ! empty($data->ZipCode)) ? $data->ZipCode : '';
            $zipcode = addslashes($zipcode);
            $zipcode = utf8_encode($zipcode);
            //language
            $language = (isset($data->Language) && is_string($data->Language) && ! empty($data->Language)) ? $data->Language : '';
            //comment
            $comment = '';
            if (isset($data->ReservationText) && isset($data->ReservationText->Text)) {
                $Text = $data->ReservationText->Text;
                $arr_comment = is_array($Text) ? $Text : [$Text];
                foreach ($arr_comment as $key => $value) {
                    $comment .= (is_string($value) && ! empty($value)) ? "$value " : '';
                }
                $comment = ! empty($comment) ? substr($comment, 0, 250) : '';
            }
            //angel_status
            $angel_status = $this->validateAngelStatus($hotel_id);
            //city
            $city = (isset($data->City) && is_string($data->City) && ! empty($data->City)) ? $data->City : '';
            $city = addslashes($city);
            $city = utf8_encode($city);
            //now
            $now = date('Y-m-d H:i:s');
            //generar el array de los datos anteriormente validados
            $guest_registration = [
                'hotel_id' => $hotel_id,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email_address' => $email_address,
                'phone_no' => $phone_no,
                'address' => '',
                'state' => $state,
                'zipcode' => $zipcode,
                'language' => $language,
                'comment' => $comment,
                'angel_status' => $angel_status,
                'city' => $city,
                'created_by' => $staff_id,
                'created_on' => $now,
            ];

            $ClientCode = $data->ClientCode;
            $back = false;

            $IntegrationsGuestInformation = IntegrationsGuestInformation::where('hotel_id', $hotel_id)
                ->where('guest_number', $ClientCode)
                ->first();

            $GuestRegistration = null;

            if ($IntegrationsGuestInformation) {
                $guest_id = $IntegrationsGuestInformation->guest_id;

                $GuestRegistration = GuestRegistration::where('hotel_id', $hotel_id)
                    ->where('guest_id', $guest_id)
                    ->first();

                $gcd = GuestCheckinDetails::where('hotel_id', $hotel_id)
                    ->where('guest_id', $guest_id)
                    ->get();

                if (count($gcd) > 1) {
                    $back = true;
                }
            }

            if ($GuestRegistration) {
                $__update = '';
                if ($GuestRegistration->firstname !== $guest_registration['firstname']) {
                    $__update .= "firstname: $GuestRegistration->firstname to $guest_registration[firstname]. ";
                    $GuestRegistration->firstname = $guest_registration['firstname'];
                }
                if ($GuestRegistration->lastname !== $guest_registration['lastname']) {
                    $__update .= "lastname: $GuestRegistration->lastname to $guest_registration[lastname]. ";
                    $GuestRegistration->lastname = $guest_registration['lastname'];
                }
                if ($GuestRegistration->email_address !== $guest_registration['email_address']) {
                    $__update .= "email_address: $GuestRegistration->email_address to $guest_registration[email_address]. ";
                    $GuestRegistration->email_address = $guest_registration['email_address'];
                }
                if ($GuestRegistration->phone_no !== $guest_registration['phone_no']) {
                    $__update .= "phone_no: $GuestRegistration->phone_no to $guest_registration[phone_no]. ";
                    $GuestRegistration->phone_no = $guest_registration['phone_no'];
                }
                if ($GuestRegistration->zipcode !== $guest_registration['zipcode']) {
                    $__update .= "zipcode: $GuestRegistration->zipcode to $guest_registration[zipcode]. ";
                    $GuestRegistration->zipcode = $guest_registration['zipcode'];
                }
                if ($GuestRegistration->city !== $guest_registration['city']) {
                    $__update .= "city: $GuestRegistration->city to $guest_registration[city]. ";
                    $GuestRegistration->city = $guest_registration['city'];
                }
                if ($GuestRegistration->comment !== $guest_registration['comment']) {
                    $__update .= "comment: $GuestRegistration->comment to $guest_registration[comment]. ";
                    $GuestRegistration->comment = $guest_registration['comment'];
                }
                // if ($hotel_id == 267) {
                //     \Log::info("1");
                // }
                if (! empty($__update)) {
                    $GuestRegistration->updated_on = $now;
                    $GuestRegistration->updated_by = $staff_id;
                    $GuestRegistration->save();

                    $this->saveLogTracker([
                        'module_id' => 8,
                        'action' => 'update',
                        'prim_id' => $guest_id,
                        'staff_id' => $staff_id,
                        'date_time' => $now,
                        'comments' => "Update Guest information: $__update",
                        'hotel_id' => $hotel_id,
                        'type' => 'API-maestro_pms',
                    ]);

                    // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
                    //     \Log::info('------------------Maestro entro al updateGuest ----------------------');
                    //     \Log::info('reservation status');
                    //     \Log::info($reservation_status);
                    //     \Log::info('data');
                    //     \Log::info(json_encode($GuestRegistration));
                    //     \Log::info('----------------------------------------');
                    // }
                }
            } else {
                $guest_id = GuestRegistration::create($guest_registration)->guest_id;
                IntegrationsGuestInformation::create([
                    'hotel_id' => $hotel_id,
                    'guest_id' => $guest_id,
                    'guest_number' => $ClientCode,
                ]);
                $this->saveLogTracker([
                    'module_id' => 8,
                    'action' => 'add',
                    'prim_id' => $guest_id,
                    'staff_id' => $staff_id,
                    'date_time' => $now,
                    'comments' => 'Add Guest information',
                    'hotel_id' => $hotel_id,
                    'type' => 'API-maestro_pms',
                ]);
                // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
                //     \Log::info('------------------Maestro entro al createguest ----------------------');
                //     \Log::info('Guest_id:');
                //     \Log::info(json_encode($guest_id));
                //     \Log::info('----------------------------------------');
                // }
            }
            DB::commit();
            if ($hotel_id != 267) {
                $this->guestChatDummy($guest_id, $guest_registration['phone_no'], $hotel_id);
            }
        } catch (\Exception $e) {
            DB::rollback();
            \Log::info("CheckInReservationList Error:\n");
            \Log::info($e);
            $success = false;
        }

        DB::beginTransaction();

        try {
            $room_no = 0;
            if (isset($data->Room) && isset($data->Room->RoomCode) && is_string($data->Room->RoomCode) && ! empty($data->Room->RoomCode)) {
                $room_code = $data->Room->RoomCode;
                $room = $this->findRoomId($hotel_id, $staff_id, $room_code);
                $room_no = (int) $room['room_id'];
            }

            $ReservationNumber = $data->ReservationNumber;

            $__ArrivalDate = is_string($data->ArrivalDate) ? $data->ArrivalDate : '';
            $__DepartureDate = is_string($data->DepartureDate) ? $data->DepartureDate : '';
            $reg = '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})-(\d{2}):(\d{2})$/';
            if (preg_match($reg, $__ArrivalDate) == true && preg_match($reg, $__DepartureDate) == true) {
                $send_email = false;

                $check_in = (new DateTime($data->ArrivalDate))->format('Y-m-d H:i:s');
                $check_out = (new DateTime($data->DepartureDate))->format('Y-m-d H:i:s');

                $guest_checkin_details = [
                    'guest_id' => $guest_id,
                    'hotel_id' => $hotel_id,
                    'room_no' => $room_no,
                    'check_in' => $check_in,
                    'check_out' => $check_out,
                    'reservation_status' => $reservation_status,
                    'reservation_number' => $ReservationNumber,
                    'comment' => '',
                    'status' => 1,
                    'main_guest' => 0,
                ];

                $GuestCheckinDetails = GuestCheckinDetails::where('hotel_id', $hotel_id)
                    ->where('reservation_number', $ReservationNumber)
                    ->first();

                if ($GuestCheckinDetails) {
                    $__roomMove = false;
                    $__update = '';
                    if (($GuestCheckinDetails->guest_id !== $guest_checkin_details['guest_id'])) {
                        $__update .= "guest_id: $GuestCheckinDetails->guest_id to $guest_checkin_details[guest_id]. ";
                        $GuestCheckinDetails->guest_id = $guest_checkin_details['guest_id'];
                    }

                    if ($GuestCheckinDetails->room_no !== $guest_checkin_details['room_no']) {
                        if ($GuestCheckinDetails->reservation_status == 1 && $guest_checkin_details['room_no'] !== 0) {
                            $__roomMove = true;
                        } else {
                            $__update .= "room_no: $GuestCheckinDetails->room_no to $guest_checkin_details[room_no]. ";
                            $GuestCheckinDetails->room_no = $guest_checkin_details['room_no'];
                        }
                    }
                    if (($GuestCheckinDetails->check_in !== $guest_checkin_details['check_in']) && ! $__roomMove) {
                        $__update .= "check_in: $GuestCheckinDetails->check_in to $guest_checkin_details[check_in]. ";
                        $GuestCheckinDetails->check_in = $guest_checkin_details['check_in'];
                    }
                    if (($GuestCheckinDetails->check_out !== $guest_checkin_details['check_out']) && ! $__roomMove) {
                        $__update .= "check_out: $GuestCheckinDetails->check_out to $guest_checkin_details[check_out]. ";
                        $GuestCheckinDetails->check_out = $guest_checkin_details['check_out'];
                    }
                    if (($GuestCheckinDetails->reservation_status !== $guest_checkin_details['reservation_status']) && ! $__roomMove) {
                        $send_email = false;
                        if ($GuestCheckinDetails->reservation_status === 0 && $guest_checkin_details['reservation_status'] === 1) {
                            $send_email = true;
                        }
                        $__update .= "reservation_status: $GuestCheckinDetails->reservation_status to $guest_checkin_details[reservation_status]. ";
                        $GuestCheckinDetails->reservation_status = $guest_checkin_details['reservation_status'];
                    }
                    if ($__roomMove) {
                        $GuestCheckinDetails->status = 0;
                        $GuestCheckinDetails->reservation_status = 3;
                        $GuestCheckinDetails->reservation_number = $GuestCheckinDetails->reservation_number.'_RM';

                        $sno = GuestCheckinDetails::create($guest_checkin_details)->sno;
                        $this->saveLogTracker([
                            'module_id' => 8,
                            'action' => 'add',
                            'prim_id' => $guest_id,
                            'staff_id' => $staff_id,
                            'date_time' => $now,
                            'comments' => "Room move, Stay $sno created",
                            'hotel_id' => $hotel_id,
                            'type' => 'API-maestro_pms',
                        ]);
                        RoomMove::create([
                            'guest_id' => $guest_id,
                            'current_room_no' => $GuestCheckinDetails->room_no,
                            'new_room_no' => $guest_checkin_details['room_no'],
                            'hotel_id' => $hotel_id,
                            'created_by' => $staff_id,
                            'created_on' => $now,
                            'updated_on' => null,
                            'comment' => '',
                            'phone' => '',
                            'updated_by' => 0,
                        ]);
                    // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
                        //     \Log::info('------------------Maestro entro al createGuestCheckinDetails ----------------------');
                        //     \Log::info('sno:');
                        //     \Log::info(json_encode($sno));
                        //     \Log::info('----------------------------------------');
                        // }
                    } elseif (! empty($__update)) {
                        $this->saveLogTracker([
                            'hotel_id' => $hotel_id,
                            'type' => 'API-maestro_pms',
                            'module_id' => 8,
                            'action' => 'update',
                            'prim_id' => $guest_id,
                            'staff_id' => $staff_id,
                            'date_time' => date('Y-m-d H:i:s'),
                            'comments' => "Stay $GuestCheckinDetails->sno updated: $__update",
                        ]);
                    }

                    $GuestCheckinDetails->save();
                } else {
                    $sno = GuestCheckinDetails::create($guest_checkin_details)->sno;
                    $send_email = $reservation_status == 1 ? true : false;
                    $this->saveLogTracker([
                        'module_id' => 8,
                        'action' => 'add',
                        'prim_id' => $guest_id,
                        'staff_id' => $staff_id,
                        'date_time' => $now,
                        'comments' => "Stay $sno created",
                        'hotel_id' => $hotel_id,
                        'type' => 'API-maestro_pms',
                    ]);
                }

                if ($send_email) {

                    // $smsChat = \App\Models\SmsChat::where('hotel_id', $hotel_id)
                    //     ->where('phone_no', $guest_registration['phone_no'])
                    //     ->whereBetween('created_on', [
                    //         $guest_checkin_details['check_in'],
                    //         $guest_checkin_details['check_out']
                    //     ])->get();

                    $__GuestCheckinDetails = GuestCheckinDetails::where('hotel_id', $hotel_id)
                        ->where('guest_id', $guest_id)
                        // ->where(function ($q) use ($check_in, $check_out) {
                        //     $q->whereRaw("'$check_in' BETWEEN check_in and check_out")
                        //         ->orWhereRaw("'$check_out' BETWEEN check_in and check_out");
                        // })
                        ->where('reservation_status', 1)
                        ->where('status', 1)
                        ->get();
                    $rs = null;
                    if (count($__GuestCheckinDetails) === 1 && $room_no > 0) {
                        // if (count($smsChat) === 0 && $room_no > 0) {
                        try {
                            $date = date('Y-m-d H:i:s');
                            if ($__GuestCheckinDetails->first()->check_in == $date) {
                                $rs = $this->sendMessages(
                                    $hotel_id,
                                    $guest_id,
                                    $staff_id,
                                    $guest_registration['email_address'],
                                    $guest_registration['phone_no'],
                                    $back,
                                    $__GuestCheckinDetails->first()->check_in,
                                    $__GuestCheckinDetails->first()->check_out
                                );
                            }
                        } catch (\Exception $e) {
                            \Log::error("Validation SMS ERROR:\n");
                            \Log::error($e);
                        }

                        $this->saveLogTracker([
                            'module_id' => 0,
                            'action' => 'send_mail',
                            'prim_id' => $guest_id,
                            'staff_id' => $staff_id,
                            'date_time' => $now,
                            'comments' => json_encode([
                                'data' => [
                                    'hotel_id' => $hotel_id,
                                    'guest_id' => $guest_id,
                                    'staff_id' => $staff_id,
                                    'email_address' => $guest_registration['email_address'],
                                    'phone_no' => $guest_registration['phone_no'],
                                    'back' => $back,
                                ],
                                'rs' => $rs,
                            ]),
                            'hotel_id' => $hotel_id,
                            'type' => 'API-maestro_pms',
                        ]);
                    }
                }
                DB::commit();
                $success = true;
            } else {
                DB::rollback();
                $success = false;
            }
        } catch (\Exception $e) {
            DB::rollback();
            \Log::info("CheckInReservationList Error:\n");
            \Log::info($e);
            $success = false;
        }

        return $success;
    }

    private function CheckOutReservationList($hotel_id, $staff_id, $data, $reservation_status)
    {
        // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
        //     \Log::info('------------------Maestro entro al CheckOutReservationList ----------------------');
        //     \Log::info('reservation status');
        //     \Log::info($reservation_status);
        //     \Log::info('data');
        //     \Log::info(json_encode($data));
        //     \Log::info('----------------------------------------');
        // }

        $RoomCode = (isset($data->Room) && isset($data->Room->RoomCode) && is_string($data->Room->RoomCode) && ! empty($data->Room->RoomCode)) ? $data->Room->RoomCode : '';
        $FirstName = (isset($data->FirstName) && is_string($data->FirstName) && ! empty($data->FirstName)) ? $data->FirstName : '';
        $LastName = (isset($data->LastName) && is_string($data->LastName) && ! empty($data->LastName)) ? $data->LastName : '';
        $EmailAddress = (isset($data->EmailAddress) && is_string($data->EmailAddress) && ! empty($data->EmailAddress)) ? $data->EmailAddress : '';
        $ArrivalDate = (isset($data->ArrivalDate) && is_string($data->ArrivalDate) && ! empty($data->ArrivalDate)) ? $data->ArrivalDate : '';
        $DepartureDate = (isset($data->DepartureDate) && is_string($data->DepartureDate) && ! empty($data->DepartureDate)) ? $data->DepartureDate : '';
        $ReservationNumber = (isset($data->ReservationNumber) && is_string($data->ReservationNumber) && ! empty($data->ReservationNumber)) ? $data->ReservationNumber : '';
        $check_out = (object) [
            (object) [
                'FirstName' => $FirstName,
                'LastName' => $LastName,
                'EmailAddress' => $EmailAddress,
                'ArrivalDate' => $ArrivalDate,
                'DepartureDate' => $DepartureDate,
                'ReservationNumber' => $ReservationNumber,
                'RoomCode' => $RoomCode,
            ],
        ];
        $success = $this->CheckOut($hotel_id, $staff_id, $check_out, $reservation_status);

        return $success;
    }

    private function CheckIn($hotel_id, $staff_id, $data)
    {
        $success = false;
        try {
            $Check_in_data = [];
            $is_array = is_array($data->CheckInData->GuestInfo);
            if ($is_array) {
                $Check_in_data = $data->CheckInData->GuestInfo;
            } else {
                $Check_in_data[] = $data->CheckInData->GuestInfo;
            }
            foreach ($Check_in_data as $value) {
                $GuestRegistration = null;
                $status = null;
                $reservation_status = null;
                $phone_no = null;
                DB::beginTransaction();
                $ReservationNumber = $value->ReservationNumber;
                if (is_string($ReservationNumber)) {
                    $GuestCheckinDetails = GuestCheckinDetails::where('hotel_id', $hotel_id)->where('reservation_number', $ReservationNumber)->first();

                    if ($GuestCheckinDetails) {
                        $status = $GuestCheckinDetails->status;
                        $reservation_status = $GuestCheckinDetails->reservation_status;
                        $room_no = 0;
                        if (isset($value->RoomCode) && is_string($value->RoomCode) && ! empty($value->RoomCode)) {
                            $room_code = $value->RoomCode;
                            $room = $this->findRoomId($hotel_id, $staff_id, $room_code);
                            $room_no = (int) $room['room_id'];
                            $GuestCheckinDetails->room_no = $room_no;
                        }

                        $GuestCheckinDetails->status = 1;
                        $GuestCheckinDetails->reservation_status = 1;
                        $GuestCheckinDetails->check_in = (new DateTime($value->ArrivalDate))->format('Y-m-d H:i:s');
                        $GuestCheckinDetails->check_out = (new DateTime($value->DepartureDate))->format('Y-m-d H:i:s');

                        $GuestCheckinDetails->save();

                        $GuestRegistration = GuestRegistration::find($GuestCheckinDetails->guest_id);
                        $__GuestCheckinDetails = GuestCheckinDetails::where('hotel_id', $hotel_id)->where('guest_id', $GuestCheckinDetails->guest_id)->get();
                        $back = false;

                        if (count($__GuestCheckinDetails) > 1) {
                            $back = true;
                        }
                        $phone_no = str_replace(['-', '.', ' ', '(', ')', '*', '/', 'na', '+'], '', is_string($value->Cell) ? $value->Cell : '');
                        $phone_no = preg_replace('/[^0-9]/', '', $phone_no);

                        if (! empty($phone_no) && is_numeric($phone_no)) {
                            $phone_no = "+$phone_no";
                        }
                        $GuestRegistration->email_address = is_string($value->EmailAddress) ? $value->EmailAddress : '';
                        $GuestRegistration->phone_no = ! empty($phone_no) ? $phone_no : '';
                        $GuestRegistration->save();
                        $this->guestChatDummy($GuestCheckinDetails->guest_id, $phone_no, $hotel_id);
                    }
                    DB::commit();

                    if ($GuestCheckinDetails) {
                        if ($GuestCheckinDetails->status == 1 && $GuestCheckinDetails->reservation_status != $reservation_status) {
                            $rs = null;
                            try {
                                $date = date('Y-m-d H:i:s');
                                if ($GuestCheckinDetails->check_in == $date) {
                                    $rs = $this->sendMessages(
                                        $hotel_id,
                                        $GuestCheckinDetails->guest_id,
                                        $staff_id,
                                        is_string($value->EmailAddress) ? $value->EmailAddress : '',
                                        $phone_no,
                                        $back,
                                        $GuestCheckinDetails->check_in,
                                        $GuestCheckinDetails->check_out
                                    );
                                }
                            } catch (\Throwable $th) {
                                \Log::error("Validation SMS ERROR:\n".$th);
                            }

                            $this->saveLogTracker([
                                'module_id' => 0,
                                'action' => 'send_mail',
                                'prim_id' => $GuestCheckinDetails->guest_id,
                                'staff_id' => $staff_id,
                                'date_time' => date('Y-m-d H:i:s'),
                                'comments' => json_encode([
                                    'data' => [
                                        'hotel_id' => $hotel_id,
                                        'guest_id' => $GuestCheckinDetails->guest_id,
                                        'staff_id' => $staff_id,
                                        'EmailAddress' => is_string($value->EmailAddress) ? $value->EmailAddress : '',
                                        'Cell' => is_string($value->Cell) ? $value->Cell : '',
                                        'back' => $back,
                                    ],
                                    'rs' => $rs ? $rs : null,
                                ]),
                                'hotel_id' => $hotel_id,
                                'type' => 'API-maestro_pms',
                            ]);
                        }
                    }
                }
            }

            $success = true;
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error("CheckIn Error:\n");
            \Log::error($e);
            $success = false;
        }

        return $success;
    }

    private function CheckOut($hotel_id, $user_id, $checkOut, $reservation_status = 3)
    {
        // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
        //     \Log::info('------------------Maestro entro al CheckOut ----------------------');
        //     \Log::info('----------------------------------------');
        // }
        $success = true;
        $check_out = [];
        if (isset($checkOut->CheckOutData)) {
            $check_out = $checkOut->CheckOutData->GuestInfo;
            if (is_array($checkOut->CheckOutData->GuestInfo)) {
                $check_out = $checkOut->CheckOutData->GuestInfo;
            } else {
                $check_out = [$checkOut->CheckOutData->GuestInfo];
            }
        } else {
            $check_out = $checkOut;
        }
        // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
        //     \Log::info(\json_encode($check_out));
        //     \Log::info('----------------------------------------');
        // }
        $now = date('Y-m-d H:i:s');

        foreach ($check_out as $data) {
            // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
            //     \Log::info("entro forecah");
            //     \Log::info('----------------------------------------');
            // }
            DB::beginTransaction();
            try {
                $LastName = (isset($data->EmailAddress) && is_string($data->LastName) && ! empty($data->LastName)) ? $data->LastName : '';
                $FirstName = (isset($data->FirstName) && is_string($data->FirstName) && ! empty($data->FirstName)) ? $data->FirstName : '';
                $EmailAddress = (isset($data->EmailAddress) && is_string($data->EmailAddress) && ! empty($data->EmailAddress)) ? $data->EmailAddress : '';
                $RoomCode = (isset($data->RoomCode) && is_string($data->RoomCode) && ! empty($data->RoomCode)) ? $data->RoomCode : '';
                $ReservationNumber = (isset($data->ReservationNumber) && is_string($data->ReservationNumber) && ! empty($data->ReservationNumber)) ? $data->ReservationNumber : '';

                $room_no = 0;

                if (! empty($RoomCode)) {
                    $room = HotelRoom::where('hotel_id', $hotel_id)
                        ->where('location', $RoomCode)
                        ->first();

                    if ($room) {
                        $room_no = $room->room_id;
                    }
                }

                $guest_checkin_details = GuestCheckinDetails::where('hotel_id', $hotel_id)
                    ->where('reservation_number', $ReservationNumber)
                    ->first();
                // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
                //     \Log::info(\json_encode($guest_checkin_details));
                //     \Log::info('----------------------------------------');
                // }
                if ($guest_checkin_details) {
                    $guest_checkin_details->check_in = (new DateTime($data->ArrivalDate))->format('Y-m-d H:i:s');
                    $guest_checkin_details->check_out = (new DateTime($data->DepartureDate))->format('Y-m-d H:i:s');
                    $guest_checkin_details->status = 0;
                    $guest_checkin_details->reservation_status = $reservation_status;
                    $guest_checkin_details->save();
                    // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
                    //     \Log::info("guardo guest checkin details 1");
                    //     \Log::info('----------------------------------------');
                    // }
                    DB::commit();

                // return true;
                } else {
                    $guest = GuestRegistration::select(['guest_id'])
                        ->where('email_address', $EmailAddress)
                        ->Where('lastname', $LastName)
                        ->where('firstname', $FirstName)
                        ->first();

                    if ($guest) {
                        $guest_id = $guest->guest_id;
                        $check_in = (new DateTime($data->ArrivalDate))->format('Y-m-d');
                        $check_out = (new DateTime($data->DepartureDate))->format('Y-m-d');

                        $guest_checkin_details = GuestCheckinDetails::where('status', 1)
                            ->where('hotel_id', $hotel_id)
                            ->where('guest_id', $guest_id)
                            ->where('room_no', $room_no)
                            ->where('reservation_status', ($reservation_status == 2 ? 0 : 1))
                            ->where(function ($q) use ($check_in, $check_out) {
                                $q
                                    ->where(DB::raw("(DATE_FORMAT(check_in,'%Y-%m-%d'))"), $check_in)
                                    ->orWhere(DB::raw("(DATE_FORMAT(check_out,'%Y-%m-%d'))"), $check_out);
                            })
                            ->first();

                        if ($guest_checkin_details) {
                            $guest_checkin_details->check_in = (new DateTime($data->ArrivalDate))->format('Y-m-d H:i:s');
                            $guest_checkin_details->check_out = (new DateTime($data->DepartureDate))->format('Y-m-d H:i:s');
                            $guest_checkin_details->status = 0;
                            $guest_checkin_details->reservation_status = $reservation_status;
                            $guest_checkin_details->reservation_number = $ReservationNumber;
                            $guest_checkin_details->save();
                            DB::commit();
                            // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
                            //     \Log::info("guardo guest checkin details 2");
                            //     \Log::info('----------------------------------------');
                            // }
                            // return true;
                        }
                    }

                    DB::rollback();
                    // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
                    //     \Log::info("return roolback");
                    //     \Log::info('----------------------------------------');
                    // }
                    // return true;
                }
            } catch (\Exception $e) {
                DB::rollback();
                \Log::info("Check out error $hotel_id :");
                \Log::info($e);
            }
        }

        return $success;
    }

    private function unique_inArray($array)
    {
        try {
            $temp_array = [];
            $i = 0;
            $key_array = [];

            foreach (array_reverse($array) as $val) {
                $reservation_number = '';
                if (
                    isset($val->ReservationNumber) &&
                    is_string($val->ReservationNumber) &&
                    ! empty($val->ReservationNumber)
                ) {
                    $reservation_number = $val->ReservationNumber;
                }

                if (! in_array($reservation_number, $key_array)) {
                    $key_array[] = $reservation_number;
                    $temp_array[] = $val;
                }
                $i++;
            }

            return array_reverse($temp_array);
        } catch (\Exception $e) {
            return [
                'error' => $e,
            ];
        }
    }

    public function HousekeepingStatus($hotel_id, $user_id, $data)
    {
        $success = false;

        if (is_array($this->hsk_status)) {
            //DB::beginTransaction();
            try {
                // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
                //     \Log::info('------------------Maestro entro al HousekeepingStatus ----------------------');
                //     \Log::info('data');
                //     \Log::info(json_encode($data));
                //     \Log::info('----------------------------------------');
                // }
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
                $HousekeepingData['rooms'] = [];

                foreach ($hkd as $h) {
                    if (
                        (isset($h->RoomCode) && (is_string($h->RoomCode) && ! empty($h->RoomCode))) && (isset($h->RoomStatus) && (is_string($h->RoomStatus) && ! empty($h->RoomStatus))) && (isset($h->HousekeepingStatus) && (is_string($h->HousekeepingStatus) && ! empty($h->HousekeepingStatus)))
                    ) {
                        $location = $h->RoomCode;
                        $room = $this->findRoomId($hotel_id, $user_id, $location);
                        $_d['room_id'] = $room['room_id'];

                        if (isset($this->hsk_status[strtoupper($h->HousekeepingStatus)])) {
                            $_d['hk_status'] = $this->hsk_status[strtoupper($h->HousekeepingStatus)]['codes'][0]['hk_status'];
                            $HousekeepingData['rooms'][] = $_d;
                        }
                    }
                }

                if (count($HousekeepingData['rooms']) > 0) {
                    if (strpos(url('/'), 'api-dev') !== false) {
                        $url = 'https://integrations.mynuvola.com/index.php/housekeeping/pmsHKChange';
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

                //DB::commit();
                $success = true;
            } catch (\Exception $e) {
                $error = $e;
                $success = false;
                //DB::rollback();
                \Log::info('Error HousekeepingStatus:');
                \Log::info($e);
            }
        }

        return $success;
    }

    private function findRoomId($hotel_id, $staff_id, $location)
    {
        // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
        //     \Log::info('------------------Maestro entro al findRoomId ----------------------');
        //     \Log::info('data');
        //     \Log::info($location);
        //     \Log::info('----------------------------------------');
        // }

        if (is_numeric($location)) {
            $room = HotelRoom::where('hotel_id', $hotel_id)
                ->where('location', $location)
                ->where('active', 1)
                ->orderBy('room_id', 'ASC')
                ->first();

            if ($room === null) {
                $l = strlen($location);
                if ($l == 3) {
                    $location = "0$location";
                } elseif ($l == 4) {
                    $sub = substr($location, 0, 1);
                    if ($sub === '0') {
                        $location = substr($location, 1);
                    }
                }
            }
            $room = HotelRoom::where('hotel_id', $hotel_id)
                ->where('location', $location)
                ->where('active', 1)
                ->orderBy('room_id', 'ASC')
                ->first();
        } else {
            $room = HotelRoom::where('hotel_id', $hotel_id)
                ->where('location', $location)
                ->where('active', 1)
                ->orderBy('room_id', 'ASC')
                ->first();
        }

        if ($room) {
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

    private function roomType($hotel_id, $staff_id, $location, $name_type)
    {
        // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
        //     \Log::info('------------------Maestro entro al roomType ----------------------');
        //     \Log::info('data');
        //     \Log::info($location);
        //     \Log::info('----------------------------------------');
        // }
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

    private function Offmarket($hotel_id, $user_id, $data)
    {
        // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
        //     \Log::info('------------------Maestro entro al Offmarket ----------------------');
        //     \Log::info('data');
        //     \Log::info(json_encode($data));
        //     \Log::info('----------------------------------------');
        // }
        $success = false;
        if (is_array($this->hsk_status)) {
            DB::beginTransaction();
            try {
                $offMarket = [];
                if (is_array($data->Rooms->OffmarketData)) {
                    $offMarket = $data->Rooms->OffmarketData;
                } else {
                    $offMarket[] = $data->Rooms->OffmarketData;
                }
                $__data = [];
                $comment = '';
                if (count($__data) > 0) {
                    foreach ($__data as $key => $d) {
                        if (isset($d) && is_string($d)) {
                            $comment = "$comment $d";
                        }
                    }
                }

                foreach ($offMarket as $key => $value) {
                    if (
                        (is_string($value->RoomCode) && ! empty($value->RoomCode))
                        && (is_string($value->StartDate) && ! empty($value->StartDate))
                        && (is_string($value->EndDate) && ! empty($value->EndDate))
                        && (is_string($value->OffmarketFlag) && ! empty($value->OffmarketFlag))
                        && (is_string($value->OutOfInventoryFlag) && ! empty($value->OutOfInventoryFlag))
                        && (is_string($value->OffmarketKey) && ! empty($value->OffmarketKey))
                    ) {
                        $location = $value->RoomCode;
                        $room = $this->findRoomId($hotel_id, $user_id, $location);
                        if ($room) {
                            $room_id = $room['room_id'];
                            $this->__offmarket(
                                $hotel_id,
                                $user_id,
                                $room_id,
                                $value->StartDate,
                                $value->EndDate,
                                $value->OffmarketFlag,
                                $value->OutOfInventoryFlag,
                                $comment
                            );
                        }
                    }
                }
                DB::commit();
            } catch (\Exception $e) {
                \Log::info('Error Offmarket:');
                \Log::info($e);
                DB::rollback();
            }
        }

        return $success;
    }

    private function __offmarket(
        $hotel_id,
        $user_id,
        $room_id,
        $start_date,
        $end_date,
        $OffmarketFlag = 'true',
        $OutOfInventoryFlag = 'true',
        $comment = ''
    ) {
        $end_date = date('Y-m-d H:i:s', strtotime($end_date.' +30 days'));
        $reason = 'Room placed Off Market';
        $hskReason = HousekeepingReasons::where('reason', $reason)->where('hotel_id', $hotel_id)->first();

        if (is_null($hskReason)) {
            $hskReason = HousekeepingReasons::create([
                'hotel_id' => $hotel_id,
                'reason_type' => 3,
                'reason' => $reason,
                'creatd_by' => $user_id,
                'created_on' => date('Y-m-d H:i:s'),
                'is_default' => 0,
                'is_active' => 1,
            ]);
        }

        $reason_id = $hskReason->reason_id;

        $HotelRoomsOut = HotelRoomsOut::where('hotel_id', $hotel_id)
            ->where('room_id', $room_id)
            ->where('is_close', 0)
            ->orderBy('room_out_id', 'DESC')
            ->first();

        if ($HotelRoomsOut) {
            $data_to_update = [];
            if ($OffmarketFlag == 'false' && $OutOfInventoryFlag == 'false') {
                $data_to_update = [
                    'is_close' => 1,
                    'end_date' => date('Y-m-d H:i:s'),
                    'updated_by' => $user_id,
                    'updated_on' => date('Y-m-d H:i:s'),
                ];
            } elseif ($OffmarketFlag == 'true' && $OutOfInventoryFlag == 'false') {
                $data_to_update = [
                    'hk_reasons_id' => $reason_id,
                    'is_close' => 0,
                    'status' => 1,
                    'start_date' => (new DateTime($start_date))->format('Y-m-d H:i:s'),
                    'end_date' => (new DateTime($end_date))->format('Y-m-d H:i:s'),
                    'updated_by' => $user_id,
                    'updated_on' => date('Y-m-d H:i:s'),
                ];
            } else {
                $data_to_update = [
                    'hk_reasons_id' => $reason_id,
                    'is_close' => 0,
                    'status' => 2,
                    'start_date' => (new DateTime($start_date))->format('Y-m-d H:i:s'),
                    'end_date' => (new DateTime($end_date))->format('Y-m-d H:i:s'),
                    'updated_by' => $user_id,
                    'updated_on' => date('Y-m-d H:i:s'),
                ];
            }
            $HotelRoomsOut->fill($data_to_update);
            $HotelRoomsOut->save();
        } else {
            HotelRoomsOut::create([
                'room_id' => $room_id,
                'hotel_id' => $hotel_id,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'status' => 1,
                'hk_reasons_id' => $reason_id,
                'created_by' => $user_id,
                'created_on' => date('Y-m-d H:i:s'),
                'comment' => $comment,
                'is_active' => 1,
            ]);
        }
    }

    private function proccessString($string, $replace_data = null)
    {
        if ($replace_data) {
            return (isset($string) && is_string($string)) ? str_replace($replace_data['replace'], $replace_data['by'], addslashes($string)) : '';
        }

        return (isset($string) && is_string($string)) ? addslashes($string) : '';
    }

    private function validateAngelStatus($hotel_id)
    {
        // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
        //     \Log::info('------------------Maestro entro al validateAngelStatus ----------------------');
        //     \Log::info('----------------------------------------');
        // }
        $query =
            "SELECT rp.view from role_permission rp 
            INNER JOIN menus m ON m.menu_id = 22
            INNER JOIN roles r ON r.hotel_id = $hotel_id AND r.role_name = 'Hotel Admin'
            WHERE rp.role_id = r.role_id AND rp.menu_id = m.menu_id
            LIMIT 1";

        $result = DB::select($query);
        if ($result && count($result) > 0) {
            return $result[0]->view;
        }

        return 0;
    }

    private function saveLogTracker($__log_tracker)
    {
        $track_id = LogTracker::create($__log_tracker)->track_id;

        return $track_id;
    }

    /**
     * Enviar mensajes de Angel y Welcome message a un huespedes
     *
     * @param  int  $hotel_id           // Hotel id
     * @param  int  $guest_id           // guest id
     * @param  int  $staff_id           // Staff id
     * @param  string  $email              // Email del huesped
     * @param  string  $phone              // Phone del huesped
     * @param  bool  $back               // Si el huesped ya ha tenido estadias se le envia un Welcome Back
     * @param  bool  $welcome            // Enviar Welcome Message de sms chat
     * @param  bool  $angel              // Enviar invitacion de Angel
     */
    public function sendMessages($hotel_id, $guest_id, $staff_id, $email = '', $phone = '', $back = false, $welcome = true, $angel = true, $check_in_date = null, $check_out_date = null)
    {
        //blocked hotels
        $blocked_hotels_angel = [
            240,    // proximity_hotel
            241,    // o_henry_hotel
            231,    // grande_shores_condo
        ];
        $blocked_hotels_welcome = [
            230,    // towers_at_north_myrtle_beach
            232,    // horizon_at_77th
            240,    // proximity_hotel
        ];

        $welcome_send = [
            230, 198, 216, 232, 217, 231, 208,
        ];

        // validate if already send a WELCOME MESSAGE bettewn dates checkin and checkout
        if ($check_in_date != null && $check_out_date != null) {
            $sms_chat_validate = SmsChat::where('hotel_id', $hotel_id)->where('guest_id', $guest_id)
                ->where('type', 'WELCOME')->whereBetween('created_on', [$check_in_date, $check_out_date])->first();
        }

        if ($sms_chat_validate) {
            return 'Welcome Send false';
        }

        try {
            // Validar que el hotel tenga el modulo de Angel activo.
            $str_query = '';
            if ($angel) {
                $str_query .= "SELECT 'angel' type, rp.view access, g.angel_status FROM role_permission rp INNER JOIN menus m ON m.menu_id = 22 INNER JOIN roles r ON r.is_active = 1 AND r.hotel_id = $hotel_id AND lower(r.role_name) = 'hotel admin' INNER JOIN guest_registration g on g.hotel_id = r.hotel_id and g.guest_id = $guest_id WHERE rp.role_id = r.role_id AND rp.menu_id = m.menu_id";
            }
            if ($angel && $welcome) {
                $str_query .= ' UNION ';
            }
            if ($welcome) {
                $str_query .= "SELECT 'schat' type, rp.view access, ''             FROM role_permission rp INNER JOIN menus m ON m.menu_id = 30 INNER JOIN roles r ON r.is_active = 1 AND r.hotel_id = $hotel_id AND lower(r.role_name) = 'hotel admin' WHERE rp.role_id = r.role_id AND rp.menu_id = m.menu_id";
            }

            if (! empty($str_query)) {
                $result = DB::select($str_query);
                if (count($result) > 0) {
                    $send_angel = false;
                    $send_welcome = false;

                    foreach ($result as $kResult => $vResult) {
                        if ($vResult->type == 'angel' && $vResult->access == 1) {
                            $send_angel = $vResult->angel_status == 1 ? true : false;
                        }
                        if ($vResult->type == 'schat' && $vResult->access == 1) {
                            $send_welcome = true;
                        }
                    }

                    $client = new \GuzzleHttp\Client(['verify' => false]);

                    if (strpos(url('/'), 'api-dev') !== false) {
                        $url_app = 'https://integrations.mynuvola.com/index.php/send_invitations';
                    } else {
                        $url_app = 'https://hotel.mynuvola.com/index.php/send_invitations';
                    }

                    $rs = [];
                    if ($send_angel) {
                        if (! in_array($hotel_id, $blocked_hotels_angel)) {
                            $response = $client->request('POST', $url_app, [
                                'form_params' => [
                                    'hotel_id' => $hotel_id,
                                    'guest_id' => '',
                                    'staff_id' => '',
                                    'type' => 'angel',
                                    'email' => $email,
                                    'phone' => $phone,
                                ],
                            ]);
                            $response = $response->getBody()->getContents();

                            $rs['angel'] = $response;
                        } else {
                            $rs['send_angel_blocked'] = true;
                        }
                    }
                    if (in_array($hotel_id, $welcome_send)) {
                        $hotel_staff = Hotel::find($hotel_id);
                        $staff_id = $hotel_staff->account ? $hotel_staff->account : $staff_id;
                    }
                    if ($send_welcome) {
                        if (! in_array($hotel_id, $blocked_hotels_welcome)) {
                            $response = $client->request('POST', $url_app, [
                                'form_params' => [
                                    'hotel_id' => $hotel_id,
                                    'guest_id' => $guest_id,
                                    'staff_id' => $staff_id,
                                    'type' => 'welcome',
                                    'email' => $email,
                                    'phone' => $phone,
                                    'back' => $back,
                                ],
                            ]);
                            $response = $response->getBody()->getContents();

                            $rs['welcome'] = $response;
                        } else {
                            $rs['send_welcome_blocked'] = true;
                        }
                    }

                    $rs['send_angel'] = $send_angel;
                    $rs['send_welcome'] = $send_welcome;

                    return $rs;
                }

                return 'No record found '.$hotel_id;
            }

            return 'Sql no generated';
        } catch (\Exception $e) {
            \Log::info('Error al enviar invitaciones:');
            \Log::info($e);

            return 'Error show laravel.log';
        }
    }

    private function LogReservationList($hotel_id, $data)
    {
        try {
            // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
            //     \Log::info('------------------Maestro entro al LogReservationList ----------------------');
            //     \Log::info(json_encode($data));
            //     \Log::info('----------------------------------------');
            // }
            if (! is_array($data->Reservations->ReservationData)) {
                $data->Reservations->ReservationData = [
                    $data->Reservations->ReservationData,
                ];
            }

            foreach ($data->Reservations->ReservationData as $rl) {
                $rl->hotel_id = $hotel_id;
                $rl->HotelId = $data->HotelId;
                $rl->PasswordHash = $data->PasswordHash;
                $rl->Salutation = is_object($rl->Salutation) ? '' : $rl->Salutation;
                $rl->MiddleInitial = is_object($rl->MiddleInitial) ? '' : $rl->MiddleInitial;
                $rl->MiddleName = is_object($rl->MiddleName) ? '' : $rl->MiddleName;
                $rl->Address2 = is_object($rl->Address2) ? '' : $rl->Address2;
                $rl->EmailAddress = is_object($rl->EmailAddress) ? '' : $rl->EmailAddress;
                $rl->Email = is_object($rl->Email) ? '' : $rl->Email;
                $rl->Language = is_object($rl->Language) ? '' : $rl->Language;
                $rl->AccountNumber = is_object($rl->AccountNumber) ? '' : $rl->AccountNumber;
                $rl->Phone = is_object($rl->Phone) ? '' : $rl->Phone;
                $rl->Fax = is_object($rl->Fax) ? '' : $rl->Fax;
                $rl->Company = is_object($rl->Company) ? '' : $rl->Company;
                $rl->LoyaltyID = is_object($rl->LoyaltyID) ? '' : $rl->LoyaltyID;
                $rl->SpecialRequests = is_object($rl->SpecialRequests) ? '' : $rl->SpecialRequests;

                $rl->BuildingCode = '';
                $rl->RoomCode = '';
                $rl->RoomTypeCode = '';
                $rl->RoomTypeDescription = '';

                if (isset($data->Room)) {
                    if (isset($data->Room->BuildingCode) && is_string($data->Room->BuildingCode) && ! empty($data->Room->BuildingCode)) {
                        $rl->BuildingCode = $data->Room->BuildingCode;
                    }
                    if (isset($data->Room->RoomCode) && is_string($data->Room->RoomCode) && ! empty($data->Room->RoomCode)) {
                        $rl->RoomCode = $data->Room->RoomCode;
                    }
                    if (isset($data->Room->RoomTypeCode) && is_string($data->Room->RoomTypeCode) && ! empty($data->Room->RoomTypeCode)) {
                        $rl->RoomTypeCode = $data->Room->RoomTypeCode;
                    }
                    if (isset($data->Room->RoomTypeDescription) && is_string($data->Room->RoomTypeDescription) && ! empty($data->Room->RoomTypeDescription)) {
                        $rl->RoomTypeDescription = $data->Room->RoomTypeDescription;
                    }
                }

                $rl->ReservationText = '';

                if (isset($data->ReservationText) && isset($data->ReservationText->Text)) {
                    $Text = $data->ReservationText->Text;
                    $arr_comment = is_array($Text) ? $Text : [$Text];
                    $comment = '';
                    foreach ($arr_comment as $key => $value) {
                        $comment .= (is_string($value) && ! empty($value)) ? "$value " : '';
                    }
                    $rl->ReservationText = $comment;
                }
                // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
                //     \Log::info('------------------reservation text ----------------------');
                //     \Log::info($rl->ReservationText);
                // }
                $rl->GroupReservation = '';
                if (isset($data->Group)) {
                    if (
                        isset($data->Group->GroupReservation) &&
                        is_string($data->Group->GroupReservation) &&
                        ! empty($data->Group->GroupReservation)
                    ) {
                        $rl->GroupReservation = $data->Room->GroupReservation;
                    }
                    if (
                        isset($data->Group->Name) &&
                        is_string($data->Group->Name) &&
                        ! empty($data->Group->Name)
                    ) {
                        $rl->GroupName = $data->Room->Name;
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error("Error in LogReservationList $hotel_id");
            \Log::error($e);
        }
    }

    public function HousekeepingStatusSync($hotel_id, $user_id, $data)
    {
        if (is_array($this->hsk_status)) {

            //DB::beginTransaction();
            try {
                // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
                //     \Log::info('------------------Maestro entro al HousekeepingStatusSync ----------------------');
                //     \Log::info(json_encode($data));
                //     \Log::info('----------------------------------------');
                // }
                $h = ($data->RoomData->Room);
                $HousekeepingData = [];
                $HousekeepingData['hotel_id'] = $hotel_id;
                $HousekeepingData['staff_id'] = $user_id;
                $HousekeepingData['rooms'] = [];
                if (
                    (isset($h->RoomCode) && (is_string($h->RoomCode) && ! empty($h->RoomCode))) && (isset($h->RoomStatus) && (is_string($h->RoomStatus) && ! empty($h->RoomStatus))) && (isset($h->HousekeepingStatus) && (is_string($h->HousekeepingStatus) && ! empty($h->HousekeepingStatus)))
                ) {
                    $location = $h->RoomCode;
                    $room = $this->findRoomId($hotel_id, $user_id, $location);
                    $_d['room_id'] = $room['room_id'];

                    if ($h->RoomStatus === 'offmarket') {
                        $this->__offmarket($hotel_id, $user_id, $location, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'));
                    }

                    if (isset($this->hsk_status[strtoupper($h->HousekeepingStatus)])) {
                        $_d['hk_status'] = $this->hsk_status[strtoupper($h->HousekeepingStatus)]['codes'][0]['hk_status'];
                        $HousekeepingData['rooms'][] = $_d;
                    }
                }

                if (count($HousekeepingData['rooms']) > 0) {
                    if (strpos(url('/'), 'api-dev') !== false) {
                        $url = 'https://integrations.mynuvola.com/index.php/housekeeping/pmsHKChange';
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

                //DB::commit();
                $success = true;
            } catch (\Exception $e) {
                $error = $e;
                $success = false;
                //DB::rollback();
                \Log::info('Error HousekeepingStatus:');
                \Log::info($e);
            }

            return true;
        }
    }

    public function UpdateStateReservation($hotel_id, $user_id, $data)
    {
        // if( $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
        //     \Log::info('------------------Maestro entro al UpdateStateReservation ----------------------');
        //     \Log::info(json_encode($__data));
        //     \Log::info('----------------------------------------');
        // }
        $date = date('Y-m-d H:i:s');
        $room = $this->findRoomId($hotel_id, $user_id, $data->RoomData->Room->RoomCode);
        $reservations = GuestCheckinDetails::where('room_no', $room['room_id'])->where('hotel_id', $hotel_id)
            ->where('status', 1)->where(function ($query) {
                $query->where('check_out', '<', date('Y-m-d H:i:s'))
                    ->orWhere('check_in', '<', date('Y-m-d H:i:s'));
            })->get();
        foreach ($reservations as $reservation) {
            if ($reservation->reservation_status == 1) {
                $reservation->status = 0;
                $reservation->reservation_status = 3;
                if ($reservation->check_out > $date) {
                    $reservation->check_out = $date;
                }
            } elseif ($reservation->reservation_status == 0) {
                $reservation->status = 0;
                $reservation->reservation_status = 2;
            }
            $reservation->save();
        }
    }

    public function getSaltToPMS($url, $pms_hotel_id)
    {
        $xml =
            '<?xml version="1.0" encoding="utf-8"?>'.
            '<Request>'.
            '<Version>1.0</Version>'.
            '<HotelId>'.$pms_hotel_id.'</HotelId>'.
            '<GetSalt/>'.
            '</Request>';
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => ['Content-Type: application/xml', 'cache-control: no-cache'],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            \Log::info($err);

            return $err;
        } else {
            \Log::info($xml);
            $xml = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $response);

            $xml = simplexml_load_string($xml);
            $str_json = json_encode($xml);
            $json = json_decode($str_json);

            return $json->Salt;
        }

        return null;
    }

    public function makePasswordHash($url, $pms_hotel_id, $agreed_upon_key)
    {
        $salt = $this->getSaltToPMS($url, $pms_hotel_id);
        $PasswordHash = hash('sha256', $agreed_upon_key.$salt);

        return $PasswordHash;
    }

    public function BuildRequestSync($__room, $pms_hotel_id, $salt)
    {
        $xml = [
            'Version' => '1.0',
            'HotelId' => $pms_hotel_id,
            'PasswordHash' => $salt,
            'Action' => 'RoomInquiry',
            'RequestData' => [
                'RoomCode' => $__room['room_location'],
                'SalesLocation' => '1',
            ],
        ];

        return ArrayToXml::convert($xml, 'Request');
    }

    public function SendRequestSync($xml, $url)
    {
        if (! empty($url)) {
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $xml,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/xml',
                ],
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if ($err) {
                \Log::error($err);

                return $err;
            } else {
                return $response;
            }
        }
    }

    public function GenerateSync($hotel_id, $room_id = null)
    {
        $url = $this->maestroIntegration->config['url'];
        $pms_hotel_id = $this->maestroIntegration->config['hotel_id'];
        $agreed_upon_key = $this->maestroIntegration->config['agreed_upon_key'];

        $salt = $this->makePasswordHash($url, $pms_hotel_id, $agreed_upon_key);

        if (! empty($salt)) {
            $rooms = \App\Models\HotelRoom::where('hotel_id', $hotel_id)
                ->where('active', 1)
                ->where('is_common_area', 0);

            if (! is_null($room_id)) {
                $rooms = $rooms->where('room_id', $room_id);
            }

            $rooms = $rooms->get();

            $__rooms = [];

            \Log::info('Total: '.count($rooms));

            foreach ($rooms as $key => $room) {
                try {
                    $__room = [
                        'room_location' => $room->location,
                        'room_id' => $room->room_id,
                        'response' => '',
                    ];
                    $xml_request = $this->BuildRequestSync($__room, $pms_hotel_id, $salt);
                    $xml_response = $this->SendRequestSync($xml_request, $url);

                    $xml = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $xml_response);
                    $xml = simplexml_load_string($xml);
                    $str_json = json_encode($xml);
                    $json = json_decode($str_json);

                    $__room['response'] = $json;
                    \Log::error(json_encode($__room));
                    if ($__room['response']->Status != 'failure') {
                        $__rooms[] = $__room;
                    }
                } catch (\Exception $e) {
                    \Log::error("$e");
                }
                \Log::info(count($rooms).'/'.$key);
            }

            return $__rooms;
        }
    }

    public function guestChatDummy($guest_id, $phone, $hotel_id)
    {
        try {
            // if(  $hotel_id == '276' || $hotel_id == '277' || $hotel_id == '278' || $hotel_id == '279') {
            //     \Log::info('------------------Maestro entro al guestChatDummy ----------------------');
            //     \Log::info('----------------------------------------');
            // }
            $guest = GuestRegistration::where('hotel_id', $hotel_id)->where('phone_no', 'LIKE', "%$phone%")->where('is_active', 1)->get();
            foreach ($guest as  $value) {
                if (empty($value->firstname) && empty($value->lastname) && empty($value->email_address) && empty($value->address)) {
                    $integration = IntegrationsGuestInformation::where('guest_id', $value->guest_id)->first();
                    if (! $integration) {
                        $sms = SmsChat::where('guest_id', $value->guest_id)->get();
                        foreach ($sms as  $value2) {
                            $value2->guest_id = $guest_id;
                            $value2->save();
                        }
                        $value->phone_no = '';
                        $value->comment = 'GuestChat dummy';
                        $value->save();
                        // \Log::info('cambio ' . $phone);
                        // \Log::info(json_encode($value));
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::alert('guestChatDummy');
            \Log::error($e);
        }
    }
}
