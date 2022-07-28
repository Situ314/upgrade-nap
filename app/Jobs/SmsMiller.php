<?php

namespace App\Jobs;

use App\Models\GuestCheckinDetails;
use App\Models\GuestRegistration;
use App\Models\HotelRoom;
use App\Models\HotelRoomsOut;
use App\Models\HousekeepingCleanings;
use App\Models\HousekeepingTimeline;
use App\Models\IntegrationsActive;
use App\Models\IntegrationsGuestInformation;
use App\Models\LogTracker;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Validator;

class SmsMiller implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $hotel_id;

    private $staff_id;

    private $type;

    private $data;

    private $now;

    private $config;

    private $HotelHousekeepingConfig;

    private $SuitesRooms;

    private $ExtraStatus;

    private $miller_suites_message = false;

    private $count_message = 0;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($hotel_id, $staff_id, $type, $data, $config, $now)
    {
        // \Log::info(json_encode($data));
        $this->hotel_id = $hotel_id;
        $this->staff_id = $staff_id;
        $this->type = $type;
        $this->data = $data;
        $this->config = $config;
        $this->HotelHousekeepingConfig = $config['housekeeping'];
        $this->ExtraStatus = $config['extra_status'];
        $this->now = $now;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (method_exists($this, $this->type)) {
            $method = $this->type;
            if ($this->type == 'reservation') {
                $this->$method($this->data);
            } else {
                $this->$method();
            }
        }
    }

    public function reservation($data)
    {
        // // DB::beginTransaction();
        try {
            foreach ($data as $value) {
                if ($this->validateReservationData($value)) {
                    $if_suites = $this->getIfSuite($value['location'], $value['suites']);
                    if ($if_suites) {
                        $check_in = $this->removeReservation($value['reservation_number']);
                        $another_suite_reservation = $this->removeAnotherSuiteReservation($value['reservation_number'], $value['suites'][$value['location']]);
                        if ($check_in) {
                            $value['check_in'] = $check_in;
                        }
                        $__data = [];
                        foreach ($if_suites as $value2) {
                            $aux = $value;
                            $aux['location'] = $value2;
                            $aux['reservation_number'] = $value['reservation_number'].'_'.$value2;
                            $__data[] = $aux;
                        }
                        // $data_save = $this->data;
                        // $this->data = $__data;
                        $this->miller_suites_message = true;
                        $this->reservation($__data);
                        $this->miller_suites_message = false;
                    // $this->count_message = 0;
                        // $this->data = $data_save;
                    } else {
                        $check_in = $this->removeSuitesReservation($value['reservation_number']);
                        if ($check_in) {
                            $value['check_in'] = $check_in;
                        }
                        $reservation_number = $value['reservation_number'];
                        $hotel_id = $this->hotel_id;

                        $reservation = GuestCheckinDetails::where('hotel_id', $hotel_id)->where('reservation_number', trim($reservation_number))->first();

                        if ($reservation) {
                            $this->editReservation($reservation, $value);
                        } else {
                            $guest_integration = IntegrationsGuestInformation::where('hotel_id', $hotel_id)->where('guest_number', trim($value['guest_number']))->first();
                            $guest = null;
                            if (! $guest_integration) {
                                $guest = $this->registerGuest($value);
                            } else {
                                $guest = GuestRegistration::find($guest_integration->guest_id);
                            }

                            if ($guest) {
                                $send_message_miller = false;
                                $room = $value['location'] == '' ? 0 : $this->getRoom($value['location']);

                                $reservation = [
                                    'guest_id' => $guest->guest_id,
                                    'hotel_id' => $this->hotel_id,
                                    'room_no' => $room == 0 ? $room : $room['room_id'],
                                    'check_in' => $value['check_in'],
                                    'check_out' => $value['check_out'],
                                    'comment' => '',
                                    'status' => $value['status'],
                                    'main_guest' => 0,
                                    'reservation_status' => $value['reservation_status'],
                                    'reservation_number' => $value['reservation_number'],
                                ];

                                $__reservation = GuestCheckinDetails::create($reservation);
                                if ($__reservation->reservation_status == 1 && date('Y-m-d', strtotime($value['check_in'])) == date('Y-m-d')) {
                                    $send_message_miller = true;
                                }
                                // \Log::alert(json_encode($__reservation));
                                $this->saveLogTracker([
                                    'hotel_id' => $this->hotel_id,
                                    'module_id' => 8,
                                    'action' => 'add',
                                    'prim_id' => $__reservation->sno,
                                    'staff_id' => $this->staff_id,
                                    'date_time' => date('Y-m-d H:i:s'),
                                    'comments' => 'Add Reservation',
                                    'type' => 'API-OPERA',
                                ]);

                                $hsk_cleanning = HousekeepingCleanings::where('hotel_id', $this->hotel_id)
                                    ->where('room_id', $__reservation->room_no)
                                    ->where('assigned_date', date('Y-m-d', strtotime($this->now)))
                                    ->where('is_active', 1)->first();
                                if ($hsk_cleanning && $hsk_cleanning->guest_id != $__reservation->guest_id) {
                                    $hsk_cleanning->guest_id = $__reservation->guest_id;
                                    $hsk_cleanning->checkin_details_id = $__reservation->sno;
                                    $hsk_cleanning->front_desk_status = 2;
                                    $hsk_cleanning->save();
                                }
                                // DB::commit();
                                if ($send_message_miller) {
                                    $__GuestCheckinDetails = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('guest_id', $__reservation->guest_id)->get();
                                    $back = false;

                                    if (count($__GuestCheckinDetails) > 1) {
                                        $back = true;
                                    }
                                    $GuestRegistration = GuestRegistration::find($__reservation->guest_id);
                                    $rs = $this->sendMessages(
                                        $this->hotel_id,
                                        $__reservation->guest_id,
                                        1,
                                        $GuestRegistration->email_address,
                                        $GuestRegistration->phone_no,
                                        $back
                                    );
                                    $this->saveLogTracker([
                                        'module_id' => 0,
                                        'action' => 'send_mail',
                                        'prim_id' => $__reservation->guest_id,
                                        'staff_id' => 1,
                                        'date_time' => date('Y-m-d H:i:s'),
                                        'comments' => json_encode([
                                            'data' => [
                                                'hotel_id' => $this->hotel_id,
                                                'guest_id' => $__reservation->guest_id,
                                                'staff_id' => 1,
                                                'email_address' => $GuestRegistration->email_address,
                                                'phone_no' => $GuestRegistration->phone_no,
                                                'back' => $back,
                                            ],
                                            'rs' => $rs,
                                        ]),
                                        'hotel_id' => $this->hotel_id,
                                        'type' => 'API-OPERA',
                                    ]);
                                }
                                // return $__reservation;
                            }
                        }
                    }
                } else {
                    \Log::critical('SMS Reservation NOT register');
                    \Log::critical(json_encode($value));
                }
                // DB::commit();
            }
        } catch (\Exception $e) {
            // DB::rollback();
            \Log::error('error SMS Miller');
            \Log::error($e);

            return null;
        }
    }

    public function editReservation(GuestCheckinDetails $reservation, $data)
    {
        $send_message_miller = false;
        // DB::beginTransaction();
        try {
            $__update = '';
            $room = ($data['location'] == '' || $data['location'] == null) ? ['room_id' => 0, 'location' => 0] : $this->getRoom($data['location']);
            $data['room_no'] = $room['room_id'];

            if (
                $data['room_no'] != null && $data['room_no'] != '' &&
                $reservation->reservation_status == 1 &&
                $data['reservation_status'] == 1 &&
                $reservation->room_no != $data['room_no']
            ) {
                $reservation->status = 0;
                $reservation->reservation_status = 5;
                $reservation->reservation_number = $reservation->reservation_number.'_RM';
                $check_out = $reservation->check_out;
                $reservation->check_out = $this->now;

                $data['check_in'] = $reservation->check_out;
                $data['check_out'] = $check_out;
                $reservation->save();
                // \Log::info('ROOM MOVE');
                // $aux_data = $this->data;
                // $this->data = [$data];
                $__reservation = $this->reservation([$data]);
                // $this->data = $aux_data;
                if ($__reservation) {
                    \Log::alert('entro RM');
                    $this->RoomMove($reservation, $__reservation);
                }
            } else {
                // \Log::critical($reservation->reservation_status != $data['reservation_status']);
                // \Log::critical($data['reservation_status'] == 1);
                // \Log::critical($data['status'] == 1);
                // \Log::critical(date('Y-m-d', strtotime($data["check_in"])) == date('Y-m-d'));
                // \Log::critical($data);
                if (
                    $reservation->reservation_status != $data['reservation_status'] &&
                    $data['reservation_status'] == 1 && $data['status'] == 1 && date('Y-m-d', strtotime($data['check_in'])) == date('Y-m-d')
                ) {
                    $send_message_miller = true;
                }
                if ($reservation->reservation_status != 1 && $reservation->check_in != $data['check_in']) {
                    $__update .= "check_in: $reservation->check_in to ".$data['check_in'].', ';
                    $reservation->check_in = $data['check_in'];
                }
                if ($reservation->check_out != $data['check_out'] && $reservation->reservation_status != 3) {
                    $__update .= "check_out: $reservation->check_out to ".$data['check_out'].', ';
                    $reservation->check_out = $data['check_out'];
                }
                if ($reservation->status != $data['status']) {
                    $__update .= "status: $reservation->status to ".$data['status'].', ';
                    $reservation->status = $data['status'];
                }
                if ($reservation->reservation_status != $data['reservation_status']) {
                    $__update .= "reservation_status: $reservation->reservation_status to ".$data['reservation_status'].', ';
                    $reservation->reservation_status = $data['reservation_status'];
                }
                if ($reservation->room_no != $data['room_no']) {
                    $__update .= "room_no: $reservation->room_no to ".$data['room_no'].', ';
                    $reservation->room_no = $data['room_no'];
                }
                $_guest = IntegrationsGuestInformation::where('hotel_id', $this->hotel_id)->where('guest_number', trim($data['guest_number']))->first();
                if (! $_guest) {
                    $__guest = $this->registerGuest($data);
                    $__update .= "guest_id: $reservation->guest_id to ".$__guest->guest_id.', ';
                    $reservation->guest_id = $__guest->guest_id;
                } else {
                    if ($_guest && $_guest->guest_id != $reservation->guest_id) {
                        $reservation->guest_id = $_guest->guest_id;
                        $__update .= "guest_id: $reservation->guest_id to ".$_guest->guest_id.', ';
                    }
                }

                if (! empty($__update)) {
                    $reservation->save();
                    $this->saveLogTracker([
                        'hotel_id' => $this->hotel_id,
                        'module_id' => 8,
                        'action' => 'update',
                        'prim_id' => $reservation->sno,
                        'staff_id' => $this->staff_id,
                        'date_time' => date('Y-m-d H:i:s'),
                        'comments' => "Update Reservation information: $__update",
                        'type' => 'API-OPERA',
                    ]);
                }
            }
            $guest = GuestRegistration::find($reservation->guest_id);
            $this->editGuest($guest, $data);
            // DB::commit();
            if ($send_message_miller) {
                $__GuestCheckinDetails = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('guest_id', $reservation->guest_id)->get();
                $back = false;

                if (count($__GuestCheckinDetails) > 1) {
                    $back = true;
                }
                $GuestRegistration = GuestRegistration::find($reservation->guest_id);
                $rs = $this->sendMessages(
                    $this->hotel_id,
                    $reservation->guest_id,
                    1,
                    $GuestRegistration->email_address,
                    $GuestRegistration->phone_no,
                    $back
                );
                $this->saveLogTracker([
                    'module_id' => 0,
                    'action' => 'send_mail',
                    'prim_id' => $reservation->guest_id,
                    'staff_id' => 1,
                    'date_time' => date('Y-m-d H:i:s'),
                    'comments' => json_encode([
                        'data' => [
                            'hotel_id' => $this->hotel_id,
                            'guest_id' => $reservation->guest_id,
                            'staff_id' => 1,
                            'email_address' => $GuestRegistration->email_address,
                            'phone_no' => $GuestRegistration->phone_no,
                            'back' => $back,
                        ],
                        'rs' => $rs,
                    ]),
                    'hotel_id' => $this->hotel_id,
                    'type' => 'API-OPERA',
                ]);
            }
        } catch (\Exception $e) {
            // DB::rollback();
            \Log::error('error SMS Miller');
            \Log::error($e);
        }
    }

    public function registerGuest($__data)
    {
        // DB::beginTransaction();
        try {
            $igi = IntegrationsGuestInformation::where('hotel_id', $this->hotel_id)->where('guest_number', $__data['guest_number'])->first();
            if ($igi) {
                return GuestRegistration::find($igi->guest_id);
            }

            $angel_status = 0;
            if (! $this->getAngelStatus()) {
                $angel_status = 1;
            }
            $data = [
                'hotel_id' => $this->hotel_id,
                'firstname' => $__data['firstname'],
                'lastname' => $__data['lastname'],
                'email_address' => $__data['email_address'],
                'phone_no' => $__data['phone_no'],
                'address' => $__data['address'],
                'state' => $__data['state'],
                'zipcode' => $__data['zipcode'],
                'language' => '',
                'comment' => '',
                'city' => $__data['city'],
                'angel_status' => $angel_status,
                'is_active' => 1,
                'created_on' => $this->now,
                'created_by' => $this->staff_id,
            ];

            if ($__data['dob'] != '') {
                $data['dob'] = date('Y-m-d', strtotime($__data['dob']));
            }
            $guest = GuestRegistration::create($data);

            $guest_integration = [
                'hotel_id' => $this->hotel_id,
                'guest_id' => $guest->guest_id,
                'guest_number' => $__data['guest_number'],
            ];
            // \Log::info("guest_integration ---> ");
            // \Log::info($guest_integration);
            $guest_integration = IntegrationsGuestInformation::create($guest_integration);
            // DB::commit();
            return $guest;
        } catch (\Exception $e) {
            // DB::rollback();
            \Log::error('error SMS Miller');
            \Log::error($e);

            return null;
        }
    }

    public function editGuest(GuestRegistration $guest, $data)
    {
        // DB::beginTransaction();
        try {
            $__update = '';
            if ($guest->email_address != $data['email_address']) {
                $__update .= "email_address: $guest->email_address to ".$data['email_address'].', ';
                $guest->email_address = $data['email_address'];
            }
            if ($guest->phone_no != $data['phone_no']) {
                $__update .= "phone_no: $guest->phone_no to ".$data['phone_no'].', ';
                $guest->phone_no = $data['phone_no'];
            }
            if ($data['firstname'] != $guest->firstname) {
                $__update .= "firstname: $guest->firstname to ".$data['firstname'].', ';
                $guest->firstname = $data['firstname'];
            }
            if ($data['lastname'] != $guest->lastname) {
                $__update .= "lastname: $guest->lastname to ".$data['lastname'].', ';
                $guest->lastname = $data['lastname'];
            }
            if ($data['address'] != $guest->address) {
                $__update .= "address: $guest->address to ".$data['address'].', ';
                $guest->address = $data['address'];
            }
            if ($data['city'] != $guest->city) {
                $__update .= "city: $guest->city to ".$data['city'].', ';
                $guest->city = $data['city'];
            }
            if ($data['zipcode'] != $guest->zipcode) {
                $__update .= "zipcode: $guest->zipcode to ".$data['zipcode'].', ';
                $guest->zipcode = $data['zipcode'];
            }
            if ($data['state'] != $guest->state) {
                $__update .= "state: $guest->state to ".$data['state'].', ';
                $guest->state = $data['state'];
            }
            if ($__update != '') {
                $guest->updated_on = $this->now;
                $guest->updated_by = $this->staff_id;
                // \Log::info($guest);
                $guest->save();

                $this->saveLogTracker([
                    'hotel_id' => $this->hotel_id,
                    'module_id' => 8,
                    'action' => 'update',
                    'prim_id' => $guest->guest_id,
                    'staff_id' => $this->staff_id,
                    'date_time' => date('Y-m-d H:i:s'),
                    'comments' => 'Guest Update',
                    'type' => 'API-OPERA',
                ]);
            }
            // DB::commit();
        } catch (\Exception $e) {
            // DB::rollback();
            \Log::error('error SMS Miller');
            \Log::error($e);

            return null;
        }
    }

    public function getRoom($location)
    {
        $room = HotelRoom::where('hotel_id', $this->hotel_id)
            ->where(function ($query) use ($location) {
                return $query
                    ->where('location', $location)
                    ->orWhere('room_id', $location);
            })->first();

        if ($room) {
            return [
                'room_id' => $room->room_id,
                'room' => $room->location,
            ];
        } else {
            $room = HotelRoom::create([
                'hotel_id' => $this->hotel_id,
                'location' => $location,
                'created_by' => $this->staff_id,
                'created_on' => date('Y-m-d H:i:s'),
                'updated_by' => null,
                'updated_on' => null,
                'active' => 1,
                'angel_view' => 1,
                'device_token' => '',
            ]);

            $this->saveLogTracker([
                'hotel_id' => $this->hotel_id,
                'staff_id' => $this->staff_id,
                'prim_id' => $room->room_id,
                'module_id' => 17,
                'action' => 'add',
                'date_time' => date('Y-m-d H:i:s'),
                'comments' => '',
                'type' => 'API',
            ]);

            return [
                'room_id' => $room->room_id,
                'room' => $room->location,
            ];
        }
    }

    public function saveLogTracker($__log_tracker)
    {
        $track_id = LogTracker::create($__log_tracker)->track_id;

        return $track_id;
    }

    public function checkOut()
    {
        // DB::beginTransaction();
        try {
            $reservation_number = $this->data['reservation_number'];
            $hotel_id = $this->hotel_id;
            $_reservation = GuestCheckinDetails::where('hotel_id', $hotel_id)->where('reservation_number', 'LIKE', '%'.$reservation_number.'%')
                ->where('status', '!=', 0)->where('reservation_status', '!=', 3)->get();
            $guest_id_data = 0;
            if ($_reservation) {
                foreach ($_reservation as  $reservation) {
                    $reservation->check_out = $this->now;
                    $reservation->reservation_status = $this->data['reservation_status'];
                    $reservation->status = $this->data['status'];
                    $guest_id_data = 0;
                    $reservation->save();
                }
            }
            $this->saveLogTracker([
                'hotel_id' => $this->hotel_id,
                'module_id' => 8,
                'action' => 'update',
                'prim_id' => $guest_id_data,
                'staff_id' => $this->staff_id,
                'date_time' => date('Y-m-d H:i:s'),
                'comments' => 'Update Reservation information: RESERVATION_STATUS 1 TO 3, STATUS 1 TO 3',
                'type' => 'API-OPERA',
            ]);
            // DB::commit();
        } catch (\Exception $e) {
            // DB::rollback();
            \Log::error('error SMS Miller');
            \Log::error($e);

            return null;
        }
    }

    public function validateReservationData($data)
    {
        $resp = true;

        $Validator = Validator::make($data, [
            'reservation_number' => 'required',
            'guest_number' => 'required',
            'check_in' => 'required',
            'check_out' => 'required',
        ]);

        if ($Validator->fails()) {
            $resp = false;
        }

        if ($data['firstname'] == '' && $data['lastname'] == '') {
            $resp = false;
        }

        if ($data['status'] === '' || $data['reservation_status'] === '') {
            $resp = false;
        }

        if ($data['reservation_status'] === 1 && $data['location'] === '') {
            $resp = false;
        }

        return $resp;
    }

    public function RoomMove($reservation, $new_reservation)
    {
        $room_move = [
            'guest_id' => $reservation->guest_id,
            'current_room_no' => $reservation->room_no,
            'new_room_no' => $new_reservation->room_no,
            'hotel_id' => $this->hotel_id,
            'created_by' => $this->staff_id,
            'created_on' => $this->now,
            'status' => 1,
            'active' => 1,
            'updated_by' => $this->staff_id,
        ];
        \App\Models\RoomMove::create($room_move);
        \Log::alert('creado '.json_encode($room_move));
    }

    public function housekeeping()
    {
        try {
            $HousekeepingData = [];
            $HousekeepingData['hotel_id'] = $this->hotel_id;
            $HousekeepingData['staff_id'] = $this->staff_id;
            $HousekeepingData['rooms'] = [];
            foreach ($this->data as $value) {
                $room = $value['location'] == '' ? 0 : $this->getRoom($value['location']);
                if ($room != 0) {
                    $is_rush = Arr::get($this->ExtraStatus, $value['status'], false);
                    if ($is_rush === false) {
                        $hk_status = Arr::get($this->HotelHousekeepingConfig, $value['status'], -1);
                        $ooo = $hk_status['description'] == 'OUT_OF_ORDER' ? true : false;
                        $oos = $hk_status['description'] == 'OUT_OF_SERVICE' ? true : false;
                        if ($hk_status !== -1) {
                            if ($ooo) {
                                $this->FrontdeskStatus($room['room_id'], 1, false);
                            } elseif ($oos) {
                                $this->FrontdeskStatus($room['room_id'], 2, false);
                            } else {
                                $this->FrontdeskStatus($room['room_id'], 1, true);
                                $this->FrontdeskStatus($room['room_id'], 2, true);
                            }
                            $_d['room_id'] = $room['room_id'];
                            $_d['hk_status'] = Arr::get($hk_status, 'codes.0.hk_status');
                            $HousekeepingData['rooms'][] = $_d;
                            // $this->createQueue($room['room_id'], 'CLEANING_DELETED', 1, 0);
                        }
                    } else {
                        $this->createQueue($room['room_id'], 'CLEANING_CREATED', 1, $is_rush['code']);
                    }
                }
            }

            $this->SendHSK($HousekeepingData);
        } catch (\Exception $e) {
            \Log::error('Error in housekeeping');
            \Log::error($e);
        }
    }

    public function createQueue($room_id, $action = 'CLEANING_CREATED', $active = 1, $queue = 1)
    {
        // DB::beginTransaction();
        try {
            $hsk_cleanning = HousekeepingCleanings::where('hotel_id', $this->hotel_id)
                ->where('room_id', $room_id)
                ->where('is_active', 1)->orderBy('cleaning_id', 'DESC')->first();

            if ($hsk_cleanning) {
                $hsk_cleanning->in_queue = $queue;
                $hsk_cleanning->save();
                $timeline = [
                    'item_id' => $hsk_cleanning->cleaning_id,
                    'hotel_id' => $this->hotel_id,
                    'action' => $action,
                    'is_active' => $active,
                    'changed_by' => $this->staff_id,
                    'changed_on' => date('Y-m-d H:i:s'),
                    'platform' => 'API-OPERA',
                ];
                HousekeepingTimeline::create($timeline);
                // DB::commit();
            }
        } catch (\Exception $e) {
            // DB::rollback();
            \Log::error($e);
        }
    }

    public function FrontdeskStatus($room_id, $status, $sw = false)
    {
        if (! Arr::has($this->config, 'hk_reasons_id')) {
            return null;
        }
        // DB::beginTransaction();
        try {
            $date = $this->now;
            $room_out_of_service = HotelRoomsOut::where('hotel_id', $this->hotel_id)
                ->where('room_id', $room_id)
                ->where('is_active', 1)
                ->where('status', $status)
                ->whereRaw("'$date' BETWEEN start_date AND end_date")
                ->first();
            if (! $sw) {
                if (! $room_out_of_service) {
                    $data = [
                        'room_id' => $room_id,
                        'hotel_id' => $this->hotel_id,
                        'status' => $status,
                        'hk_reasons_id' => $this->config['hk_reasons_id'],
                        'start_date' => $date,
                        'end_date' => date('Y-m-d H:i:s', strtotime($date.' +90 days')),
                        'comment' => 'SMS Miller Api',
                        'is_active' => 1,
                        'created_by' => $this->staff_id,
                        'created_on' => $date,
                    ];
                    HotelRoomsOut::create($data);
                // DB::commit();
                } else {
                    $room_out_of_service->end_date = date('Y-m-d H:i:s', strtotime($room_out_of_service->end_date.' +30 days'));
                    $room_out_of_service->save();
                }
            } else {
                if ($room_out_of_service) {
                    $room_out_of_service->is_active = 0;
                    $room_out_of_service->updated_by = $this->staff_id;
                    $room_out_of_service->updated_on = $date;
                    $room_out_of_service->save();
                }
            }
            // DB::commit();
        } catch (\Exception $e) {
            \Log::error('Error in change out of service');
            \Log::error($e);
            // DB::rollback();
        }
    }

    public function SendHSK($data)
    {
        if (count($data['rooms']) > 0) {
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
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($data),
            ]);
            $response = curl_exec($curl);
            $err = curl_error($curl);
            if ($err) {
                \Log::error('Error en SmsMiller SendHSK');
                \Log::error($err);
            } else {
                // \Log::info($response);
            }
            curl_close($curl);
        }
    }

    public function getIfSuite($room_no, $SuitesRooms)
    {
        $rooms = null;
        if (Arr::has($SuitesRooms, $room_no)) {
            $rooms = Arr::get($SuitesRooms, $room_no);
        }

        return $rooms;
    }

    public function removeSuitesReservation($reservation_number)
    {
        // \DB::beginTransaction();

        $reservations = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('reservation_number', 'LIKE', '%'.$reservation_number.'_%')->get();
        // dd($reservations);
        $check_in = null;
        if (strpos($reservation_number, '_')) {
            return $check_in;
        }

        foreach ($reservations as $value) {
            if ($value->reservation_status == 1) {
                $check_in = $value->check_in;
                $value->status = 0;
                $value->reservation_status = 5;
                $value->check_out = $this->now;
                $value->reservation_number = $reservation_number.'_REMOVE';

                $value->save();
            }
        }
        // \DB::commit();

        return $check_in;
    }

    public function removeReservation($reservation_number)
    {

        // \DB::beginTransaction();
        $reservations = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('reservation_number', $reservation_number)->get();
        $check_in = null;
        foreach ($reservations as $value) {
            // if ($value->reservation_status == 1) {

            $check_in = $value->check_in;
            $value->status = 0;
            $value->reservation_status = 5;
            $value->check_out = $this->now;
            $value->reservation_number = $reservation_number.'_REMOVE';
            $value->save();
            // }
        }
        // \DB::commit();

        return $check_in;
    }

    public function sendMessages($hotel_id, $guest_id, $staff_id, $email = '', $phone = '', $back = false, $welcome = true, $angel = true)
    {

        //blocked hotels
        $blocked_hotels_angel = [
            273, 325,
        ];
        $blocked_hotels_welcome = [
            273, 325,
        ];
        try {
            if ($this->miller_suites_message && $this->count_message != 0) {
                $rs['send_welcome_blocked'] = true;

                return $rs;
            }
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
                    $this->count_message++;
                    // \Log::alert('se envi¨® una vez');
                    // \Log::alert("$guest_id,$phone");
                    return $rs;
                }

                return 'No record found '.$this->hotel_id;
            }

            return 'Sql no generated';
        } catch (\Exception $e) {
            \Log::info('Error al enviar invitaciones:');
            \Log::info($e);

            return 'Error show laravel.log';
        }
    }

    public function getAngelStatus()
    {
        $data = IntegrationsActive::where('hotel_id', $this->hotel_id)->first();

        return $data->sms_angel_active == 1 ? true : false;
    }

    public function removeAnotherSuiteReservation($reservation_number, $suites)
    {
        $reservations = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('reservation_status', 1)->where('reservation_number', 'LIKE', "%$reservation_number%");
        foreach ($suites as $value) {
            $reservations = $reservations->where('reservation_number', '!=', $reservation_number."_$value");
        }
        $reservations = $reservations->get();
        $check_in = null;
        foreach ($reservations as $value) {
            // if ($value->reservation_status == 1) {

            $check_in = $value->check_in;
            $value->status = 0;
            $value->reservation_status = 5;
            $value->check_out = $this->now;
            $value->reservation_number = $reservation_number.'_REMOVE';
            $value->save();
            // }
        }
    }
}
