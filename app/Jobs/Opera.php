<?php

namespace App\Jobs;

use App\Models\GuestCheckinDetails;
use App\Models\GuestRegistration;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\HotelRoomsOut;
use App\Models\HousekeepingCleanings;
use App\Models\HousekeepingTimeline;
use App\Models\Integrations;
use App\Models\IntegrationsActive;
// use App\Models\IntegrationsGuestInformation;
use App\Models\IntegrationSuitesRoom;
use App\Models\Log\OracleReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use DB;

ini_set('max_execution_time', 260);

class Opera implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $hotel_id;
    private $staff_id;
    private $type;
    private $data;
    private $now;
    private $config;
    private $HotelHousekeepingConfig;
    private $room_id;
    private $is_suite;
    private $messages_guest;
    private $send_message_opera = 0;
    private $reservations_numbers = [];
    private $MessageID;
    private $xml;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($hotel_id, $staff_id, $type, $data, $config, $room_id = null, $is_suite = false, $MessageID = null, $xml = null)
    {
        $this->hotel_id                 = $hotel_id;
        $this->staff_id                 = $staff_id;
        $this->type                     = $type;
        $this->data                     = $data;
        $this->config                   = $config;
        $this->HotelHousekeepingConfig  = $config['housekeeping'];
        $this->room_id                  = $room_id;
        $this->is_suite                 = $is_suite;
        $this->messages_guest           = 0;
        $this->MessageID                = $MessageID;
        $this->xml                      = $xml;
        date_default_timezone_set('UTC');
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
            $this->$method();
        }
    }


    private function GuestStatusNotificationRequest()
    {
        date_default_timezone_set('UTC');

        $resortId       = array_get($this->data, 'Body.GuestStatusNotificationRequest.GuestStatus.resortId', '');
        $ReservationID  = array_get($this->data, 'Body.GuestStatusNotificationRequest.GuestStatus.ReservationID', '');
        $UniqueID       = array_get($this->data, 'Body.GuestStatusNotificationRequest.GuestStatus.ProfileIDs.UniqueID', '');
        $Profile        = null;
        // $Profile        = IntegrationsGuestInformation::where('hotel_id', $this->hotel_id)->where('guest_number', $UniqueID)->first();
        $Profile = \App\Models\GuestRegistration::where('hotel_id', $this->hotel_id)->where('pms_unique_id', $UniqueID)->first();
        $state          = 0;

        // if ($Profile && $this->hotel_id != 289) {
        if ($Profile) {
            $state = 1;
        } else {
            $result = $this->getProfileData($UniqueID, $resortId);
            $result['ResortId'] = $resortId;
            $data = $this->data;
            $this->data = $result;
            $resp = $this->ProfileRegistration();
            $this->data = $data;
            if ($resp) $state = 1;
        }

        $reservation = [
            'resortId'          => $resortId,
            'ReservationID'     => $ReservationID,
            'reservationStatus' => array_get($this->data, 'Body.GuestStatusNotificationRequest.GuestStatus.@attributes.reservationStatus', ''),
            'roomNumber'        => array_get($this->data, 'Body.GuestStatusNotificationRequest.GuestStatus.roomNumber', ''),
            'UniqueID'          => $UniqueID,
            'checkInDate'       => array_get($this->data, 'Body.GuestStatusNotificationRequest.GuestStatus.checkInDate', ''),
            'checkOutDate'      => array_get($this->data, 'Body.GuestStatusNotificationRequest.GuestStatus.checkOutDate', ''),
            'ageQualifyingCode' => array_get($this->data, 'Body.GuestStatusNotificationRequest.GuestStatus.GuestCounts.GuestCount.@attributes.ageQualifyingCode', ''),
            'GuestCount'        => array_get($this->data, 'Body.GuestStatusNotificationRequest.GuestStatus.GuestCounts.GuestCount.@attributes.count', ''),
            'state'             => $state,
            'created_at'        => date('Y-m-d H:i:s')
        ];
        $suites = $this->getSuites($reservation['roomNumber']);
        if ($suites) {
            $this->is_suite = true;
            $_rs = $reservation['ReservationID'];
            $this->removeReservation($_rs);
            $resp = $this->isAnotherSuite($_rs, $reservation['roomNumber']);
            if ($resp)  $this->removeSuitesReservation($_rs);

            foreach ($suites as  $suite) {
                $reservation['roomNumber'] = $suite['location'];
                $reservation['ReservationID'] = $_rs . '_' . $suite['location'];
                $this->reservations_numbers[] = $_rs . '_' . $suite['location'];
                $OracleReservation = new \App\Models\Log\OracleReservation($reservation);
                if ($state === 1) {
                    $this->GuestRegistration($OracleReservation);
                }
                try {
                    $OracleReservation->save();
                    $reservation['idLog'] = $OracleReservation->id;
                    $this->sendMonitoringApp($reservation, 'LogOpera_Reservation');
                } catch (\Exception $th) {
                    \Log::info('No guardó 1');
                    \Log::error($th);
                }
            }
            GuestCheckinDetails::where('hotel_id', $this->hotel_id)
                ->where('reservation_number', 'like', "%$_rs%")
                ->whereNotIn('reservation_number', $this->reservations_numbers)
                ->update(['status' => 0, 'reservation_status' => 5]);
        } else {
            $this->removeSuitesReservation($reservation['ReservationID']);

            $OracleReservation = new \App\Models\Log\OracleReservation($reservation);
            if ($state === 1) $this->GuestRegistration($OracleReservation);

            try {
                $OracleReservation->save();
                $reservation['idLog'] = $OracleReservation->id;
                $this->sendMonitoringApp($reservation, 'LogOpera_Reservation');
            } catch (\Exception $th) {
                \Log::info('No guardó 2');
                \Log::error($th);
            }
        }
    }

    private function GuestStatusNotificationExtRequest()
    {
        date_default_timezone_set('UTC');

        $resortId       = array_get($this->data, 'Body.GuestStatusNotificationExtRequest.GuestStatus.resortId', '');
        $ReservationID  = array_get($this->data, 'Body.GuestStatusNotificationExtRequest.GuestStatus.ReservationID', '');
        $UniqueID       = array_get($this->data, 'Body.GuestStatusNotificationExtRequest.GuestStatus.ProfileIDs.UniqueID', '');

        $Profile = null;
        // $Profile = IntegrationsGuestInformation::where('hotel_id', $this->hotel_id)->where('guest_number', $UniqueID)->first();
        $Profile = \App\Models\GuestRegistration::where("hotel_id", $this->hotel_id)->where('pms_unique_id', $UniqueID)->first();
        $state = 0;

        // if ($Profile && $this->hotel_id != 289) {
        if ($Profile) {
            $state = 1;
        } else {
            $result = $this->getProfileData($UniqueID, $resortId);
            $result['ResortId'] = $resortId;
            $data = $this->data;
            $this->data = $result;
            $resp = $this->ProfileRegistration();
            $this->data = $data;
            if ($resp) $state = 1;
        }
        $reservation = [
            'resortId'          => $resortId,
            'ReservationID'     => $ReservationID,
            'reservationStatus' => array_get($this->data, 'Body.GuestStatusNotificationExtRequest.GuestStatus.@attributes.reservationStatus', ''),
            'roomNumber'        => array_get($this->data, 'Body.GuestStatusNotificationExtRequest.GuestStatus.roomNumber', ''),
            'UniqueID'          => $UniqueID,
            'checkInDate'       => array_get($this->data, 'Body.GuestStatusNotificationExtRequest.GuestStatus.checkInDate', ''),
            'checkOutDate'      => array_get($this->data, 'Body.GuestStatusNotificationExtRequest.GuestStatus.checkOutDate', ''),
            'ageQualifyingCode' => array_get($this->data, 'Body.GuestStatusNotificationExtRequest.GuestStatus.GuestCounts.GuestCount.@attributes.ageQualifyingCode', ''),
            'GuestCount'        => array_get($this->data, 'Body.GuestStatusNotificationExtRequest.GuestStatus.GuestCounts.GuestCount.@attributes.count', ''),
            'state'             => $state,
            'created_at'        => date('Y-m-d H:i:s')
        ];
        $suites = $this->getSuites($reservation['roomNumber']);
        if ($suites) {
            $this->is_suite = true;
            $_rs = $reservation['ReservationID'];
            $this->removeReservation($_rs);
            $resp = $this->isAnotherSuite($_rs, $reservation['roomNumber']);
            if ($resp)  $this->removeSuitesReservation($_rs);

            foreach ($suites as  $suite) {
                $reservation['roomNumber'] = $suite['location'];
                $reservation['ReservationID'] = $_rs . '_' . $suite['location'];
                $this->reservations_numbers[] = $_rs . '_' . $suite['location'];

                $OracleReservation = new \App\Models\Log\OracleReservation($reservation);
                if ($state === 1) {
                    $this->GuestRegistration($OracleReservation);
                }
                try {
                    $OracleReservation->save();
                    $reservation['idLog'] = $OracleReservation->id;
                    $this->sendMonitoringApp($reservation, 'LogOpera_Reservation');
                } catch (\Exception $th) {
                    \Log::info('No guardó 3');
                    \Log::error($th);
                }
            }
            GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('reservation_number', 'like', "%$_rs%")->whereNotIn('reservation_number', $this->reservations_numbers)->update(['status' => 0, 'reservation_status' => 5]);
        } else {
            $this->removeSuitesReservation($reservation['ReservationID']);

            $OracleReservation = new \App\Models\Log\OracleReservation($reservation);
            if ($state === 1) {
                $this->GuestRegistration($OracleReservation);
            }
            try {
                $OracleReservation->save();
                $reservation['idLog'] = $OracleReservation->id;
                $this->sendMonitoringApp($reservation, 'LogOpera_Reservation');
            } catch (\Exception $th) {
                \Log::info('No guardó 4');
                \Log::error($th);
            }
        }
    }

    public function QueueRoomStatus()
    {
        $resortId       = array_get($this->data, 'resortId', '');
        $action  = array_get($this->data, '@attributes.Action', '');
        $data_element = array_get($this->data, 'DataElements.DataElement', []);
        switch ($action) {
            case 'NEW':
                $location = '';
                foreach ($data_element as $key => $value) {
                    if (array_get($value, '@attributes.name', '') == 'RoomNumber') {
                        $location = array_get($value, '@attributes.newData', '');
                    }
                }
                if ($location != '') {
                    $room_id = $this->getRoom($location);
                    if (array_has($room_id, 'room_id')) {
                        $this->createQueue($room_id['room_id']);
                    }
                }
                break;
            case 'UPDATE':
                $location = '';
                foreach ($data_element as $key => $value) {
                    if (array_get($value, '@attributes.name', '') == 'RoomNumber') {
                        $location = array_get($value, '@attributes.newData', '');
                    }
                }
                if ($location != '') {
                    $room_id = $this->getRoom($location, 'CLEANING_UPDATED', 1, 1);
                    if (array_has($room_id, 'room_id')) {
                        $this->createQueue($room_id['room_id']);
                    }
                }
                break;
            case 'DELETE':
                foreach ($data_element as $key => $value) {
                    if (array_get($value, '@attributes.name', '') == 'GuestNameId') {
                        $guest_id = array_get($value, '@attributes.newData', array_get($value, '@attributes.oldData', ''));
                    }
                }
                if ($guest_id != '') {
                    // $guest = IntegrationsGuestInformation::where('hotel_id', $this->hotel_id)->where('guest_number', $guest_id)->first();
                    $guest = \App\Models\GuestRegistration::where("hotel_id", $this->hotel_id)->where("pms_unique_id", $guest_id)->first();
                    if ($guest) {
                        $rs = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('guest_id', $guest->guest_id)->whereDate('check_in', '>=', date('Y-m-d'))
                            ->where('reservation_status', 1)->orderBy('sno', 'DESC')->first();
                        if ($rs) {
                            $this->createQueue($rs->room_no, 'CLEANING_DELETED', 1, 0);
                        }
                    }
                }
                break;
        }
    }

    public function createQueue($room_id, $action = 'CLEANING_CREATED', $active = 1, $queue = 1)
    {
        $this->configTimeZone($this->hotel_id);
        try {
            $hsk_cleanning = HousekeepingCleanings::where('hotel_id', $this->hotel_id)
                ->where('room_id', $room_id)
                ->orderBy('assigned_date', 'desc')->orderBy('cleaning_id', 'DESC')->first();

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
                    'changed_field' => 'in_queue',
                    'previous_value' => '0',
                    'value' => '1'
                ];
                HousekeepingTimeline::create($timeline);
                // DB::commit();
            }
        } catch (\Exception $e) {
            // DB::rollback();
            \Log::error("createQueue");
            \Log::error($e);
        }
    }

    private function GuestRegistration($reservation)
    {
        // DB::beginTransaction();
        $this->customWriteLog("sync_opera", $this->hotel_id, "ENTRO GUEST REGISTRATION");
        try {

            $this->configTimeZone($this->hotel_id);

            // $IntegrationsGuestInformation = \App\Models\IntegrationsGuestInformation::where('hotel_id', $this->hotel_id)->where('guest_number', $reservation->UniqueID)->first();
            $guest = \App\Models\GuestRegistration::where('hotel_id', $this->hotel_id)->where('pms_unique_id', $reservation->UniqueID)->first();
            // if ($IntegrationsGuestInformation) {
            if ($guest) {

                $this->customWriteLog("sync_opera", $this->hotel_id, "ENCONTRO GUEST REGISTRATION");
                
                $guest_id = $guest->guest_id;
                $room_no = 0;
                if (!empty($reservation->roomNumber)) {
                    $room           = $this->getRoom($reservation->roomNumber);
                    $room_no        = null;
                    if (!is_null($room)) {
                        $room_no    = $room['room_id'];
                    }
                }

                $this->customWriteLog("sync_opera", $this->hotel_id, "DATOS DEL ROOM NUMBER");
                $this->customWriteLog("sync_opera", $this->hotel_id, json_encode($room_no));

                if (!is_null($room_no)) {
                    
                    $this->customWriteLog("sync_opera", $this->hotel_id, "ENTRO AL ROOM NUMBER");

                    $status = 0;
                    $reservation_status = 0;

                    $time1 = '23:59:00';
                    $time2 = '23:59:59';
                    switch ($reservation->reservationStatus) {
                        case 'RESERVED':
                        case 'OTHER':
                            $reservation_status = 0;
                            $status = 1;
                            break;
                        case 'CHECKED_IN':
                            $reservation_status = 1;
                            $status = 1;
                            $this->configTimeZone($this->hotel_id);
                            $time1 = date('H:i:s');
                            // $time1 = "11:59:00";
                            break;
                        case 'CANCELLED':
                            $reservation_status = 2;
                            $status = 0;
                            $this->configTimeZone($this->hotel_id);
                            $time2 = date('H:i:s');
                            break;
                        case 'CHECKED_OUT':
                            $reservation_status = 3;
                            $status = 0;
                            $this->configTimeZone($this->hotel_id);
                            $time2 = date('H:i:s');
                            break;
                        case 'NO_SHOW':
                            $reservation_status = 4;
                            $status = 0;
                            $this->configTimeZone($this->hotel_id);
                            $time2 = date('H:i:s');
                            break;
                    }

                    $guest_reservation = [
                        'hotel_id'              => $this->hotel_id,
                        'guest_id'              => $guest_id,
                        'room_no'               => $room_no,
                        'check_in'              => date('Y-m-d', strtotime("$reservation->checkInDate")) . " " . $time1,
                        'check_out'             => date('Y-m-d', strtotime("$reservation->checkOutDate")) . " " . $time2,
                        'comment'               => '',
                        'status'                => $status,
                        'main_guest'            => 0,
                        'reservation_status'    => $reservation_status,
                        'reservation_number'    => $reservation->ReservationID
                    ];

                    $GuestCheckinDetails = \App\Models\GuestCheckinDetails::where('hotel_id', $this->hotel_id)
                        ->where('reservation_number', $guest_reservation['reservation_number'])
                        ->first();

                    $__update = "";

                    if ($GuestCheckinDetails) {
                        if (
                            $GuestCheckinDetails->reservation_status == 1 &&
                            $guest_reservation['reservation_status'] == 1 &&
                            $GuestCheckinDetails->room_no != $guest_reservation['room_no']
                        ) {
                            $this->configTimeZone($this->hotel_id);
                            $GuestCheckinDetails->status                = 0;
                            $GuestCheckinDetails->reservation_status    = 5;
                            $GuestCheckinDetails->reservation_number    = $GuestCheckinDetails->reservation_number . '_RM';
                            $check_out                                  = $GuestCheckinDetails->check_out;
                            $GuestCheckinDetails->check_out             = date('Y-m-d H:i:s');

                            $guest_reservation['check_in']              = $GuestCheckinDetails->check_out;
                            $guest_reservation['check_out']             = $check_out;
                            if ($guest_reservation['check_out'] == strtotime('0000-00-00 00:00:00')) {
                                $guest_reservation['check_out'] =  date('Y-m-d H:i:s');
                            }
                            \App\Models\GuestCheckinDetails::create($guest_reservation);
                            $this->RoomMove($GuestCheckinDetails, $guest_reservation);

                            // $hsk_cleanning = HousekeepingCleanings::where('hotel_id', $this->hotel_id)
                            //     ->where('room_id', $GuestCheckinDetails->room_no)
                            //     ->orderBy('assigned_date', 'desc')->orderBy('cleaning_id', 'DESC')->first();

                            // if ($hsk_cleanning && $hsk_cleanning->guest_id != $GuestCheckinDetails->guest_id) {
                            //     $hsk_cleanning->guest_id = null;
                            //     $hsk_cleanning->checkin_details_id = null;
                            //     $hsk_cleanning->front_desk_status = 1;
                            //     $hsk_cleanning->save();
                            // }
                        } else {
                            if (
                                $GuestCheckinDetails->reservation_status != $reservation_status &&
                                $reservation_status == 1 && $status == 1 &&  date('Y-m-d', strtotime($guest_reservation["check_in"])) == date('Y-m-d')
                            ) {
                                $this->send_message_opera = 1;
                            }

                            if ($GuestCheckinDetails->check_in != $guest_reservation['check_in']) {
                                $__update .= "check_in: $GuestCheckinDetails->check_in to " . $guest_reservation['check_in'] . ", ";
                                // fix checkin_date
                                if ($guest_reservation['reservation_status'] <= 2) {
                                    $GuestCheckinDetails->check_in = $guest_reservation['check_in'];
                                }
                                //$GuestCheckinDetails->check_in = $guest_reservation['check_in'];
                            }
                            if ($GuestCheckinDetails->check_out != $guest_reservation['check_out']) {
                                $__update .= "check_out: $GuestCheckinDetails->check_out to " . $guest_reservation['check_out'] . ", ";
                                $GuestCheckinDetails->check_out = $guest_reservation['check_out'];
                            }
                            if ($GuestCheckinDetails->status != $guest_reservation['status']) {
                                $__update .= "status: $GuestCheckinDetails->status to " . $guest_reservation['status'] . ", ";
                                $GuestCheckinDetails->status = $guest_reservation['status'];
                            }
                            if ($GuestCheckinDetails->reservation_status != $guest_reservation['reservation_status']) {
                                $__update .= "reservation_status: $GuestCheckinDetails->reservation_status to " . $guest_reservation['reservation_status'] . ", ";
                                $GuestCheckinDetails->reservation_status = $guest_reservation['reservation_status'];
                            }

                            if ($GuestCheckinDetails->room_no != $guest_reservation['room_no']) {
                                $__update .= "room_no: $GuestCheckinDetails->room_no to " . $guest_reservation['room_no'] . ", ";
                                $hsk_cleanning = HousekeepingCleanings::where('hotel_id', $this->hotel_id)
                                    ->where('room_id', $GuestCheckinDetails->room_no)
                                    ->orderBy('assigned_date', 'desc')->orderBy('cleaning_id', 'DESC')->first();

                                $GuestCheckinDetails->room_no = $guest_reservation['room_no'];

                                if ($hsk_cleanning && $hsk_cleanning->in_queue == 1) {
                                    $hsk_cleanning->in_queue = 0;
                                    $hsk_cleanning->save();
                                    $hsk_cleanning2 = HousekeepingCleanings::where('hotel_id', $this->hotel_id)
                                        ->where('room_id', $guest_reservation['room_no'])
                                        ->orderBy('assigned_date', 'desc')->orderBy('cleaning_id', 'DESC')->first();
                                    if ($hsk_cleanning2) {
                                        $hsk_cleanning2->in_queue = 1;
                                        $hsk_cleanning2->save();
                                    }
                                }
                            }
                            // Se agrego en la actualización de la reserva, que verifique si el huesped asociado a la reserva fue o no modificado
                            if ($GuestCheckinDetails->guest_id != $guest_reservation["guest_id"]) {
                                $__update .= "guest_id: $GuestCheckinDetails->guest_id to " . $guest_reservation['guest_id'] . ", ";
                                $GuestCheckinDetails->guest_id = $guest_reservation['guest_id'];
                            }
                        }

                        if ($GuestCheckinDetails->room_no != 0) {

                            $hsk_cleanning = HousekeepingCleanings::where('hotel_id', $this->hotel_id)
                                ->where('room_id', $GuestCheckinDetails->room_no)
                                ->orderBy('assigned_date', 'desc')->orderBy('cleaning_id', 'DESC')->first();
                            if ($hsk_cleanning && $hsk_cleanning->guest_id != $GuestCheckinDetails->guest_id) {
                                $hsk_cleanning->guest_id = $GuestCheckinDetails->guest_id;
                                $hsk_cleanning->checkin_details_id = $GuestCheckinDetails->sno;
                                $hsk_cleanning->front_desk_status = 2;
                                $hsk_cleanning->save();
                            }
                        }
                        $GuestCheckinDetails->save();
                        if (!empty($__update)) {
                            $this->saveLogTracker([
                                'hotel_id'  => $this->hotel_id,
                                'module_id' => 8,
                                'action'    => 'update',
                                'prim_id'   => $GuestCheckinDetails->sno,
                                'staff_id'  => $this->staff_id,
                                'date_time' => date('Y-m-d H:i:s'),
                                'comments'  => "Update Reservation information: $__update",
                                'type'      => 'API-OPERA'
                            ]);
                        }
                    } else {
                        $GuestCheckinDetails = \App\Models\GuestCheckinDetails::create($guest_reservation);
                        if ($guest_reservation['reservation_status'] == 1 && $guest_reservation['status'] == 1 &&  date('Y-m-d', strtotime($guest_reservation["check_in"])) == date('Y-m-d')) {
                            $this->send_message_opera = 1;
                        }
                        $this->saveLogTracker([
                            'hotel_id'  => $this->hotel_id,
                            'module_id' => 8,
                            'action'    => 'add',
                            'prim_id'   => $GuestCheckinDetails->sno,
                            'staff_id'  => $this->staff_id,
                            'date_time' => date('Y-m-d H:i:s'),
                            'comments'  => "Add Reservation information: $__update",
                            'type'      => 'API-OPERA'
                        ]);
                    }
                    date_default_timezone_set('UTC');
                }
            }
            // // DB::commit();

            if ($this->send_message_opera === 1) {
                $__GuestCheckinDetails = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('guest_id', $GuestCheckinDetails->guest_id)->get();
                $back = false;

                if (count($__GuestCheckinDetails) > 1) {
                    $back = true;
                }
                $GuestRegistration = GuestRegistration::find($GuestCheckinDetails->guest_id);
                $rs = $this->sendMessages(
                    $this->hotel_id,
                    $GuestCheckinDetails->guest_id,
                    1,
                    $GuestRegistration->email_address,
                    $GuestRegistration->phone_no,
                    $back
                );
                $this->saveLogTracker([
                    'module_id' => 0,
                    'action'    => 'send_mail',
                    'prim_id'   => $GuestCheckinDetails->guest_id,
                    'staff_id'  => 1,
                    'date_time' => date('Y-m-d H:i:s'),
                    'comments'  => json_encode([
                        "data" => [
                            "hotel_id"      => $this->hotel_id,
                            "guest_id"      => $GuestCheckinDetails->guest_id,
                            "staff_id"      => 1,
                            "email_address" => $GuestRegistration->email_address,
                            "phone_no"      => $GuestRegistration->phone_no,
                            "back"          => $back
                        ],
                        "rs" => $rs
                    ]),
                    'hotel_id'  => $this->hotel_id,
                    'type'      => 'API-OPERA'
                ]);
            }
            return true;
        } catch (Exception $e) {
            \Log::error('Error GuestRegistration');
            \Log::error("$e");
            // DB::rollback();
            return false;
        }
    }

    private function ProfileRegistration($dataLog = null)
    {   
        $this->customWriteLog("sync_opera", $this->hotel_id, "=========ENTRO AL PROFILE LOG========");
        date_default_timezone_set('UTC');
        try {
            $sw = false;
            $profile_log_data = null;
            $this->customWriteLog("sync_opera", $this->hotel_id, "=========ANTES DEL IF========");
            if (!$dataLog) {
                $this->customWriteLog("sync_opera", $this->hotel_id, "=========DESPUES DEL IF========");
                $unique_id = array_get($this->data, 'Profile.IDs.UniqueID');
                if (array_has(!$this->data, 'Profile')) {
                    return false;
                }
                $resort_id = array_get($this->data, 'ResortId');
                $addressString = '';
                $addressData = array_get($this->data, 'Profile.Addresses.NameAddress.AddressLine', '');
                if (!is_array($addressData)) {
                    $addressData = [$addressData];
                }
                foreach ($addressData as  $address) {
                    if (is_string($address)) {
                        $addressString .= $address != '' ? $address . ';' : '';
                    } else {
                        $addressString = '';
                    }
                }

                $firstName = array_get($this->data, 'Profile.Customer.PersonName.FirstName', '');
                $firstName = is_array($firstName) ? "" : $firstName;

                $lastName = array_get($this->data, 'Profile.Customer.PersonName.LastName', '');
                $lastName = is_array($lastName) ? "" : $lastName;

                $cityName = array_get($this->data, 'Profile.Addresses.NameAddress.CityName', '');
                $cityName = is_array($cityName) ? "" : $cityName;

                $postalCode = array_get($this->data, 'Profile.Addresses.NameAddress.PostalCode', '');
                $postalCode = is_array($postalCode) ? "" : $postalCode;

                $countryCode = array_get($this->data, 'Profile.Addresses.NameAddress.CountryCode', '');
                $countryCode = is_array($countryCode) ? "" : $countryCode;

                $birthDate = array_get($this->data, 'Profile.Customer.@attributes.birthDate', '');
                $birthDate = is_array($birthDate) ? "" : $birthDate;


                $dataLog = [
                    'resortId'      => $resort_id,
                    'UniqueID'      => $unique_id,
                    'FirstName'     => $firstName,
                    'LastName'      => $lastName,
                    'EMAIL'         => '',
                    'MOBILE'        => '',
                    'AddressLine'   => $addressString,
                    'CityName'      => $cityName,
                    'PostalCode'    => $postalCode,
                    'CountryCode'   => $countryCode,
                    'birthDate'     => $birthDate,
                    'created_at'    => date('Y-m-d H:i:s')
                ];
                $phonesData = array_get($this->data, 'Profile.Phones.NamePhone', []);
                if (array_has($phonesData, '@attributes.phoneType')) {
                    $phonesData = [$phonesData];
                }

                foreach ($phonesData as $value) {
                    if (array_get($value, '@attributes.phoneRole') == 'PHONE' && $dataLog['MOBILE'] == '') {
                        $dataLog['MOBILE'] = array_get($value, 'PhoneNumber', '');
                        $dataLog['MOBILE'] = is_array($dataLog['MOBILE']) ? "" : $dataLog['MOBILE'];
                    }
                    if (array_get($value, '@attributes.phoneRole') == 'EMAIL' && $dataLog['EMAIL'] == '') {
                        $dataLog['EMAIL'] = array_get($value, 'PhoneNumber', '');
                        $dataLog['EMAIL'] = is_array($dataLog['EMAIL']) ? "" : $dataLog['EMAIL'];
                    }
                }
                $profile_log_data = new \App\Models\Log\OracleProfile($dataLog);
                $this->NewGuest($this->data, $sw);
                try {
                    $profile_log_data->save();
                } catch (\Exception $th) {
                    \Log::error("Error en ProfileRegistration 1");
                    $this->customWriteLog("sync_opera", $this->hotel_id, "Error en ProfileRegistration 1");
                    \Log::error($th);
                }

                $dataLog['idLog'] = $profile_log_data->id;
                $this->sendMonitoringApp($dataLog, 'LogOpera_Profile');
            } else {
                $this->customWriteLog("sync_opera", $this->hotel_id, "=========DESPUES DEL IF #2========");
                $sw         = true;
                $unique_id  = array_get($dataLog, 'UniqueID');
                $resort_id  = array_get($this->data, 'resortId');
                $profile_log_data = new \App\Models\Log\OracleProfile($dataLog);
                $this->customWriteLog("sync_opera", $this->hotel_id, "========= DATA LOG ========");
                $this->customWriteLog("sync_opera", $this->hotel_id, json_encode($dataLog));
                $this->NewGuest($dataLog, $sw);
                try {
                    $profile_log_data->save();
                } catch (\Exception $th) {
                    \Log::error("Error en ProfileRegistration 2");
                    $this->customWriteLog("sync_opera", $this->hotel_id, "Error en ProfileRegistration 2");
                    \Log::error($th);
                }
            }
            return true;
        } catch (Exception $e) {
            \Log::error('Error Create Log Profile');
            $this->customWriteLog("sync_opera", $this->hotel_id, "Error Create Log Profile");
            \Log::error($e);
            return false;
        }
    }

    public function configTimeZone($hotel_id)
    {
        $timezone = \App\Models\Hotel::find($hotel_id)->time_zone;
        date_default_timezone_set($timezone);
    }

    public function NewGuest($arrayData, $sw = false)
    {
        // DB::beginTransaction();
        try {
            $old_guest  = null;
            $guest_data = [];
            $angel_status = 1;
            if (!$this->getAngelStatus()) {
                $angel_status = 0;
            }
            $unique_id = '';
            $this->configTimeZone($this->hotel_id);

            $this->customWriteLog("sync_opera", $this->hotel_id, "========= ANTES DEL SW EN CREATE NEW_GUEST ========");

            if ($sw) {
                $unique_id = array_get($arrayData, 'UniqueID');
                $guest_zip_code = '';
                if (!is_array(array_get($arrayData, 'PostalCode', ''))) {
                    $guest_zip_code = array_get($arrayData, 'PostalCode', '');
                }
                $guest_data = [
                    'firstname'     => array_get($arrayData, 'FirstName', ''),
                    'lastname'      => array_get($arrayData, 'LastName', ''),
                    'address'       => array_get($arrayData, 'AddressLine', ''),
                    'city'          => array_get($arrayData, 'CityName', ''),
                    'state'         => array_get($arrayData, 'StateProv', ''),
                    'zipcode'       => $guest_zip_code,
                    'email_address' => '',
                    'phone_no'      => '',
                    'hotel_id'      => $this->hotel_id,
                    'language'      => '',
                    'comment'       => '',
                    'pms_unique_id' => $unique_id
                ];

                $this->customWriteLog("sync_opera", $this->hotel_id, "========= GUEST DATA 1 ========");
                $this->customWriteLog("sync_opera", $this->hotel_id, json_encode($guest_data));
            } else {
                $addressString = '';
                $addressData = array_get($arrayData, 'Profile.Addresses.NameAddress.AddressLine', '');
                if (!is_array($addressData)) {
                    $addressData = [$addressData];
                }
                foreach ($addressData as  $address) {
                    if (is_string($address)) {
                        $addressString .= $address != '' ? ($address . ';') : '';
                    }
                }
                $unique_id = array_get($arrayData, 'Profile.IDs.UniqueID');
                $guest_zip_code = '';
                if (!is_array(array_get($arrayData, 'PostalCode', ''))) {
                    $guest_zip_code = array_get($arrayData, 'Profile.Addresses.NameAddress.PostalCode', '');
                }
                $guest_data = [
                    'firstname'     => array_get($arrayData, 'Profile.Customer.PersonName.FirstName', ''),
                    'lastname'      => array_get($arrayData, 'Profile.Customer.PersonName.LastName', ''),
                    'address'       => is_string($addressString) ? $addressString : '',
                    'city'          => array_get($arrayData, 'Profile.Addresses.NameAddress.CityName', ''),
                    'state'         => array_get($arrayData, 'Profile.Addresses.NameAddress.StateProv', ''),
                    'zipcode'       => $guest_zip_code,
                    'email_address' => '',
                    'phone_no'      => '',
                    'hotel_id'      => $this->hotel_id,
                    'language'      => '',
                    'comment'       => '',
                    'created_on'    => date('Y-m-d H:i:s'),
                    'created_by'    => $this->staff_id,
                    'pms_unique_id' => $unique_id,
                ];
                $phonesData = array_get($arrayData, 'Profile.Phones.NamePhone', []);
                if (array_has($phonesData, '@attributes.phoneType')) {
                    $phonesData = [$phonesData];
                }
                foreach ($phonesData as $value) {
                    if (array_get($value, '@attributes.phoneRole') == 'PHONE' && $guest_data['phone_no'] == '') {
                        $guest_data['phone_no'] = array_get($value, 'PhoneNumber', '');
                    }
                    if (array_get($value, '@attributes.phoneRole') == 'EMAIL' && $guest_data['email_address'] == '') {
                        $guest_data['email_address'] = array_get($value, 'PhoneNumber', '');
                        $guest_data['email_address'] = substr($guest_data['email_address'], 0, 100);
                    }
                }

                $this->customWriteLog("sync_opera", $this->hotel_id, "========= GUEST DATA 2 ========");
                $this->customWriteLog("sync_opera", $this->hotel_id, json_encode($guest_data));
            }

            if (!$unique_id == '') {

                $this->customWriteLog("sync_opera", $this->hotel_id, "========= DENTRO DE LA INFO DEL  GUEST ========");


                // $IntegrationsGuestInformation = \App\Models\IntegrationsGuestInformation::where('guest_number', $unique_id)->where('hotel_id', $this->hotel_id)->first();
                $guest = \App\Models\GuestRegistration::where('hotel_id', $this->hotel_id)->where('pms_unique_id', $unique_id)->first();

                // if ($IntegrationsGuestInformation) {
                if ($guest) {

                    $this->customWriteLog("sync_opera", $this->hotel_id, "========= ENCONTRO AL GUEST ========");

                    // $old_guest = \App\Models\GuestRegistration::find($IntegrationsGuestInformation->guest_id);
                    // if ($old_guest) {
                    $__update = '';
                    if (is_string($guest_data['email_address']) && $guest_data['email_address'] != $guest->email_address) {
                        $__update .= "email_address: $guest->email_address to " . $guest_data['email_address'] . ", ";
                        $guest->email_address = $guest_data['email_address'];
                    }
                    $phone_no = str_replace(["-", ".", " ", "(", ")", "*", "/", "na", "+"], "", $guest_data['phone_no']);
                    if (!empty($phone_no) && is_numeric($phone_no)) {
                        $phone_no = "+$phone_no";
                    } else {
                        $phone_no = '';
                    }
                    $guest_data['phone_no'] = $phone_no;

                    if ($guest->phone_no != $guest_data['phone_no'] && $guest_data['phone_no'] != '') {
                        $__update .= "phone_no: $guest->phone_no to " . $guest_data['phone_no'] . ", ";
                        $guest->phone_no = $guest_data['phone_no'];
                    }

                    if (is_string($guest_data['firstname']) && $guest_data['firstname'] != $guest->firstname) {
                        if (!is_array($guest_data['firstname'])) {
                            $__update .= "firstname: $guest->firstname to " . $guest_data['firstname'] . ", ";
                            $guest->firstname = $guest_data['firstname'];
                        }
                    }
                    if (is_string($guest_data['lastname']) && $guest_data['lastname'] != $guest->lastname) {
                        $__update .= "lastname: $guest->lastname to " . $guest_data['lastname'] . ", ";
                        $guest->lastname = $guest_data['lastname'];
                    }

                    if (is_string($guest_data['address']) && $guest_data['address'] != $guest->address) {
                        $__update .= "address: $guest->address to " . $guest_data['address'] . ", ";
                        $guest->address = $guest_data['address'];
                        if (strlen($guest->address) >= 100) {
                            $guest->address = substr($guest->address, 0, 99);
                        }
                    }
                    if (is_string($guest_data['city']) && $guest_data['city'] != $guest->city) {
                        $__update .= "city: $guest->city to " . $guest_data['city'] . ", ";
                        $guest->city = $guest_data['city'];
                    }
                    if ($guest_data['zipcode'] != $guest->zipcode) {
                        if (!is_array($guest_data['zipcode']) && !is_array($guest->zipcode)) {
                            $__update .= "zipcode: $guest->zipcode to " . $guest_data['zipcode'] . ", ";
                            $guest->zipcode = $guest_data['zipcode'];
                        }
                    }
                    if ($guest_data['state'] != $guest->state) {
                        if (!is_array($guest_data['state']) && !is_array($guest->state)) {
                            $__update .= "state: $guest->state to " . $guest_data['state'] . ", ";
                            $guest->state = $guest_data['state'];
                        }
                    }
                    if ($__update != '') {
                        $guest->updated_on = date('Y-m-d H:i:s');
                        $guest->updated_by = $this->staff_id;
                        $guest->save();
                        $this->saveLogTracker([
                            'hotel_id'  => $this->hotel_id,
                            'module_id' => 8,
                            'action'    => 'update',
                            'prim_id'   => $guest->guest_id,
                            'staff_id'  => $this->staff_id,
                            'date_time' => date('Y-m-d H:i:s'),
                            'comments'  => 'Guest Update',
                            'type'      => 'API-OPERA'
                        ]);
                    }
                    // } else {
                    //     $phone_no = str_replace(["-", ".", " ", "(", ")", "*", "/", "na", "+"], "", $guest_data['phone_no']);
                    //     if (!empty($phone_no) && is_numeric($phone_no)) {
                    //         $phone_no = "+$phone_no";
                    //     } else {
                    //         $phone_no = '';
                    //     }
                    //     $guest_data['phone_no'] = $phone_no;
                    //     $guest_data['angel_status'] =  $angel_status;
                    //     $new_guest = \App\Models\GuestRegistration::create($guest_data);
                    //     \App\Models\IntegrationsGuestInformation::create([
                    //         'hotel_id'      => $this->hotel_id,
                    //         'guest_id'      => $new_guest->guest_id,
                    //         'guest_number'  => $unique_id
                    //     ]);
                    //     $IntegrationsGuestInformation->delete();

                    //     $this->saveLogTracker([
                    //         'hotel_id'  => $this->hotel_id,
                    //         'staff_id'  => $this->staff_id,
                    //         'prim_id'   => $new_guest->guest_id,
                    //         'module_id' => 8,
                    //         'action'    => 'add',
                    //         'date_time' => date('Y-m-d H:i:s'),
                    //         'comments'  => 'Guest Created',
                    //         'type'      => 'API-OPERA'
                    //     ]);
                    // }
                } else {
                    $phone_no = str_replace(["-", ".", " ", "(", ")", "*", "/", "na", "+"], "", $guest_data['phone_no']);
                    if (!empty($phone_no) && is_numeric($phone_no)) {
                        $phone_no = "+$phone_no";
                    } else {
                        $phone_no = "";
                    }
                    $guest_data['phone_no'] = $phone_no;
                    $guest_data['angel_status'] =  $angel_status;
                    if (is_array($guest_data['zipcode'])) {
                        $guest_data['zipcode'] = '';
                    }
                    if (is_array($guest_data['firstname'])) {
                        $guest_data['firstname'] = '';
                    }
                    if (is_array($guest_data['lastname'])) {
                        $guest_data['lastname'] = '';
                    }
                    if (is_array($guest_data['state'])) {
                        $guest_data['state'] = '';
                    }
                    try {
                        // truncate  address before save
                        if (is_string($guest_data['address']) && strlen($guest_data['address']) >= 100) {
                            $guest_data['address'] = substr($guest_data['address'], 0, 99);
                        }
                    } catch (\Throwable $th) {
                        $guest_data['address'] = '';
                    }

                    $guest_data['city'] = is_string($guest_data['city']) ? $guest_data['city'] : '';
                    $guest_data['email_address'] = is_string($guest_data['email_address']) ? $guest_data['email_address'] : '';

                    $new_guest = \App\Models\GuestRegistration::create($guest_data);
                    // \App\Models\IntegrationsGuestInformation::create([ 'hotel_id'      => $this->hotel_id, 'guest_id'      => $new_guest->guest_id, 'guest_number'  => $unique_id ]);

                    $this->saveLogTracker([
                        'hotel_id'  => $this->hotel_id,
                        'staff_id'  => $this->staff_id,
                        'prim_id'   => $new_guest->guest_id,
                        'module_id' => 8,
                        'action'    => 'add',
                        'date_time' => date('Y-m-d H:i:s'),
                        'comments'  => 'Guest Created',
                        'type'      => 'API-OPERA'
                    ]);
                }
            }
            // DB::commit();
            date_default_timezone_set('UTC');
        } catch (Exception $e) {
            \Log::error('Error OPERA PROFILE');
            \Log::error($e);
            // DB::rollback();
        }
    }

    public function getRoom($location)
    {
        $is_numeric = false;
        if (is_numeric($location)) {
            $location = intval($location);
            $location *= 1;
            $is_numeric = true;
        }

        $add = true;
        if (
            (($this->hotel_id == '275' || $this->hotel_id == '281') && is_numeric($location) && $location >= 9000 && $location <= 9600) ||
            ($this->hotel_id == '207' && (!($location <= 202)  || !is_numeric($location)))
            || ($this->hotel_id == '289' && ((is_numeric($location) && ($location >= 4000)) || !is_numeric($location))) ||
            ($this->hotel_id == '239' && ((is_numeric($location) && ($location >= 3000)) || !is_numeric($location))) ||
            ($this->hotel_id == '303' && ((is_numeric($location) && ($location >= 4300)) || !is_numeric($location)))
        ) {
            $add = false;
        }
        if ($add) {
            $this->configTimeZone($this->hotel_id);
            // if (substr($location, 0, 1) == '0' && ($this->hotel_id == 189 || $this->hotel_id == 296)) {
            //     $location = substr($location, 1);
            // }
            // $room = \App\Models\HotelRoom::where('hotel_id', $this->hotel_id)
            //     ->where(function ($query) use ($location) {
            //         return $query
            //             ->where('location', $location);
            //         // ->orWhere('room_id', $location);
            //     })->where('active', 1)
            //     ->first();

            $query = "location = '$location'";
            if ($is_numeric) {
                $query = "(location * 1) = '$location'";
            }

            $room = \App\Models\HotelRoom::where('hotel_id', $this->hotel_id)->where("active", 1)->whereRaw($query, [])->first();

            if ($room) {
                date_default_timezone_set('UTC');
                return [
                    "room_id"   => $room->room_id,
                    "room"      => $room->location
                ];
            } else {
                if ($this->hotel_id == 266) {
                    return null;
                }
                if (\App\Models\HotelRoom::where('location', substr($location, 1))->where('hotel_id', $this->hotel_id)->count() >= 1) {
                    return null;
                }
                $room = \App\Models\HotelRoom::create([
                    'hotel_id'      => $this->hotel_id,
                    'location'      => $location,
                    'created_by'    => $this->staff_id,
                    'created_on'    => date('Y-m-d H:i:s'),
                    'updated_by'    => null,
                    'updated_on'    => null,
                    'active'        => 1,
                    'angel_view'    => 1,
                    'device_token'  => ''
                ]);
                date_default_timezone_set('UTC');
                \App\Models\LogTracker::create([
                    'hotel_id'  => $this->hotel_id,
                    'staff_id'  => $this->staff_id,
                    'prim_id'   => $room->room_id,
                    'module_id' => 17,
                    'action'    => 'add',
                    'date_time' => date('Y-m-d H:i:s'),
                    'comments'  => '',
                    'type'      => 'API-OPERA'
                ]);
                return [
                    "room_id" => $room->room_id,
                    "room" => $room->location
                ];
            }
        }

        return null;
    }

    public function RoomMove($reservation, $new_reservation)
    {
        $room_move = [
            'guest_id'          => $reservation->guest_id,
            'current_room_no'   => $reservation->room_no,
            'new_room_no'       => $new_reservation['room_no'],
            'hotel_id'          => $this->hotel_id,
            'created_by'        => $this->staff_id,
            'created_on'        => date('Y-m-d H:i:s'),
            'status'            => 1,
            'active'            => 1,
            'updated_by'        => $this->staff_id
        ];
        \App\Models\RoomMove::create($room_move);
    }

    public function saveLogTracker($__log_tracker)
    {
        $track_id = \App\Models\LogTracker::create($__log_tracker)->track_id;
        return $track_id;
    }

    public function RoomStatusUpdateBERequest()
    {
        date_default_timezone_set('UTC');
        $data_elements = array_get($this->data, 'DataElements.DataElement', null);
        if (!is_null($data_elements)) {
            $hskLog = [
                'RoomNumber'    => '',
                'RoomStatus'    => '',
                'RoomType'      => '',
                'resortId'      => array_get($this->data, 'ResortId', ''),
                'created_at'    => date('Y-m-d H:i:s'),
                'xml'           => $this->xml,
                'MessageID'     => $this->MessageID,
            ];

            if (!is_array($data_elements)) {
                $data_elements = [$data_elements];
            }

            foreach ($data_elements as  $data_element) {
                if (array_get($data_element, '@attributes.name', '') == 'RoomNumber') {
                    $hskLog['RoomNumber'] = array_get($data_element, '@attributes.newData');
                }
                if (array_get($data_element, '@attributes.name', '') == 'RoomStatus') {
                    $hskLog['RoomStatus'] = array_get($data_element, '@attributes.newData');
                }
            }

            $this->createHsk([$hskLog]);
            $oracle_housekeeping_data = \App\Models\Log\OracleHousekeeping::create($hskLog);
            $hskLog['idLog'] = $oracle_housekeeping_data->id;
            $this->sendMonitoringApp($hskLog, 'LogOpera_HSK');
        }
    }

    public function createHSK($hsk_data)
    {
        $HousekeepingData             = [];
        $HousekeepingData["hotel_id"] = $this->hotel_id;
        $HousekeepingData["staff_id"] = $this->staff_id;
        $HousekeepingData["rooms"]    = [];

        foreach ($hsk_data as $key => $room_data) {
            if (!is_null($room_data['RoomNumber'])) {
                $room = $this->getRoom($room_data['RoomNumber']);
                if (!is_null($room)) {
                    $ooo = ($room_data['RoomStatus'] == "Out of Order"  || $room_data['RoomStatus'] == "OutOfOrder") ? true : false;
                    $oos = ($room_data['RoomStatus'] == "Out of Service" || $room_data['RoomStatus'] == "OutOfService") ? true : false;

                    if ($ooo) {
                        $this->FrontdeskStatus($room['room_id'], 1, false);
                    } elseif ($oos) {
                        $this->FrontdeskStatus($room['room_id'], 2, false);
                    } else {
                        $this->FrontdeskStatus($room['room_id'], 1, true);
                        $this->FrontdeskStatus($room['room_id'], 2, true);
                    }
                    $_d["room_id"] = $room["room_id"];
                    try {
                        $hk_status = $this->HotelHousekeepingConfig[$room_data['RoomStatus']]["codes"][0]["hk_status"];
                    } catch (\Throwable $th) {
                        echo ($th->getMessage());
                        echo ($room["room_id"]);
                        break;
                    }
                    if ($hk_status == 4) {
                        // Esta información se proporciona cuando se realiza un sync
                        if (array_has($room_data, 'reservation_data')) {
                            $GuestCheckinDetails = $room_data['reservation_data'];
                            if (!empty($GuestCheckinDetails) && $this->hotel_id != 238) $hk_status = 3;
                        }
                    }
                    $hsk_cleanning = HousekeepingCleanings::where('hotel_id', $this->hotel_id)
                        ->where('room_id', $room['room_id'])
                        ->orderBy('assigned_date', 'desc')
                        ->orderBy('cleaning_id', 'DESC')
                        ->first();

                    if ($hsk_cleanning && $hsk_cleanning->hk_status == 2) $hk_status = 2;

                    $_d["hk_status"] = $hk_status;
                    $HousekeepingData["rooms"][] = $_d;

                    // Add log tracker
                    $this->configTimeZone($this->hotel_id);
                    $this->saveLogTracker([
                        'hotel_id'  => $this->hotel_id,
                        'module_id' => 36,
                        'action'    => 'HSK STATUS',
                        'prim_id'   => $hsk_cleanning ? $hsk_cleanning->cleaning_id : 0,
                        'staff_id'  => $this->staff_id,
                        'date_time' => date('Y-m-d H:i:s'),
                        'comments'  => $this->MessageID,
                        'type'      => 'API-OPERA'
                    ]);
                    date_default_timezone_set('UTC');
                }
            }
        }
        $this->SendHSK($HousekeepingData);
    }

    public function SendHSK($data)
    {

        if (count($data["rooms"]) > 0) {
            if (strpos(url('/'), 'api-dev') !== false) {
                $url = "https://dev4.mynuvola.com/index.php/housekeeping/pmsHKChange";
            } else {
                $url = "https://hotel.mynuvola.com/index.php/housekeeping/pmsHKChange";
            }
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL             => $url,
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_ENCODING        => "",
                CURLOPT_MAXREDIRS       => 10,
                CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST   => "POST",
                CURLOPT_POSTFIELDS      => json_encode($data)
            ));
            $response = curl_exec($curl);
            $err = curl_error($curl);
            if ($err) {
                \Log::error("Error en Job Opera SendHSK");
                \Log::error($err);
            } else {
                // \Log::info($response);
            }
            curl_close($curl);
        }
    }

    public function OracleSync($rooms)
    {

        $this->createHSK($rooms['rooms']);
        foreach ($rooms['rooms'] as $value) {
            if (!empty($value['reservation_data'])) {
                $addressString = "";
                $addressData = array_get($value, 'reservation_data.AddressLine');
                if (!is_array($addressData)) {
                    $addressData = [$addressData];
                }
                foreach ($addressData as  $address) {
                    $addressString .= $address != '' ? $address . ';' : '';
                }
                $profile = [
                    'resortId'      => array_get($value, 'reservation_data.ResortId', ''),
                    'FirstName'     => array_get($value, 'reservation_data.FirstName', ''),
                    'LastName'      => array_get($value, 'reservation_data.LastName', ''),
                    'EMAIL'         => '',
                    'MOBILE'        => '',
                    'AddressLine'   => $addressString,
                    'CityName'      => array_get($value, 'reservation_data.CityName', ''),
                    'PostalCode'    => array_get($value, 'reservation_data.PostalCode', ''),
                    'CountryCode'   => array_get($value, 'reservation_data.CountryCode', ''),
                    'UniqueID'      => array_get($value, 'reservation_data.ProfileID'),
                    'birthDate'     => '',
                    'created_at'    => date('Y-m-d H:i:s'),
                    'created_by'    => 1
                ];

                $this->ProfileRegistration($profile);

                $reservation = [
                    'resortId'          => array_get($value, 'reservation_data.ResortId'),
                    'ReservationID'     => array_get($value, 'reservation_data.ReservationID'),
                    'reservationStatus' => array_get($value, 'reservation_data.reservationStatus'),
                    'roomNumber'        => array_get($value, 'RoomNumber'),
                    'UniqueID'          => array_get($value, 'reservation_data.ProfileID'),
                    'checkInDate'       => array_get($value, 'reservation_data.Start'),
                    'checkOutDate'      => array_get($value, 'reservation_data.End'),
                    'ageQualifyingCode' => '',
                    'GuestCount'        => '',
                    'state'             => '1',
                    'created_at'        => date('Y-m-d H:i:s')
                ];

                $this->customWriteLog("sync_opera", $this->hotel_id, "DATOS DENTRO DE HSK RESERVARION:");
                $this->customWriteLog("sync_opera", $this->hotel_id, json_encode($reservation));

                if ($reservation['ReservationID'] != '') {
                    $suites = $this->getSuites($reservation['roomNumber']);
                    if ($suites) {

                        $this->is_suite = true;
                        $_rs = $reservation['ReservationID'];
                        $this->removeReservation($_rs);
                        $resp = $this->isAnotherSuite($_rs, $reservation['roomNumber']);
                        if ($resp) {
                            $this->removeSuitesReservation($_rs);
                        }
                        foreach ($suites as  $suite) {
                            $reservation['roomNumber'] = $suite['location'];
                            $reservation['ReservationID'] = $_rs . '_' . $suite['location'];
                            $OracleReservation = \App\Models\Log\OracleReservation::create($reservation);
                            $reservation['idLog'] = $OracleReservation->id;
                            $this->GuestRegistration($OracleReservation);
                            $this->sendMonitoringApp($reservation, 'LogOpera_Reservation');
                        }
                    } else {
                        $this->customWriteLog("sync_opera", $this->hotel_id, "NO == ES UN SUITE");
                        $this->removeSuitesReservation($reservation['ReservationID']);

                        $this->customWriteLog("sync_opera", $this->hotel_id, "DATA DEL RESERVATION");
                        $this->customWriteLog("sync_opera", $this->hotel_id, json_encode($reservation));

                        $OracleReservation = \App\Models\Log\OracleReservation::create($reservation);
                        $reservation['idLog'] = $OracleReservation->id;
                        $this->GuestRegistration($OracleReservation);
                        $this->sendMonitoringApp($reservation, 'LogOpera_Reservation');
                    }
                }
            } else {

                $this->customWriteLog("sync_opera", $this->hotel_id, "NO TIENE RESERVA STATUS:");
                $this->customWriteLog("sync_opera", $this->hotel_id, json_encode($value));

                $this->configTimeZone($this->hotel_id);
                $date = date('Y-m-d H:i:s');
                $room = $this->getRoom(array_get($value, 'RoomNumber'));

                $this->customWriteLog("sync_opera", $this->hotel_id, "ROOM ENCONTRADO EN NUVOLA:");
                $this->customWriteLog("sync_opera", $this->hotel_id, json_encode($room));

                if (!is_null($room)) {
                    $reservations = GuestCheckinDetails::where('hotel_id', $this->hotel_id)
                        ->where('room_no', $room['room_id'])
                        ->where('status', 1)
                        ->where('reservation_status', 1)->get();
                    //->whereDate('check_out', '<=', date('Y-m-d'))->get();
                    // \Log::info(json_encode($reservations));

                    //$this->customWriteLog("sync_opera", $this->hotel_id, "DATOS CONSULTA EN NUVOLA:");
                    //$this->customWriteLog("sync_opera", $this->hotel_id, $this->hotel_id);
                    //$this->customWriteLog("sync_opera", $this->hotel_id, $room['room_id']);
                    //$this->customWriteLog("sync_opera", $this->hotel_id, date('Y-m-d'));

                    foreach ($reservations as $reservation) {
                        if ($reservation->reservation_status == 1) {
                            $reservation->status = 0;
                            $reservation->reservation_status = 3;
                            if ($reservation->check_out > $date) {
                                $reservation->check_out =  $date;
                            }
                        } elseif ($reservation->reservation_status == 0) {
                            $reservation->status = 0;
                            $reservation->reservation_status = 2;
                        }
                        $reservation->save();
                    }
                    date_default_timezone_set('UTC');
                }
            }
        }
    }

    public function ReservationSync($room_number, $unique_id)
    {
        $this->configTimeZone($this->hotel_id);
        $date = date('Y-m-d H:i:s');
        $room = $this->getRoom($room_number);
        if (!is_null($room)) {
            $reservations = GuestCheckinDetails::where('room_no', $room['room_id'])->where('hotel_id', $this->hotel_id)
                ->where('reservation_number', '!=', $unique_id)
                ->whereIn('status', [0, 1])
                ->whereIn('reservation_status', [0, 1])
                ->where(function ($query) {
                    $query->where('check_out', '<', date('Y-m-d H:i:s'))
                        ->orWhere('check_in', '<', date('Y-m-d H:i:s'));
                })
                ->get();

            foreach ($reservations as $reservation) {
                if ($reservation->reservation_status == 1) {
                    $reservation->status = 0;
                    $reservation->reservation_status = 3;
                    if ($reservation->check_out > $date) {
                        $reservation->check_out =  $date;
                    }
                } elseif ($reservation->reservation_status == 0) {
                    $reservation->status = 0;
                    $reservation->reservation_status = 2;
                }
                $reservation->save();
            }
            date_default_timezone_set('UTC');
        }
    }



    public function GetOracleRoomSync($config, $pms_hotel_id)
    {
        $this->configTimeZone($this->hotel_id);
        date_default_timezone_set('UTC');

        $timestamp  = date('Y-m-d\TH:i:s\Z');
        $username   = $config['username_send'];
        $password   = $config['password_send'];
        $url        = $config['url_send'];
        $from       = $config['from_send'];
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
                        <wsu:Expires>' . date('Y-m-d\TH:i:s\Z', strtotime($timestamp . ' +35 minutes')) . '</wsu:Expires>
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
                    ' . (!is_null($this->room_id) ? '<RoomNumber>' . $this->room_id . '</RoomNumber>' : '') . '
                </FetchRoomStatusRequest>
            </soap:Body>
        </soap:Envelope>
        ';
        $this->customWriteLog("sync_opera", $this->hotel_id, 'START CURL FetchRoomStatusRequest');
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL             => $url,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_ENCODING        => "",
            CURLOPT_MAXREDIRS       => 10,
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST   => "POST",
            CURLOPT_POSTFIELDS      => $xml,
            CURLOPT_HTTPHEADER      => [
                "SOAPAction: http://webservices.micros.com/htng/2008B/SingleGuestItinerary#FetchRoomStatus",
                "Content-Type: text/xml; charset=utf-8",
            ],
        ));
        $response   = curl_exec($curl);
        $this->customWriteLog("sync_opera", $this->hotel_id, 'END CURL FetchRoomStatusRequest');
        $err        = curl_error($curl);
        date_default_timezone_set('UTC');
        curl_close($curl);

        if ($err) {
            // \Log::error("GetOracleRoomSync");
            // \Log::info($err);
            return $err;
        } else {
            // \Log::error("GetOracleRoomSync");
            // \Log::info($response);
            // \Log::info($xml);
            $xmlString  = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
            $xml        = simplexml_load_string($xmlString);
            $str_json   = json_encode($xml);
            // \Log::info($str_json);
            $json       = json_decode($str_json, true);
            $fetch_room = array_get($json, 'Body.FetchRoomStatusResponse');
            return $fetch_room;
        }
    }




    public function getReservationRoom($room_id, $config, $pms_hotel_id)
    {
        $this->configTimeZone($this->hotel_id);
        date_default_timezone_set('UTC');

        $timestamp  = date('Y-m-d\TH:i:s\Z');
        $username   = $config['username_send'];
        $password   = $config['password_send'];
        $url        = $config['url_sync'];
        $from       = $config['from_send'];

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
                        <wsu:Expires>' . date('Y-m-d\TH:i:s\Z', strtotime($timestamp . ' +35 minutes')) . '</wsu:Expires>
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
                    <ReservationLookupData reservationStatus="CHECKED_IN">
                        <RoomNumber>' . $room_id . '</RoomNumber>
                        <ResortId>' . $pms_hotel_id . '</ResortId>
                    </ReservationLookupData>
                </ReservationLookupRequest>
            </soap:Body>
        </soap:Envelope>
        ';
        $curl = curl_init();
        $actions = ($this->hotel_id != 281) && ($this->hotel_id != 266) && ($this->hotel_id != 296) && ($this->hotel_id != 238) && ($this->hotel_id != 314) && ($this->hotel_id != 289) && ($this->hotel_id != 303) && ($this->hotel_id != 443) && ($this->hotel_id != 207) ? array(
            "Content-Type: text/xml;charset=UTF-8",
            "SOAPAction: http://webservices.micros.com/htng/2008B/SingleGuestItinerary#ReservationLookup",
        ) : array(
            "Content-Type: text/xml;charset=UTF-8",
            "Action: http://webservices.micros.com/htng/2008B/SingleGuestItinerary#ReservationLookup",
        );
        $this->customWriteLog("sync_opera", $this->hotel_id, 'START CURL ReservationLookupRequest');
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_HTTPHEADER => $actions,
        ));


        $this->customWriteLog("sync_opera", $this->hotel_id, "XML SEND RESERVATION ROOM");
        $this->customWriteLog("sync_opera", $this->hotel_id, $xml);

        $response = curl_exec($curl);
        //$this->customWriteLog("sync_opera", $this->hotel_id, 'END CURL ReservationLookupRequest');
        $err = curl_error($curl);
        curl_close($curl);
        date_default_timezone_set('UTC');
        if ($err) {
            \Log::error("Error en getReservationRoom curl");
            \Log::error($err);

            return null;
        } else {
            $xmlString  = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
            // \Log::alert($response);

            $this->customWriteLog("sync_opera", $this->hotel_id, "RESPONSE XML");
            $this->customWriteLog("sync_opera", $this->hotel_id, $response);


            $xml        = simplexml_load_string($xmlString);
            $str_json   = json_encode($xml);
            $json       = json_decode($str_json, true);
            $resp = array_get($json, 'Body.ReservationLookupResponse.ReservationLookups.ReservationLookup');
            try {
                $resp[0];
            } catch (\Exception $e) {
                $resp = [$resp];
            }
            return $resp[0];
        }
    }


    /**
     * Obtener los datos del Guest pero con una reserva
     */
    public function getReservationRoomReserved($room_id, $config, $pms_hotel_id)
    {
        $this->configTimeZone($this->hotel_id);
        date_default_timezone_set('UTC');

        $timestamp  = date('Y-m-d\TH:i:s\Z');
        $username   = $config['username_send'];
        $password   = $config['password_send'];
        $url        = $config['url_sync'];
        $from       = $config['from_send'];

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
                        <wsu:Expires>' . date('Y-m-d\TH:i:s\Z', strtotime($timestamp . ' +35 minutes')) . '</wsu:Expires>
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
                        <RoomNumber>' . $room_id . '</RoomNumber>
                        <ResortId>' . $pms_hotel_id . '</ResortId>
                    </ReservationLookupData>
                </ReservationLookupRequest>
            </soap:Body>
        </soap:Envelope>
        ';
        $curl = curl_init();
        $actions = ($this->hotel_id != 281) && ($this->hotel_id != 266) && ($this->hotel_id != 296) && ($this->hotel_id != 238) && ($this->hotel_id != 314) && ($this->hotel_id != 289) && ($this->hotel_id != 303) && ($this->hotel_id != 443) && ($this->hotel_id != 207) ? array(
            "Content-Type: text/xml;charset=UTF-8",
            "SOAPAction: http://webservices.micros.com/htng/2008B/SingleGuestItinerary#ReservationLookup",
        ) : array(
            "Content-Type: text/xml;charset=UTF-8",
            "Action: http://webservices.micros.com/htng/2008B/SingleGuestItinerary#ReservationLookup",
        );
        $this->customWriteLog("sync_opera", $this->hotel_id, 'START CURL ReservationLookupRequest');
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_HTTPHEADER => $actions,
        ));


        $this->customWriteLog("sync_opera", $this->hotel_id, "XML SEND RESERVATION ROOM");
        $this->customWriteLog("sync_opera", $this->hotel_id, $xml);

        $response = curl_exec($curl);
        //$this->customWriteLog("sync_opera", $this->hotel_id, 'END CURL ReservationLookupRequest');
        $err = curl_error($curl);
        curl_close($curl);
        date_default_timezone_set('UTC');
        if ($err) {
            \Log::error("Error en getReservationRoom curl");
            \Log::error($err);

            return null;
        } else {
            $xmlString  = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
            // \Log::alert($response);

            $this->customWriteLog("sync_opera", $this->hotel_id, "RESPONSE XML");
            $this->customWriteLog("sync_opera", $this->hotel_id, $response);


            $xml        = simplexml_load_string($xmlString);
            $str_json   = json_encode($xml);
            $json       = json_decode($str_json, true);
            $resp = array_get($json, 'Body.ReservationLookupResponse.ReservationLookups.ReservationLookup');


            #$this->customWriteLog("sync_opera", $this->hotel_id, "RESPONSE XML a Enviar");
            #$this->customWriteLog("sync_opera", $this->hotel_id, json_encode($resp));


            try {
                $resp[0];
            } catch (\Exception $e) {
                $resp = [$resp];
            }
            return $resp[0];
        }
    }

    public function SyncOracleHSK()
    {
        $IntegrationsActive = \App\Models\IntegrationsActive::where('hotel_id', $this->hotel_id)
            ->where('int_id', 5)
            ->where('state', 1)
            ->first();
        $this->customWriteLog("sync_opera", $this->hotel_id, 'START SYNC');

        if ($IntegrationsActive) {
            $this->configTimeZone($this->hotel_id);
            $data = $this->GetOracleRoomSync($IntegrationsActive->config, $IntegrationsActive->pms_hotel_id);
            if ($data) {
                $hsk_status = [
                    'hotel_id' => $this->hotel_id,
                    'staff_id' => $IntegrationsActive->created_by,
                    'rooms'    => []
                ];
                $fetch_room = array_get($data, 'FetchRoomStatus', []);
                try {
                    $fetch_room[0];
                } catch (\Exception $e) {
                    $fetch_room = [$fetch_room];
                }
                $this->customWriteLog("sync_opera", $this->hotel_id, "FETCH ROOM DATA:");
                $this->customWriteLog("sync_opera", $this->hotel_id, json_encode($fetch_room));
                foreach ($fetch_room as $room) {
                    $this->customWriteLog("sync_opera", $this->hotel_id, json_encode($room));
                    $room_no = array_get($room, 'RoomNumber');

                    if (!$room_no) {
                        $room_no  = $this->room_id ? $this->room_id : null;
                    }
                    if ($room_no > 4000 && $this->hotel_id == 289) {
                        $add = false;
                        \Log::info('entra');
                    }
                    $add = true;
                    if (($IntegrationsActive->pms_hotel_id == 'MI01' || $IntegrationsActive->pms_hotel_id == 'CVS') && is_numeric($room_no) && $room_no >= 9000 && $room_no <= 9600) {
                        $add = false;
                    }
                    if ($IntegrationsActive->pms_hotel_id == 'MI01' && substr($room_no, 0, 3) == 'CAB') {
                        $add = false;
                    }
                    if (($IntegrationsActive->pms_hotel_id == '04130' || $IntegrationsActive->pms_hotel_id == 'MCOGR') && (($room_no >= 9000 && $room_no <= 9600))) {
                        $add = false;
                    }
                    if ($add) {
                        $this->customWriteLog("sync_opera", $this->hotel_id, "ROOM NUMBER HOTEL:");
                        $this->customWriteLog("sync_opera", $this->hotel_id, $room_no);
                        $room_status = array_get($room, 'RoomStatus');
                        $reservation_data = [];

                        $this->customWriteLog("sync_opera", $this->hotel_id, "ROOM STATUS:");
                        $this->customWriteLog("sync_opera", $this->hotel_id, array_get($room, 'FrontOfficeStatus'));

                        // && $this->hotel_id != 289
                        if (array_get($room, 'FrontOfficeStatus') == 'OCC') {
                            // $room_status = 'Clean';

                            $reservation = $this->getReservationRoom($room_no, $IntegrationsActive->config, $IntegrationsActive->pms_hotel_id);
                            $this->customWriteLog("sync_opera", $this->hotel_id, $room_no . json_encode($IntegrationsActive->config) . $IntegrationsActive->pms_hotel_id);
                            $this->customWriteLog("sync_opera", $this->hotel_id, 'reservation sync' . json_encode($reservation));

                            $reservation_data = [
                                'ReservationID'     => array_get($reservation, 'ReservationID', ''),
                                'ProfileID'         => array_get($reservation, 'ProfileID', ''),
                                'Start'             => array_get($reservation, 'DateRange.Start', ''),
                                'End'               => array_get($reservation, 'DateRange.End', ''),
                                'FirstName'         => array_get($reservation, 'ProfileInfo.FirstName', ''),
                                'LastName'          => array_get($reservation, 'ProfileInfo.LastName', ''),
                                'AddressLine'       => array_get($reservation, 'ReservationAddress.AddressLine', ''),
                                'CityName'          => array_get($reservation, 'ReservationAddress.CityName', ''),
                                'StateProv'         => array_get($reservation, 'ReservationAddress.StateProv', ''),
                                'CountryCode'       => array_get($reservation, 'ReservationAddress.CountryCode', ''),
                                'PostalCode'        => array_get($reservation, 'ReservationAddress.PostalCode', ''),
                                'ResortId'          => array_get($reservation, 'ResortId', ''),
                                'reservationStatus' => array_get($reservation, '@attributes.reservationStatus')
                            ];
                        }


                        //Validamos si la Room está VAC
                        


                        $hsk_status['rooms'][] = [
                            'RoomNumber'        => $room_no,
                            'RoomStatus'        => $room_status,
                            'reservation_data'  => $reservation_data
                        ];
                        $this->customWriteLog("sync_opera", $this->hotel_id, "ROOM FINAL:");
                        $this->customWriteLog("sync_opera", $this->hotel_id, json_encode($hsk_status));
                    }
                }

                $this->OracleSync($hsk_status);
                $HousekeepingPreferences = \App\Models\HousekeepingPreferences::where('hotel_id', $this->hotel_id)->first();
                if ($HousekeepingPreferences) {
                    $HousekeepingPreferences->sync_last_update = date('Y-m-d H:i:s');
                    $HousekeepingPreferences->save();
                }
                // $this->saveLogTracker([
                //     'hotel_id'  => $this->hotel_id,
                //     'module_id' => 17,
                //     'action'    => 'resync',
                //     'prim_id'   => 0,
                //     'staff_id'  => $this->staff_id,
                //     'date_time' => date('Y-m-d H:i:s'),
                //     'comments'  => 'Sync-Opera',
                //     'type'      => 'API-OPERA'
                //     ]);
                if (!$this->room_id) {
                    // $this->check_out_reserve();
                }
                date_default_timezone_set('UTC');
            }
        }
    }



    /**
     * Function SYNC Reserved
     */
    public function SyncOracleHSKReserved()
    {
        $IntegrationsActive = \App\Models\IntegrationsActive::where('hotel_id', $this->hotel_id)
            ->where('int_id', 5)
            ->where('state', 1)
            ->first();
        $this->customWriteLog("sync_opera", $this->hotel_id, 'START SYNC');

        if ($IntegrationsActive) {
            $this->configTimeZone($this->hotel_id);
            $data = $this->GetOracleRoomSync($IntegrationsActive->config, $IntegrationsActive->pms_hotel_id);
            if ($data) {
                $hsk_status = [
                    'hotel_id' => $this->hotel_id,
                    'staff_id' => $IntegrationsActive->created_by,
                    'rooms'    => []
                ];
                $fetch_room = array_get($data, 'FetchRoomStatus', []);
                try {
                    $fetch_room[0];
                } catch (\Exception $e) {
                    $fetch_room = [$fetch_room];
                }
                $this->customWriteLog("sync_opera", $this->hotel_id, "FETCH ROOM DATA:");
                $this->customWriteLog("sync_opera", $this->hotel_id, json_encode($fetch_room));
                foreach ($fetch_room as $room) {
                    $this->customWriteLog("sync_opera", $this->hotel_id, json_encode($room));
                    $room_no = array_get($room, 'RoomNumber');

                    if (!$room_no) {
                        $room_no  = $this->room_id ? $this->room_id : null;
                    }
                    if ($room_no > 4000 && $this->hotel_id == 289) {
                        $add = false;
                        \Log::info('entra');
                    }
                    $add = true;
                    if (($IntegrationsActive->pms_hotel_id == 'MI01' || $IntegrationsActive->pms_hotel_id == 'CVS') && is_numeric($room_no) && $room_no >= 9000 && $room_no <= 9600) {
                        $add = false;
                    }
                    if ($IntegrationsActive->pms_hotel_id == 'MI01' && substr($room_no, 0, 3) == 'CAB') {
                        $add = false;
                    }
                    if (($IntegrationsActive->pms_hotel_id == '04130' || $IntegrationsActive->pms_hotel_id == 'MCOGR') && (($room_no >= 9000 && $room_no <= 9600))) {
                        $add = false;
                    }
                    if ($add) {
                        $this->customWriteLog("sync_opera", $this->hotel_id, "ROOM NUMBER HOTEL:");
                        $this->customWriteLog("sync_opera", $this->hotel_id, $room_no);
                        $room_status = array_get($room, 'RoomStatus');
                        $reservation_data = [];

                        $this->customWriteLog("sync_opera", $this->hotel_id, "ROOM STATUS:");
                        $this->customWriteLog("sync_opera", $this->hotel_id, array_get($room, 'FrontOfficeStatus'));

                        // && $this->hotel_id != 289
                        //if (array_get($room, 'FrontOfficeStatus') == 'OCC') {
                            // $room_status = 'Clean';

                            $reservation = $this->getReservationRoomReserved($room_no, $IntegrationsActive->config, $IntegrationsActive->pms_hotel_id);
                            $this->customWriteLog("sync_opera", $this->hotel_id, $room_no . json_encode($IntegrationsActive->config) . $IntegrationsActive->pms_hotel_id);
                            $this->customWriteLog("sync_opera", $this->hotel_id, 'reservation sync' . json_encode($reservation));

                            $reservation_data = [
                                'ReservationID'     => array_get($reservation, 'ReservationID', ''),
                                'ProfileID'         => array_get($reservation, 'ProfileID', ''),
                                'Start'             => array_get($reservation, 'DateRange.Start', ''),
                                'End'               => array_get($reservation, 'DateRange.End', ''),
                                'FirstName'         => array_get($reservation, 'ProfileInfo.FirstName', ''),
                                'LastName'          => array_get($reservation, 'ProfileInfo.LastName', ''),
                                'AddressLine'       => array_get($reservation, 'ReservationAddress.AddressLine', ''),
                                'CityName'          => array_get($reservation, 'ReservationAddress.CityName', ''),
                                'StateProv'         => array_get($reservation, 'ReservationAddress.StateProv', ''),
                                'CountryCode'       => array_get($reservation, 'ReservationAddress.CountryCode', ''),
                                'PostalCode'        => array_get($reservation, 'ReservationAddress.PostalCode', ''),
                                'ResortId'          => array_get($reservation, 'ResortId', ''),
                                'reservationStatus' => array_get($reservation, '@attributes.reservationStatus')
                            ];
                        //}


                        //Validamos si la Room está VAC
                        


                        $hsk_status['rooms'][] = [
                            'RoomNumber'        => $room_no,
                            'RoomStatus'        => $room_status,
                            'reservation_data'  => $reservation_data
                        ];
                        $this->customWriteLog("sync_opera", $this->hotel_id, "ROOM FINAL:");
                        $this->customWriteLog("sync_opera", $this->hotel_id, json_encode($hsk_status));
                    }
                }

                $this->OracleSync($hsk_status);
                $HousekeepingPreferences = \App\Models\HousekeepingPreferences::where('hotel_id', $this->hotel_id)->first();
                if ($HousekeepingPreferences) {
                    $HousekeepingPreferences->sync_last_update = date('Y-m-d H:i:s');
                    $HousekeepingPreferences->save();
                }
                // $this->saveLogTracker([
                //     'hotel_id'  => $this->hotel_id,
                //     'module_id' => 17,
                //     'action'    => 'resync',
                //     'prim_id'   => 0,
                //     'staff_id'  => $this->staff_id,
                //     'date_time' => date('Y-m-d H:i:s'),
                //     'comments'  => 'Sync-Opera',
                //     'type'      => 'API-OPERA'
                //     ]);
                if (!$this->room_id) {
                    // $this->check_out_reserve();
                }
                date_default_timezone_set('UTC');
            }
        }
    }




    public function getProfileData($unique_id, $resort_id)
    {
        $timestamp = date('Y-m-d\TH:i:s\Z');
        $username   = $this->config['username_send'];
        $password   = $this->config['password_send'];
        $url        = $this->config['url_sync'];
        $from       = $this->config['from_send'];

        $xml = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:wsa="http://schemas.xmlsoap.org/ws/2004/08/addressing" xmlns:wsse="http://docs.oasisopen.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasisopen.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
        <soap:Header>
           <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
              <wsu:Timestamp wsu:Id="TS-1DB19FB15198FE10A2159249621088842">
                 <wsu:Created>' . $timestamp . '</wsu:Created>
                 <wsu:Expires>' . date('Y-m-d\TH:i:s\Z', strtotime($timestamp . ' +35 minutes')) . '</wsu:Expires>
              </wsu:Timestamp>
              <wsse:UsernameToken wsu:Id="UsernameToken-1DB19FB15198FE10A2159249621088841">
                 <wsse:Username>' . $username . '</wsse:Username>
                 <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">' . $password . '</wsse:Password>
                 <wsu:Created>' . $timestamp . '</wsu:Created>
              </wsse:UsernameToken>
           </wsse:Security>
           <wsa:Action>http://htng.org/PWS/2008B/SingleGuestItinerary#FetchProfile</wsa:Action>
           <wsa:From>
              <wsa:Address>urn:' . $from . '</wsa:Address>
           </wsa:From>
           <wsa:MessageID>urn:uuid:09a2b665-41d0-4654-b49d-86e7d437e371</wsa:MessageID>
           <wsa:ReplyTo>
              <wsa:Address>http://schemas.xmlsoap.org/ws/2004/08/addressing/role/anonymous</wsa:Address>
           </wsa:ReplyTo>
           <wsa:To>http://www.micros.com/HTNGActivity/</wsa:To>
        </soap:Header>
        <soap:Body>
           <FetchProfileRequest xmlns="http://htng.org/PWS/2008B/SingleGuestItinerary/Name/Types">
              <ProfileID>' . $unique_id . '</ProfileID>
              <ResortId>' . $resort_id . '</ResortId>
           </FetchProfileRequest>
        </soap:Body>
     </soap:Envelope>';
        $curl = curl_init();
        $actions = ($this->hotel_id != 296) ? array(
            "Content-Type: text/xml;charset=UTF-8",
            "SOAPAction: http://htng.org/PWS/2008B/SingleGuestItinerary#FetchProfile",
        ) : array(
            "Content-Type: text/xml;charset=UTF-8",
            "SOAPAction: http://htng.org/PWS/2008B/SingleGuestItinerary#FetchProfile",

        );
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_SSL_VERIFYPEER  => 0,
            CURLOPT_SSL_VERIFYHOST  => 0,
            CURLOPT_HTTPHEADER => $actions,
        ));
        try {
            $response = curl_exec($curl);
        } catch (\Exception $e) {
            throw $e;
        }
        $err = curl_error($curl);
        curl_close($curl);
        date_default_timezone_set('UTC');
        if ($err) {
            \Log::info($err);
            return null;
        } else {
            $xmlString  = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
            $xmlString = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $xmlString);
            $xml        = simplexml_load_string($xmlString);
            $str_json   = json_encode($xml);
            $json       = json_decode($str_json, true);

            return array_get($json, 'Body.FetchProfileResponse');
        }
    }


    public function syncProfileData()
    {
        $IntegrationsActive = \App\Models\IntegrationsActive::where("hotel_id", $this->hotel_id)->first();
        $pms_hotel_id = $IntegrationsActive->pms_hotel_id;
        $guest = GuestRegistration::where('hotel_id', $this->hotel_id)->whereDate('created_on', '>=', '2020-11-15')->where('phone_no', '')->get();
        foreach ($guest as $g) {
            //     $int = IntegrationsGuestInformation::where('hotel_id', $this->hotel_id)->where('guest_id', $g->guest_id)->first();
            if (!empty($g->pms_unique_id)) {
                $guest_data = $this->getProfileData($g->pms_unique_id, $pms_hotel_id);
                \Log::alert(json_encode($guest_data));
                $guest_data['ResortId'] = $pms_hotel_id;
                $this->data = $guest_data;
                $resp = $this->ProfileRegistration();
            } else {
                \Log::error('no encontrado');
                \Log::info(json_encode($g));
            }
        }
    }



    public function FrontdeskStatus($room_id, $status, $sw = false)
    {
        if (!array_has($this->config, 'hk_reasons_id')) {
            return null;
        }
        // DB::beginTransaction();
        try {
            $this->configTimeZone($this->hotel_id);
            $date = date('Y-m-d H:i:s');
            $room_out_of_service = HotelRoomsOut::where('hotel_id', $this->hotel_id)
                ->where('room_id', $room_id)
                ->where('is_active', 1)
                ->whereRaw("'$date' BETWEEN start_date AND end_date")
                ->orderBy('room_out_id', 'desc')
                ->first();
            if (!$sw) {
                if (!$room_out_of_service) {
                    $data = [
                        'room_id' => $room_id,
                        'hotel_id' => $this->hotel_id,
                        'status' => $status,
                        'hk_reasons_id' => $this->config['hk_reasons_id'],
                        'start_date' => $date,
                        'end_date' => date('Y-m-d H:i:s', strtotime($date . ' +90 days')),
                        'comment' => 'Opera Api',
                        'is_active' => 1,
                        'created_by' => $this->staff_id,
                        'created_on' => $date,
                    ];
                    HotelRoomsOut::create($data);
                    // DB::commit();
                } else {
                    $room_out_of_service->end_date = date('Y-m-d H:i:s', strtotime($room_out_of_service->end_date . ' +30 days'));
                    $room_out_of_service->status = $status;
                    $room_out_of_service->save();
                    HotelRoomsOut::where('hotel_id', $this->hotel_id)
                        ->where('room_id', $room_id)
                        ->where('is_active', 1)
                        ->whereRaw("'$date' BETWEEN start_date AND end_date")
                        ->whereNotIn('room_out_id', [$room_out_of_service->room_out_id])
                        ->update(['is_active' => 0]);
                }
            } else {
                if ($room_out_of_service) {
                    $room_out_of_service->is_active = 0;
                    $room_out_of_service->updated_by  = $this->staff_id;
                    $room_out_of_service->updated_on  = $date;
                    $room_out_of_service->save();
                }
            }
            date_default_timezone_set('UTC');
            // DB::commit();
        } catch (\Exception $e) {
            \Log::error('Error in change out of service');
            \Log::error($e);
            // DB::rollback();
        }
    }


    public function sendMessages($hotel_id, $guest_id, $staff_id, $email = '', $phone = '', $back = false, $welcome = true, $angel = true)
    {
        //blocked hotels
        $blocked_hotels_angel = [
            239, 281, 2, 207, 296
        ];
        $blocked_hotels_welcome = [
            239, 281, 2, 207
        ];
        try {
            if ($this->is_suite && $this->messages_guest != 0) {
                $rs['send_welcome_blocked'] = true;
                return $rs;
            }
            // Validar que el hotel tenga el modulo de Angel activo.
            $str_query =  "";
            if ($angel)              $str_query .= "SELECT 'angel' type, rp.view access, g.angel_status FROM role_permission rp INNER JOIN menus m ON m.menu_id = 22 INNER JOIN roles r ON r.is_active = 1 AND r.hotel_id = $hotel_id AND lower(r.role_name) = 'hotel admin' INNER JOIN guest_registration g on g.hotel_id = r.hotel_id and g.guest_id = $guest_id WHERE rp.role_id = r.role_id AND rp.menu_id = m.menu_id";
            if ($angel && $welcome)  $str_query .= " UNION ";
            if ($welcome)            $str_query .= "SELECT 'schat' type, rp.view access, ''             FROM role_permission rp INNER JOIN menus m ON m.menu_id = 30 INNER JOIN roles r ON r.is_active = 1 AND r.hotel_id = $hotel_id AND lower(r.role_name) = 'hotel admin' WHERE rp.role_id = r.role_id AND rp.menu_id = m.menu_id";
            if (!empty($str_query)) {
                $result = DB::select($str_query);
                if (count($result) > 0) {
                    $send_angel      = false;
                    $send_welcome    = false;


                    foreach ($result as $kResult => $vResult) {
                        if ($vResult->type == 'angel' && $vResult->access == 1) $send_angel = $vResult->angel_status == 1 ? true : false;
                        if ($vResult->type == 'schat' && $vResult->access == 1) $send_welcome = true;
                    }
                    $client = new \GuzzleHttp\Client(['verify' => false]);


                    if (strpos(url('/'), 'api-dev') !== false) {
                        $url_app = 'https://integrations.mynuvola.com/index.php/send_invitations';
                    } else {
                        $url_app = 'https://hotel.mynuvola.com/index.php/send_invitations';
                    }

                    $rs = [];
                    if ($send_angel) {
                        if (!in_array($hotel_id, $blocked_hotels_angel)) {
                            $response = $client->request('POST', $url_app, [
                                'form_params' => [
                                    'hotel_id'  => $hotel_id,
                                    'guest_id'  => '',
                                    'staff_id'  => '',
                                    "type"      => 'angel',
                                    'email'     => $email,
                                    'phone'     => $phone,
                                ]
                            ]);
                            $response = $response->getBody()->getContents();

                            $rs['angel'] = $response;
                        } else {
                            $rs['send_angel_blocked'] = true;
                        }
                    }

                    if ($send_welcome) {
                        if (!in_array($hotel_id, $blocked_hotels_welcome)) {
                            $response = $client->request('POST', $url_app, [
                                'form_params' => [
                                    'hotel_id'  => $hotel_id,
                                    'guest_id'  => $guest_id,
                                    'staff_id'  => $staff_id,
                                    "type"      => 'welcome',
                                    'email'     => $email,
                                    'phone'     => $phone,
                                    'back'      => $back,
                                ]
                            ]);
                            $response = $response->getBody()->getContents();

                            $rs['welcome'] = $response;
                        } else {
                            $rs['send_welcome_blocked'] = true;
                        }
                    }

                    $rs['send_angel'] = $send_angel;
                    $rs['send_welcome'] = $send_welcome;
                    $this->messages_guest++;
                    // \Log::alert('se envió una vez');
                    return $rs;
                }
                return 'No record found ' . $this->hotel_id;
            }
            return 'Sql no generated';
        } catch (\Exception $e) {
            \Log::info('Error al enviar invitaciones:');
            \Log::info($e);
            return 'Error show laravel.log';
        }
    }

    public function sendMonitoringApp($data, $endpoint)
    {
        // \Log::info($data);
        // \Log::info($endpoint);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://127.0.0.1:8000/api/$endpoint/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
            ),
        ));


        $response = curl_exec($curl);
    }


    public function getAngelStatus()
    {
        $data = IntegrationsActive::where('hotel_id', $this->hotel_id)->first();
        if($data) return $data->sms_angel_active == 1 ? true : false;
        return false;
    }

    public function getSuites($room_number)
    {
        $suites = IntegrationSuitesRoom::where('hotel_id', $this->hotel_id)->where('is_active', 1)->where('suite_id', $room_number)->get();
        if ($suites) {
            $rooms = [];
            foreach ($suites as $suite) {
                $_room = HotelRoom::find($suite->room_id);
                $room = [
                    'location' => $_room->location,
                    'room_id' => $_room->room_id
                ];
                $rooms[] = $room;
            }
            return $rooms;
        }
        return null;
    }


    public function removeSuitesReservation($reservation_number)
    {
        // \DB::beginTransaction();


        $reservations = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('reservation_number', 'LIKE', "%" . $reservation_number . "_%")->get();
        $check_in = null;
        if (strpos($reservation_number, '_')) {
            return $check_in;
        }


        foreach ($reservations as $value) {
            if ($value->reservation_status == 1) {
                $check_in = $value->check_in;
                $value->status = 0;
                $value->reservation_status = 3;
                $this->configTimeZone($this->hotel_id);
                $value->check_out = date('Y-m-d H:i:s');
                date_default_timezone_set('UTC');

                $value->reservation_number = $value->reservation_number . "_REMOVE";


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
            $value->reservation_status = 3;
            $this->configTimeZone($this->hotel_id);
            $value->check_out = date('Y-m-d H:i:s');
            date_default_timezone_set('UTC');

            $value->reservation_number = $reservation_number . "_REMOVE";
            $value->save();
            // }
        }
        // \DB::commit();

        return $check_in;
    }

    public function isAnotherSuite($reservation_number, $suite)
    {
        $reservations = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('reservation_number', 'NOT LIKE', "%_REMOVE%")->where('reservation_number', 'LIKE', "%" . $reservation_number . "_%")->get();
        $rooms = [];
        foreach ($reservations as $value) {
            $rooms[] = $value->room_no;
        }
        $suite_data = IntegrationSuitesRoom::where('hotel_id', $this->hotel_id)->whereIn('room_id', $rooms)->where('suite_id', $suite)->count();

        return $suite_data !== count($rooms) ? true : false;
    }


    public function check_out_reserve()
    {
        $reservation = GuestCheckinDetails::where('hotel_id', $this->hotel_id)->where('status', 1)->where('reservation_status', 0)
            ->whereDate('check_out', '<=', date('Y-m-d'))->get();
        foreach ($reservation as $r) {
            $r->reservation_status =  3;
            $r->status = 0;
            $r->save();
            // \Log::info($r->sno);
        }
    }

    public function customWriteLog($folder_name, $hotel_id, $text)
    {

        $path = "/logs/$folder_name";

        if (!\Storage::has($path)) {
            \Storage::makeDirectory($path, 0775, true);
        }

        if (!\Storage::has($path . "/" . $hotel_id)) {
            \Storage::makeDirectory($path . "/" . $hotel_id, 0775, true);
        }

        $day = date('Y_m_d');
        $file = "$path/$hotel_id/$day.log";
        $hour = date('H:i:s');
        $text = "[$hour]: $text";

        \Storage::append($file, $text);

        return true;
    }
}
