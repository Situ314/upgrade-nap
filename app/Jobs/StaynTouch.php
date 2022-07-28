<?php

namespace App\Jobs;

use App\Models\GuestCheckinDetails;
use App\Models\GuestRegistration;
use App\Models\HotelRoom;
use App\Models\HotelRoomsOut;
use App\Models\HousekeepingCleanings;
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

class StaynTouch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $data;

    private $hotel_id;

    private $pms_hotel_id;

    private $config;

    private $now;

    private $staff_id;

    private $HotelHousekeepingConfig;

    public function __construct($data, $hotel_id, $pms_hotel_id, $config, $staff_id, $now)
    {
        $this->data = $data;
        $this->pms_hotel_id = $pms_hotel_id;
        $this->config = $config;
        $this->hotel_id = $hotel_id;
        $this->now = $now;
        $this->staff_id = $staff_id;
        $this->HotelHousekeepingConfig = $config['housekeeping'];
    }

    public function handle()
    {
        if (Arr::get($this->data, 'reservation_number', '') != '' && Arr::get($this->data, 'guest_number', '') != '') {
            $this->reservation();
        } elseif (Arr::get($this->data, 'guest_number', '') != '') {
            $this->guest();
        } elseif (Arr::get($this->data, '0.location', '') != '' && Arr::get($this->data, '0.status', '') != '') {
            $this->housekeeping();
        } else {
            return null;
        }
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
                    $hk_status = Arr::get($this->HotelHousekeepingConfig, strtoupper($value['status']), -1);
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
                }
            }
            $this->SendHSK($HousekeepingData);
        } catch (\Exception $e) {
            \Log::error('Error in stayntouch housekeeping');
            \Log::error($e);
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
                \Log::error('Error en StaynTouch SendHSK');
                \Log::error($err);
            } else {
                // \Log::info($response);
            }
            curl_close($curl);
        }
    }

    public function FrontdeskStatus($room_id, $status, $sw = false)
    {
        if (! Arr::has($this->config, 'hk_reasons_id')) {
            return null;
        }
        DB::beginTransaction();
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
                    DB::commit();
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
            DB::commit();
        } catch (\Exception $e) {
            \Log::error('Error in change out of service');
            \Log::error($e);
            DB::rollback();
        }
    }

    public function reservation()
    {
        DB::beginTransaction();
        try {
            \Log::info('entro a jobs de reserva');
            if ($this->validateReservationData($this->data)) {
                $reservation_number = $this->data['reservation_number'];
                $hotel_id = $this->hotel_id;

                $reservation = GuestCheckinDetails::where('hotel_id', $hotel_id)->where('reservation_number', 'LIKE', $reservation_number)->first();
                if ($reservation) {
                    $this->editReservation($reservation, $this->data);
                } else {
                    $guest_integration = IntegrationsGuestInformation::where('hotel_id', $hotel_id)->where('guest_number', $this->data['guest_number'])->first();
                    $guest = null;
                    if (! $guest_integration) {
                        $guest = $this->registerGuest($this->data);
                    } else {
                        $guest = GuestRegistration::find($guest_integration->guest_id);
                    }
                    if ($guest) {
                        $room = $this->data['location'] == '' ? 0 : $this->getRoom($this->data['location']);

                        $reservation = [
                            'guest_id' => $guest->guest_id,
                            'hotel_id' => $this->hotel_id,
                            'room_no' => $room == 0 ? $room : $room['room_id'],
                            'check_in' => $this->data['check_in'],
                            'check_out' => $this->data['check_out'],
                            'comment' => '',
                            'status' => $this->data['status'],
                            'main_guest' => 0,
                            'reservation_status' => $this->data['reservation_status'],
                            'reservation_number' => $this->data['reservation_number'],
                        ];
                        $__reservation = GuestCheckinDetails::create($reservation);
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
                    } else {
                        \Log::critical('StayNTouch guest  NOT register');
                        \Log::critical(json_encode($this->data));
                    }
                }
            } else {
                \Log::critical('StayNTouch NOT register');
                \Log::critical(json_encode($this->data));
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('error StayNTouch');
            \Log::error($e);

            return null;
        }
    }

    public function guest()
    {
        $guest_integration = IntegrationsGuestInformation::where('hotel_id', $this->hotel_id)->where('guest_number', $this->data['guest_number'])->first();
        $guest = null;
        if (! $guest_integration) {
            $guest = $this->registerGuest($this->data);
        } else {
            $guest = GuestRegistration::find($guest_integration->guest_id);
            $this->editGuest($guest, $this->data);
        }
    }

    public function registerGuest($__data)
    {
        DB::beginTransaction();
        try {
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

            $guest_integration = IntegrationsGuestInformation::create($guest_integration);
            DB::commit();

            return $guest;
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('error stayNTouch');
            \Log::error($e);

            return null;
        }
    }

    public function editReservation(GuestCheckinDetails $reservation, $data)
    {
        DB::beginTransaction();
        try {
            $__update = '';
            $room = $data['location'] == '' ? 0 : $this->getRoom($data['location']);
            $data['room_no'] = $room['room_id'];

            if (
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

                $aux_data = $this->data;
                $this->data = $data;
                $__reservation = $this->reservation();
                $this->data = $aux_data;
                if ($__reservation) {
                    $this->RoomMove($reservation, $__reservation);
                }
            } else {
                if ($reservation->reservation_status != 1 && $reservation->check_in != $data['check_in']) {
                    $__update .= "check_in: $reservation->check_in to ".$data['check_in'].', ';
                    $reservation->check_in = $data['check_in'];
                }
                if ($reservation->check_out != $data['check_out']) {
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
                $_guest = IntegrationsGuestInformation::where('hotel_id', $this->hotel_id)->where('guest_number', $data['guest_number'])->first();
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
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('error StayNTouch');
            \Log::error($e);
        }
    }

    public function editGuest(GuestRegistration $guest, $data)
    {
        DB::beginTransaction();
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
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('error StayNTouch');
            \Log::error($e);

            return null;
        }
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

        // if ($data['reservation_status'] === 1 && $data['location'] === '') {
        //     $resp = false;
        // }

        return $resp;
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
}
