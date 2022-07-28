<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\GuestCheckinDetails;
use App\Models\GuestRegistration;
use App\Models\Hotel;
use App\Models\IntegrationsActive;
use DB;
use Illuminate\Filesystem\Filesystem as File;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Validator;

class OperaController extends Controller
{
    private $file_log;

    private $path;

    public function index(Request $request)
    {
        $data = json_decode($request->data, true);

        $Integrations_active =
            IntegrationsActive::where('int_id', 5)
            ->where('state', 1)
            ->get();

        //$this->writeLog(null, 'Run Cron');

        $guest_registration = [];
        $guest_checkin_details = [];

        try {
            foreach ($data as $d) {
                $company = $Integrations_active->where('config.Resort', $d['ResortID'])->first();
                if ($company) {
                    $hotel_id = $company->hotel_id;
                    $id = $company->id;
                    $staff_id = $company['config']['staff_id'];

                    $guest_registration[] = [
                        'staff_id' => $staff_id,
                        'hotel_id' => $hotel_id,
                        'firstname' => $d['FirstName'],
                        'lastname' => $d['LastName'],
                        'email_address' => $d['EmailAddress'],
                        'phone_no' => $d['PhoneNumber'],
                        'address' => $d['Address'],
                        'zipcode' => $d['zipcode'],
                        'dob' => $d['dob'],
                        'angel_status' => 1,
                    ];

                    $guest_checkin_details[] = [
                        'hotel_id' => $hotel_id,
                        'room' => 'Reserved '.date('Y-m-d H:i:s'),
                        'check_in' => $d['ArrivaleDate'],
                        'check_out' => $d['DepartureDate'],
                        'comment' => '',
                        'main_guest' => 0,
                    ];
                }
            }
            $this->storeGuest($guest_registration, $guest_checkin_details);
        } catch (\Exception $e) {
            //$this->writeLog(null, $e);
            echo $e;
        }
    }

    private function storeGuest($__guest_registration, $__guest_checkin_details)
    {
        try {
            $success = [];
            $error = [];
            $guest = $__guest_registration;
            $checkin = $__guest_checkin_details;
            $create_no = 0;

            foreach ($guest as $key => $value) {
                $_guest = $value;
                $_checkin = $checkin[$key];
                $hotel_id = $_guest['hotel_id'];
                $staff_id = $_guest['staff_id'];

                if ($this->validateHotelId($hotel_id, $staff_id)) {
                    $this->configTimeZone($hotel_id);
                    $now = date('Y-m-d H:i:s');

                    $validation = Validator::make($_guest, [
                        'hotel_id' => 'required|numeric|exists:hotels',
                        'firstname' => 'required|string',
                        'lastname' => 'required|string',
                        'email_address' => [
                            'string',
                            'required_without:phone_no',
                            'required_if:phone_no,',
                            'nullable',
                            'regex:/([-0-9a-zA-Z.+_]+@[-0-9a-zA-Z.+_]+.[a-zA-Z]{2,4}|)/',
                            Rule::unique('guest_registration')->where(function ($query) use ($hotel_id) {
                                return $query->where('is_active', 1)->where('hotel_id', '!=', $hotel_id);
                            }),
                        ],
                        'phone_no' => [
                            'string',
                            'required_without:email_address',
                            'required_if:email_address,',
                            'regex:/(\+[0-9]{1,4}[0-9]{6,10}|)/',
                            'nullable',
                            Rule::unique('guest_registration')->where(function ($query) use ($hotel_id) {
                                return $query->where('is_active', 1)->where('hotel_id', '!=', $hotel_id);
                            }),
                        ],
                        'angel_status' => 'numeric|required|in:0,1',
                        'language' => 'string|in:en,es',
                    ]);

                    //$this->writeLog($hotel_id, 'Primera validacion del guest_requistration');
                    if ($validation->fails()) {
                        $error[] = [
                            'error' => $validation->errors(),
                            'guest_registration' => $_guest,
                            'guest_checkin_details' => $_checkin,
                        ];
                    } else {
                        $guest_registration = [
                            'hotel_id' => $hotel_id,
                            'firstname' => is_string($_guest['firstname']) ? $_guest['firstname'] : '',
                            'lastname' => is_string($_guest['lastname']) ? $_guest['lastname'] : '',
                            'email_address' => array_key_exists('email_address', $_guest) ? $_guest['email_address'] | '' : '',
                            'phone_no' => array_key_exists('phone_no', $_guest) ? $_guest['phone_no'] | '' : '',
                            'angel_status' => array_key_exists('angel_status', $_guest) ? $_guest['angel_status'] : '',
                            'language' => array_key_exists('language', $_guest) ? $_guest['language'] : '',
                            'created_on' => date('Y-m-d H:i:s'),
                            'created_by' => $staff_id,
                            'address' => '',
                            'state' => '',
                            'zipcode' => '',
                            'comment' => '',
                            'city' => '',
                        ];

                        //$this->writeLog($hotel_id, 'Buscar registro que coincidan con el email y phone, activos en otro hotel');

                        $find_guest = GuestRegistration::where(function ($query) use ($guest_registration) {
                            if (! empty($guest_registration['email_address']) && ! empty($guest_registration['phone_no'])) {
                                return $query
                                ->where('email_address', $guest_registration['email_address'])
                                ->orWhere('phone_no', $guest_registration['phone_no']);
                            } elseif (! empty($guest_registration['email_address']) && empty($guest_registration['phone_no'])) {
                                return $query
                                ->where('email_address', $guest_registration['email_address']);
                            } else {
                                return $query
                                ->where('phone_no', $guest_registration['phone_no']);
                            }
                        })
                        ->where('is_active', 1)
                        ->where('hotel_id', '!=', $hotel_id)
                        ->first();

                        if ($find_guest && $find_guest->is_active == 1) {

                            //$this->writeLog($hotel_id, 'guest encontrado es un error');
                            $e = [
                                'error' => [
                                    'email_address' => 'The email_address is already registered in the system',
                                    'phone_no' => 'The phone_no is already registered in the system',
                                ],
                                'guest_registration' => $_guest,
                                'guest_checkin_details' => $_checkin,
                            ];
                            $error[] = $e;

                        //$this->writeLog($hotel_id, 'error: '.json_encode($e));
                        } else {
                            //$this->writeLog($hotel_id, 'Buscar registro que coincidan con el email y phone, activos en EL HOTEL');

                            $find_guest = GuestRegistration::where(function ($query) use ($guest_registration) {
                                if (! empty($guest_registration['email_address']) && ! empty($guest_registration['phone_no'])) {
                                    return $query
                                    ->where('email_address', $guest_registration['email_address'])
                                    ->orWhere('phone_no', $guest_registration['phone_no']);
                                } elseif (! empty($guest_registration['email_address']) && empty($guest_registration['phone_no'])) {
                                    return $query
                                    ->where('email_address', $guest_registration['email_address']);
                                } else {
                                    return $query
                                    ->where('phone_no', $guest_registration['phone_no']);
                                }
                            })
                            ->where('is_active', 1)
                            ->where('hotel_id', '=', $hotel_id)
                            ->first();

                            DB::beginTransaction();

                            if ($find_guest) {

                                //$this->writeLog($hotel_id, 'Guest encontrado y actualizado');

                                $guest_id = $find_guest->guest_id;
                                $find_guest->fill($guest_registration);
                                $find_guest->save();
                            } else {
                                $guest_id = GuestRegistration::create($guest_registration)->guest_id;
                                //$this->writeLog($hotel_id, 'Guest guardado, creado nuevo');
                            }

                            //$this->writeLog($hotel_id, 'Guest_id encontrado::'. $guest_id);

                            $validation = Validator::make($_checkin, [
                                'room_no' => 'required_without:room',
                                'room' => 'required_without:room_no',
                                'check_in' => 'required|date_format:"Y-m-d H:i:s"',
                                'check_out' => 'required|date_format:"Y-m-d H:i:s"|after:'.$_checkin['check_in'],
                                'comment' => 'string',
                            ]);

                            //$this->writeLog($hotel_id, 'Validar para el dato delcheck inl');

                            if ($validation->fails()) {

                                //$this->writeLog($hotel_id,  $validation->errors());
                                $e = [
                                    'error' => $validation->errors(),
                                    'guest_registration' => $_guest,
                                    'guest_checkin_details' => $_checkin,
                                ];
                                $error[] = $e;

                            //$this->writeLog($hotel_id, 'Error: '.json_encode($e));
                            } else {

                                //$this->writeLog($hotel_id, 'Pasa la segunda validaci��n');

                                $room = $this->getRoom($hotel_id, $staff_id, $_checkin['room']);

                                $room_id = $room['room_id'];
                                $location = $room['room'];

                                $guest_checkin_details = [
                                    'guest_id' => $guest_id,
                                    'hotel_id' => $hotel_id,
                                    'room_no' => $room_id,
                                    'comment' => array_key_exists('comment', $_checkin) ? $_checkin['comment'] : '',
                                    'check_in' => $_checkin['check_in'],
                                    'check_out' => $_checkin['check_out'],
                                    'status' => 1,
                                ];

                                $now = date('Y-m-d H:i:s');

                                $find_guest_checkin_details = GuestCheckinDetails::where(function ($query) use ($room_id, $hotel_id, $guest_checkin_details) {
                                    return
                                        $query->where('hotel_id', $hotel_id)
                                            ->where('status', 1)
                                            ->where('room_no', $room_id)
                                            ->where('check_in', '<=', $guest_checkin_details['check_in'])
                                            ->where('check_out', '>=', $guest_checkin_details['check_in']);
                                })->get();

                                //$this->writeLog($hotel_id, 'Check in encontrados: '.json_encode($find_guest_checkin_details));

                                if (count($find_guest_checkin_details) > 0) {
                                    DB::rollback();
                                    $e = [
                                        'error' => [
                                            'check_in' => [
                                                "Room $location is in use in this date range",
                                            ],
                                        ],
                                        'guest_registration' => $_guest,
                                        'guest_checkin_details' => $_checkin,
                                    ];
                                    $error[] = $e;

                                //$this->writeLog($hotel_id, 'Error: '.json_encode($e));
                                } else {
                                    //$this->writeLog($hotel_id, 'Pasa segunda validaci��nes');
                                    $guest_checkin_details = GuestCheckinDetails::create($guest_checkin_details);
                                    DB::commit();
                                    $create_no++;

                                    $this->saveLogTracker([
                                        'module_id' => 8,
                                        'action' => 'add',
                                        'prim_id' => $guest_id,
                                        'staff_id' => $staff_id,
                                        'date_time' => $now,
                                        'comments' => '',
                                        'hotel_id' => $hotel_id,
                                        'type' => 'API-OPERA',
                                    ]);

                                    $success[] = [
                                        'guest_registration' => $_guest,
                                        'guest_checkin_details' => $_checkin,
                                    ];
                                }
                            }
                        }
                    }
                } else {
                    $error[] = [
                        'create' => false,
                        'message' => 'the hotel_id does not belong to the current user',
                        'success' => $success,
                        'error' => $error,
                    ];
                }
            }

            /*$this->writeLog(null, 'result: '.json_encode([
                'create'    => $create_no > 0 ? true : false,
                'message'   => '',
                'success'   => $success,
                'error'     => $error
            ]));*/
        } catch (\Exception $e) {
            //$this->writeLog(null, $e);
            echo $e;
        }
    }

    public function writeLog($hotel_id, $text)
    {
        if ($hotel_id > 0) {
            $this->configTimeZone($hotel_id);
        } else {
            $hotel_id = '';
        }

        $day = date('Y_m_d');
        $this->path = public_path()."/logs/$hotel_id-opera-$day.log";
        if (! ($this->file_log)) {
            $this->file_log = new File();
        }
        if (! file_exists($this->path)) {
            $this->file_log->put($this->path, '');
        }
        $hour = date('H:i:s');
        $text = "[$hour]: $text \n";
        $this->file_log->append($this->path, $text);
    }
}
