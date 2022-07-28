<?php

namespace App\Console\Commands;

use App\Models\GuestCheckinDetails;
use App\Models\GuestRegistration;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\IntegrationsActive;
use App\Models\LogTracker;
use App\Models\NoShow;
use App\Models\RoomMove;
use DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Log;
use Storage;

class InsertAgilysys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agilysys:insert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert In house reservation and Future reservation to the Agilysys Integration';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        //echo "$this->description\n";
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //sleep(600);
        $IntegrationsActives = IntegrationsActive::where(function ($query) {
        $query->where('int_id', 11)->where('state', 1);
        })->get();
        foreach ($IntegrationsActives as $IntegrationsActiveKey => $_integrations_active) {
            $this->runProccess($_integrations_active, 'InHouseReservations', 1, 1);
            $this->runProccess($_integrations_active, 'FutureReservations', 0, 1);
        }
    }

    public function runProccess($ia, $file, $reservation_status, $count)
    {
        try {
            ini_set('memory_limit', '500M');

            $hotel_id = $ia->hotel_id;
            $staff_id = $ia->created_by;
            $__folder = $ia->config['folder'];
            $allFiles = Storage::files($__folder);
            $matchingFiles = preg_grep('/'.$ia->config['hotel_id'].'_'.$file.'/', $allFiles);
            $matchingFiles = array_slice($matchingFiles, 0, $count);

            $this->configTimeZone($hotel_id);

            if (count($matchingFiles) > 0) {
                foreach ($matchingFiles as $path) {
                    $data = Storage::get($path);
                    if (Storage::exists($path)) {
                        //$this->writeLog( 'agilysys', $hotel_id, "Move file");
                        Storage::move($path, str_replace('agilysys', $__folder.'_to_delete', $path));
                    }

                    $data = explode("\n", $data);
                    $column_name = explode(',', $data[0]);

                    $records = [];

                    //echo "File: $path";
                    $this->writeLog('agilysys', $hotel_id, "File: $path");

                    foreach ($data as $key => $value) {
                        $row = explode(',', $value);

                        if (count($row) == count($column_name) && $key > 0) {
                            $data_row = [];
                            foreach ($column_name as $key1 => $value1) {
                                $data_row[$value1] = $row[$key1];
                            }

                            $records[] = $data_row;
                        } else {
                            //Log::info("no tiene la misma cantidad");
                            //Log::info("column_name: ".json_encode($column_name));
                            //Log::info("Data: ".json_encode($row));

                            //$this->writeLog( 'agilysys', $hotel_id, "no tiene la misma cantidad");
                        }
                    }

                    $records = $this->ClearArray($records, $ia->config, $hotel_id);

                    //$this->writeLog( 'agilysys', $hotel_id, "Start runProccess...");
                    $rs = $this->store($hotel_id, $staff_id, $records, $reservation_status);
                    //$this->writeLog( 'agilysys', $hotel_id, "End runProccess...");
                }
            }
        } catch (\Exception $e) {
            Log::info("Error en runProccess: \n");
            Log::info($e);
            Log::info("\n\n");
        }
    }

    public function writeLog($folder_name, $hotel_id, $text)
    {
        if ($hotel_id) {
            $hotel_name = Hotel::find($hotel_id)->hotel_name;
            $hotel_name = str_replace([' '], '_', $hotel_name);
            $hotel_name = str_replace(['.'], '_', $hotel_name);
            $hotel_name = str_replace(['&'], '', $hotel_name);
            $hotel_name = strtolower($hotel_name);
            $path = "/logs/$folder_name/$hotel_id"."__$hotel_name";
        } else {
            $path = "/logs/$folder_name/error";
        }

        if (! Storage::has($path)) {
            Storage::makeDirectory($path, 0775, true);
        }
        $day = date('Y_m_d');
        $file = "$path/$day.log";
        $hour = date('H:i:s');
        $text = "[$hour]: $text";
        Storage::append($file, $text);
    }

    public function ClearArray($stays, $config, $hotel_id)
    {
        try {
            $stays_filtered = [];
            $key_array = [];

            foreach (array_reverse($stays) as $stayskey => $stay) {
                $__start = '';
                $__start_have_time = false;
                $__end_have_time = false;
                if (isset($stay['start']) && ! empty($stay['start'])) {
                    if (\DateTime::createFromFormat('m-d-y', $stay['start']) != false) {
                        $__start = \DateTime::createFromFormat('m-d-y', $stay['start'])->format('Y-m-d');
                    }
                } elseif (isset($stay['arrivalDate']) && ! empty($stay['arrivalDate'])) {
                    if (\DateTime::createFromFormat('m-d-y', $stay['arrivalDate']) != false) {
                        $__start = \DateTime::createFromFormat('m-d-y', $stay['arrivalDate'])->format('Y-m-d');
                    }
                }

                if (isset($stay['checkInTime']) && ! empty($stay['checkInTime'])) {
                    if (\DateTime::createFromFormat('m-d-y H:i:s', $stay['checkInTime']) != false) {
                        $__start_have_time = true;
                        $__start = \DateTime::createFromFormat('m-d-y H:i:s', $stay['checkInTime'])->format('Y-m-d H:i:s');
                    // g:i:s
                    } elseif (\DateTime::createFromFormat('m/d/Y g:i:s A', $stay['checkInTime']) != false) {
                        $__start_have_time = true;
                        $__start = \DateTime::createFromFormat('m/d/Y g:i:s A', $stay['checkInTime'])->format('Y-m-d H:i:s');
                    }
                }

                $__end = '';
                if (isset($stay['end']) && ! empty($stay['end'])) {
                    if (\DateTime::createFromFormat('m-d-y', $stay['end']) != false) {
                        $__end = \DateTime::createFromFormat('m-d-y', $stay['end'])->format('Y-m-d');
                    }
                } elseif (isset($stay['departureDate']) && ! empty($stay['departureDate'])) {
                    if (\DateTime::createFromFormat('m-d-y', $stay['departureDate']) != false) {
                        $__end = \DateTime::createFromFormat('m-d-y', $stay['departureDate'])->format('Y-m-d');
                    }
                }

                if (isset($stay['check_out_time_2']) && ! empty($stay['check_out_time_2'])) {
                    if (\DateTime::createFromFormat('m-d-y', $stay['check_out_time_2']) != false) {
                        $__end = \DateTime::createFromFormat('m-d-y', $stay['check_out_time_2'])->format('Y-m-d');
                    } elseif (\DateTime::createFromFormat('m/d/Y g:i:s A', $stay['check_out_time_2']) != false) {
                        $__end_have_time = true;
                        $__end = \DateTime::createFromFormat('m/d/Y g:i:s A', $stay['check_out_time_2'])->format('Y-m-d H:i:s');
                    }
                }

                $valid_dates_range = true;
                if (! empty(trim($__start)) && ! empty(trim($__end))) {
                    if (strtotime($__start) < strtotime($__end)) {
                        if (! $__start_have_time) {
                            $__start .= " $config[check_in_time]";
                        }
                        if (! $__end_have_time) {
                            $__end .= " $config[check_out_time]";
                        }
                    } elseif (strtotime($__start) == strtotime($__end)) {
                        if (! $__start_have_time) {
                            $__start .= " $config[check_in_time]";
                        }
                        if (! $__end_have_time) {
                            $__end .= " $config[check_out_time_2]";
                        }
                    } else {
                        $valid_dates_range = false;
                    }

                    if ($valid_dates_range) {
                        $__phone = '';
                        if (! empty(trim($stay['phone']))) {
                            $__phone = str_replace([' ', '-', '(', ')', '/', '.'], '', $stay['phone']);
                            if (is_numeric($__phone) && (int) $__phone > 0) {
                                $__phone = "+1$__phone";
                            }
                        }

                        $__stay = [
                            'address' => isset($stay['addres']) ? trim($stay['addres']) : '',
                            'email' => isset($stay['email']) ? trim($stay['email']) : '',
                            'start' => $__start,
                            'end' => $__end,
                            'firstname' => isset($stay['firstname']) ? trim($stay['firstname']) : (isset($stay['firstName']) ? trim($stay['firstName']) : ''),
                            'lastname' => isset($stay['lastname']) ? trim($stay['lastname']) : (isset($stay['lastName']) ? trim($stay['lastName']) : ''),
                            'phone' => $__phone,
                            'roomNumber' => isset($stay['roomNumber']) ? trim($stay['roomNumber']) : '',
                            'reservationNumber' => isset($stay['reservationNumber']) ? trim($stay['reservationNumber']) : '',
                            'status' => isset($stay['status']) ? strtoupper(trim($stay['status'])) : '',
                            'vip' => isset($stay['VIP']) ? trim($stay['VIP']) : '',
                        ];

                        if (
                            ! empty(trim($__stay['reservationNumber'])) &&
                            ! empty(trim($__stay['status'])) &&
                            (! empty(trim($__stay['firstname'])) || ! empty(trim($__stay['lastname'])) || ! empty(trim($__stay['email'])))
                        ) {
                            $__to_validate = "$__stay[address]-$__stay[email]-$__stay[start]-$__stay[end]-$__stay[firstname]-$__stay[lastname]-$__stay[phone]-$__stay[roomNumber]-$__stay[reservationNumber]-$__stay[status]-$__stay[vip]";

                            if (! in_array($__to_validate, $key_array)) {
                                $key_array[] = $__to_validate;
                                $stays_filtered[] = $__stay;
                            }
                        }
                    } else {
                        Log::info('Error:');
                        Log::info(json_encode($stay));
                    }
                } else {
                    Log::info('Rango invalido');
                }
            }

            //$this->writeLog( 'agilysys', $hotel_id, "Cantidad:");
            //$this->writeLog( 'agilysys', $hotel_id, count($stays_filtered));

            return array_reverse($stays_filtered);
        } catch (\Exception $e) {
            Log::info("Error en ClearArray: \n");
            Log::info($e);
            Log::info("\n\n");

            return  [];
        }
    }

    public function store($hotel_id, $staff_id, $stays)
    {
        $error = [];
        foreach ($stays as $stayKey => $stay) {
            DB::beginTransaction();
            try {
                $now = date('Y-m-d H:i:s');
                $GuestCheckinDetails = GuestCheckinDetails::where('hotel_id', $hotel_id)
                    ->where('reservation_number', trim($stay['reservationNumber']))
                    ->with(['Guest'])
                    ->first();

                if ($GuestCheckinDetails) {
                    $guest_id = $GuestCheckinDetails->guest_id;
                    $GuestRegistration = $GuestCheckinDetails->guest;

                    if ($GuestRegistration) {
                        $updated = false;
                        $data_to_update = [];
                        $message = '';

                        $GuestRegistration->category = empty($stay['vip']) ? 0 : 3;

                        $__firstname = isset($stay['firstname']) ? trim(strtoupper($stay['firstname'])) : '';
                        if (strtoupper(trim($GuestRegistration->firstname)) != $__firstname) {
                            $updated = true;
                            $message .= "firstname: '$GuestRegistration->firstname' to '$stay[firstname]', ";
                            $data_to_update['firstname'] = trim($stay['firstname']);
                            //$GuestRegistration->firstname = trim($stay["firstname"]);
                        }

                        $__lastname = isset($stay['lastname']) ? trim(strtoupper($stay['lastname'])) : '';
                        if (strtoupper(trim($GuestRegistration->lastname)) != $__lastname) {
                            $updated = true;
                            $message .= "lastname: '$GuestRegistration->lastname' to '$stay[lastname]', ";
                            $data_to_update['lastname'] = trim($stay['lastname']);
                            //$GuestRegistration->lastname = trim($stay["lastname"]);
                        }

                        $__email = isset($stay['email']) ? trim(strtoupper($stay['email'])) : '';
                        if ((strtoupper(trim($GuestRegistration->email_address)) != $__email) && filter_var($__email, FILTER_VALIDATE_EMAIL)) {
                            $updated = true;
                            $message .= "email_address: '$GuestRegistration->email_address' to '$stay[email]', ";
                            $data_to_update['email_address'] = trim($stay['email']);
                        }

                        $__phone = isset($stay['phone']) ? trim($stay['phone']) : '';
                        if ((trim($GuestRegistration->phone_no) != $__phone) && preg_match("/^\+[0-9]{1,4}[0-9]{6,15}$/", $__phone)) {
                            $updated = true;
                            $message .= "phone_no: '$GuestRegistration->phone_no' to '$__phone', ";
                            $data_to_update['phone_no'] = $__phone;
                        }

                        $__address = isset($stay['address']) ? strtoupper(trim($stay['address'])) : '';
                        if (strtoupper(trim($GuestRegistration->address)) != $__address) {
                            $updated = true;
                            $message .= "address: '$GuestRegistration->address' to '$stay[address]', ";
                            $data_to_update['address'] = $stay['address'];
                            //$GuestRegistration->address = $stay["address"];
                        }

                        if ($updated) {
                            $data_to_update['updated_by'] = $staff_id;
                            $data_to_update['updated_on'] = $now;

                            // 0.019 segundos
                            $GuestRegistration->update($data_to_update);

                            $this->saveLogTracker([
                                'module_id' => 8,
                                'action' => 'update',
                                'prim_id' => $GuestRegistration->guest_id,
                                'staff_id' => $staff_id,
                                'date_time' => $now,
                                'comments' => $message,
                                'hotel_id' => $hotel_id,
                                'type' => 'API-agilysys',
                            ]);
                        }
                    }

                    $__status_code = 0;

                    switch ($stay['status']) {
                        case 'RES':
                            $__status_code = 0;
                            break;
                        case 'INH':
                            $__status_code = 1;
                            break;
                        case 'CLX':
                        case 'MOV':
                            $__status_code = 2;
                            break;
                        case 'DPT':
                            $__status_code = 3;
                            break;
                        case 'NOS':
                            $__status_code = 4;
                            break;
                    }

                    $updated = false;
                    $data_to_update = [];
                    $message = '';

                    $room_id = 0;
                    if (! empty($stay['roomNumber'])) {
                        $room_id = (int) $this->findRoomId($hotel_id, $staff_id, trim($stay['roomNumber']));
                    }

                    if ((int) $GuestCheckinDetails->room_no != $room_id && $room_id > 0) {
                        $updated = true;
                        $current_room_no = $GuestCheckinDetails->room_no;
                        $new_room_no = $room_id;
                        $message = "room_no: '$current_room_no' to '$new_room_no', ";
                        $data_to_update['room_no'] = $room_id;
                        $data_to_update['status'] = 1;

                        if ((int) $GuestCheckinDetails->status == 1) {
                            RoomMove::create([
                                'guest_id' => $guest_id,
                                'phone' => '',
                                'current_room_no' => $current_room_no,
                                'new_room_no' => $new_room_no,
                                'comment' => '',
                                'hotel_id' => $hotel_id,
                                'created_by' => $staff_id,
                                'created_on' => date('Y-m-d H:i:s'),
                                'updated_by' => 0,
                                'updated_on' => null,
                            ]);
                        }
                    }

                    if ($GuestCheckinDetails->check_in != $stay['start']) {
                        $updated = true;
                        $message = "check_in: '$GuestCheckinDetails->check_in' to '$stay[start]', ";
                        $data_to_update['check_in'] = $stay['start'];
                    }

                    if ($GuestCheckinDetails->check_out != $stay['end']) {
                        $updated = true;
                        $message = "check_out: '$GuestCheckinDetails->check_out' to '$stay[end]', ";
                        $data_to_update['check_out'] = $stay['end'];
                    }

                    if ($GuestCheckinDetails->reservation_status != $__status_code) {
                        $updated = true;
                        $message = "reservation_status: '$GuestCheckinDetails->reservation_status' to '$__status_code', ";
                        $data_to_update['reservation_status'] = $__status_code;

                        if ($__status_code == 4) {
                            $data_to_update['status'] = 0;
                            if (strtotime($GuestCheckinDetails->check_out) > strtotime($now)) {
                                $data_to_update['check_out'] = $now;
                            }
                            NoShow::create([
                                'guest_id' => $guest_id,
                                'phone' => $GuestRegistration->phone_no,
                                'created_by' => $staff_id,
                                'created_on' => $now,
                                'hotel_id' => $hotel_id,
                                'updated_by' => 0,
                                'status' => 1,
                            ]);
                        }

                        if ($__status_code == 3) {
                            $data_to_update['status'] = 0;
                            if (strtotime($GuestCheckinDetails->check_out) > strtotime($now)) {
                                $data_to_update['check_out'] = $now;
                            }
                        }
                    }

                    if ((int) $room_id == 0) {
                        $updated = true;
                        $data_to_update['status'] = -1;
                    }

                    if (strtotime($GuestCheckinDetails->check_out) < strtotime($now)) {
                        $updated = true;
                        $data_to_update['status'] = 0;
                        $data_to_update['reservation_status'] = $GuestCheckinDetails->reservation_status == 0 ? 2 : 3;
                    }

                    if ($updated) {
                        $GuestCheckinDetails->update($data_to_update);
                        $this->saveLogTracker([
                            'module_id' => 8,
                            'action' => 'update',
                            'prim_id' => $GuestCheckinDetails->sno,
                            'staff_id' => $staff_id,
                            'date_time' => $now,
                            'comments' => $message,
                            'hotel_id' => $hotel_id,
                            'type' => 'API-agilysys',
                        ]);
                    }

                    DB::commit();
                } else {
                    //Log::info("--->Crea:*$stay[reservationNumber]*");
                    $guest_registration = [
                        'hotel_id' => $hotel_id,
                        'firstname' => trim($stay['firstname']),
                        'lastname' => trim($stay['lastname']),
                        'email_address' => trim($stay['email']),
                        'phone_no' => trim($stay['phone']),
                        'address' => trim($stay['address']),
                        'state' => '',
                        'zipcode' => '',
                        'language' => 'en',
                        'comment' => '',
                        'angel_status' => 0, //$this->validateAngelStatus($hotel_id),
                        'city' => '',
                        'created_on' => $now,
                        'created_by' => $staff_id,
                        'category' => (empty($stay['vip']) ? 0 : 3),
                    ];

                    $email_phone_valid = true;

                    if (
                        ! empty(trim($guest_registration['email_address'])) ||
                        ! empty(trim($guest_registration['phone_no']))
                    ) {
                        $__GuestRegistration = GuestRegistration::where('is_active', 1);

                        if (! empty(trim($guest_registration['email_address']))) {
                            $__GuestRegistration = $__GuestRegistration->where('email_address', trim($guest_registration['email_address']));
                        }

                        if (! empty(trim($guest_registration['phone_no']))) {
                            $__GuestRegistration = $__GuestRegistration->orWhere('phone_no', trim($guest_registration['phone_no']));
                        }

                        $__GuestRegistration = $__GuestRegistration->first();

                        if ($__GuestRegistration) {
                            if (
                                ! empty(trim($guest_registration['email_address'])) &&
                                trim($__GuestRegistration->email_address) == trim($guest_registration['email_address'])
                            ) {
                                $guest_registration['email_address'] = '';
                                $guest_registration['comment'] .= "Email address already exists: $guest_registration[email_address]";
                            }

                            if (
                                ! empty(trim($guest_registration['phone_no'])) &&
                                trim($__GuestRegistration->email_address) == trim($guest_registration['phone_no'])
                            ) {
                                $guest_registration['phone_no'] = '';
                                $guest_registration['comment'] .= "Phone number already exists: $guest_registration[phone_no]";
                            }
                        }

                        // ->where('email_address',$guest_registration["email_address"])
                        // ->get();

                        // if(count($find) > 0) {
                        //     //Log::info("find email: ");
                        //     //Log::info(json_encode($find));
                        //     $email_valid = false;
                        //}
                    }

                    //if( $email_phone_valid ) {

                    $rs = GuestRegistration::where('hotel_id', $hotel_id)
                        ->where('firstname', $guest_registration['firstname'])
                        ->where('lastname', $guest_registration['lastname'])
                        ->first();

                    if ($rs) {
                        $guest_id = $rs->guest_id;
                    } else {
                        $guest_id = GuestRegistration::create($guest_registration)->guest_id;
                    }

                    $room_id = 0;
                    if (! empty($stay['roomNumber'])) {
                        $room_id = (int) $this->findRoomId($hotel_id, $staff_id, $stay['roomNumber']);
                    }

                    $__status_code = 0;

                    switch ($stay['status']) {
                            case 'RES':
                                $__status_code = 0;
                                break;
                            case 'INH':
                                $__status_code = 1;
                                break;
                            case 'CLX':
                            case 'MOV':
                                $__status_code = 2;
                                break;
                            case 'DPT':
                                $__status_code = 3;
                                break;
                            case 'NOS':
                                $__status_code = 4;
                                break;
                        }

                    $guest_checkin_cetails = [
                        'guest_id' => $guest_id,
                        'hotel_id' => $hotel_id,
                        'room_no' => $room_id,
                        'check_in' => $stay['start'],
                        'check_out' => $stay['end'],
                        'status' => (int) $room_id == 0 ? -1 : 1,
                        'reservation_status' => $__status_code,
                        'reservation_number' => $stay['reservationNumber'],
                        'comment' => '',
                    ];

                    if (($__status_code == 2 || $__status_code == 3)) {
                        $guest_checkin_cetails['status'] = (int) $room_id == 0 ? -1 : 0;

                        if (strtotime($guest_checkin_cetails['check_out']) > strtotime($now)) {
                            $guest_checkin_cetails['check_out'] = $now;
                        }
                    }

                    if (strtotime($guest_checkin_cetails['check_out']) < strtotime($now)) {
                        $guest_checkin_cetails['status'] = (int) $room_id == 0 ? -1 : 1;
                    }

                    $sno = GuestCheckinDetails::create($guest_checkin_cetails)->sno;

                    if ($__status_code == 4) {
                        NoShow::create([
                            'guest_id' => $guest_id,
                            'phone' => $guest_registration['phone_no'],
                            'created_by' => $staff_id,
                            'created_on' => $now,
                            'hotel_id' => $hotel_id,
                            'updated_by' => 0,
                            'status' => 1,
                        ]);
                    }

                    $this->saveLogTracker([
                        'module_id' => 8,
                        'action' => 'add',
                        'prim_id' => $sno,
                        'staff_id' => $staff_id,
                        'date_time' => $now,
                        'comments' => '',
                        'hotel_id' => $hotel_id,
                        'type' => 'API-agilysys',
                    ]);

                    DB::commit();
                    //$executionEndTime = microtime(true);
                        //$seconds = $executionEndTime - $executionStartTime;
                        //$this->writeLog( 'agilysys', $hotel_id, "... Create $stayKey: $seconds");
                    //}
                    // else {
                    //     DB::rollback();
                    //     $__err = [
                    //         "error" => "error Email or email",
                    //         "stay" => "reservationNumber: $stay[reservationNumber] - index: $stayKey"
                    //         //"stay" => "reservationNumber: ".json_encode($stay)
                    //     ];
                    //     $error[] = $__err;
                    // }
                }
            } catch (\Exception $e) {
                Log::info("Error en store: \n");
                Log::info($e);
                Log::info("\n\n");

                DB::rollback();

                //$this->writeLog( 'agilysys', $hotel_id, "error: stay: $stay[reservationNumber]");
                $__err = [
                    'error' => 'Exception',
                    //"stay"  => "reservationNumber:".json_encode($stay)
                    'stay' => "reservationNumber: $stay[reservationNumber] - index: $stayKey",
                ];
                $error[] = $__err;
            }
        }

        return [
            'create' => (count($error) == count($stays)) ? false : true,
            'error' => $error,
        ];
    }

    public function findRoomId($hotel_id, $staff_id, $location)
    {
        $room = HotelRoom::where('hotel_id', $hotel_id)
        ->where('location', $location)
        ->first();

        if ($room) {
            if ((int) $room->active == 0) {
                $room->update([
                    'active' => 1,
                ]);
            }

            return $room->room_id;
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
                'comments' => '',
                'type' => 'API-agilysys',
            ]);

            return $room->room_id;
        }
    }

    public function saveLogTracker($__log_tracker)
    {
        $track_id = LogTracker::create($__log_tracker)->track_id;

        return $track_id;
    }

    public function configTimeZone($hotel_id)
    {
        $timezone = Hotel::find($hotel_id)->time_zone;
        date_default_timezone_set($timezone);
    }

    public function validateAngelStatus($hotel_id)
    {
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
}
