<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\AlexaDevice;
use \App\Models\HotelRoom;
use \App\Models\GuestCheckinDetails;
use \App\Models\GuestRegistration;
use \App\Models\WakeUpCall;
use \App\Models\DeptTag;
use \App\Models\Event;
use App\Models\HousekeepingCleanings;
use App\Models\HousekeepingStaff;
use App\Models\OauthClient;
use App\Models\OauthCode;
use App\Models\StaffHotel;
use App\Models\Tag;
use App\User;
use DateTime;
use DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;

class AlexaController extends Controller
{
    /**
     * 
     */
    public $room_id         = 0;
    public $hotel_id        = 0;
    public $staff_id        = 0;
    public $dept_tag_id     = 0;
    public $intent          = '';
    public $data            = [];
    public $now;
    /**
     * 
     */
    public function alexa_validate(Request $request)
    {
        $location   = $request->room;
        $intent     = $request->intent;

        if (strpos($location, 'partner') !== false) {
            $array = explode("_", $location);
            $staff_id = $array[2];
            $location = $array[3];
        } else {
            $staff_id   = $request->user()->staff_id;
        }

        $info = DB::table('alexa_intents_active_in_hotels AS ai')
            ->select([
                'ai.is_active',
                'dt.dept_tag_id',
                'ai.time',
                'h.hotel_id',
                'h.time_zone as time_zone_text',
                'hr.room_id',
            ])->join('alexa_intents AS a', function ($join) {
                $join->on('a.id', '=', 'ai.alexa_intents_id');
            })->join('hotels AS h', function ($join) {
                $join->on('h.hotel_id', '=', 'ai.hotel_id');
            })->join('staff_hotels AS sh', function ($join) {
                $join->on('sh.hotel_id', '=', 'ai.hotel_id');
            })->join('hotel_rooms AS hr', function ($join) {
                $join->on('hr.hotel_id', '=', 'ai.hotel_id');
            })->leftJoin('dept_tag AS dt', function ($join) {
                $join->on('dt.dept_id', '=', 'ai.dept_id');
                $join->on('dt.tag_id', '=', 'ai.tag_id');
            })
            ->where('a.intent_name', '=', $intent)
            ->where('hr.location', '=', $location)
            ->where('sh.staff_id', '=', $staff_id)
            ->first();

        $sql = DB::table('alexa_intents_active_in_hotels AS ai')
            ->select([
                'ai.is_active',
                'dt.dept_tag_id',
                'ai.time',
                'h.hotel_id',
                'h.time_zone as time_zone_text',
                'hr.room_id',
            ])->join('alexa_intents AS a', function ($join) {
                $join->on('a.id', '=', 'ai.alexa_intents_id');
            })->join('hotels AS h', function ($join) {
                $join->on('h.hotel_id', '=', 'ai.hotel_id');
            })->join('staff_hotels AS sh', function ($join) {
                $join->on('sh.hotel_id', '=', 'ai.hotel_id');
            })->join('hotel_rooms AS hr', function ($join) {
                $join->on('hr.hotel_id', '=', 'ai.hotel_id');
            })->leftJoin('dept_tag AS dt', function ($join) {
                $join->on('dt.dept_id', '=', 'ai.dept_id');
                $join->on('dt.tag_id', '=', 'ai.tag_id');
            })
            ->where('a.intent_name', '=', $intent)
            ->where('hr.location', '=', $location)
            ->where('sh.staff_id', '=', $staff_id)->toSql();

        if ($info) {
            if ($info->is_active == 1) {
                $info->state = true;
                $timezone = $info->time_zone_text;
                date_default_timezone_set($timezone);
                $info->time_zone = (date("Z") / 3600);
                return response()->json($info, 200);
            }
        }

        return response()->json([
            "state" => false,
            "asd" => $sql
        ], 200);
    }
    /**
     * 
     */
    public function index(Request $request)
    {

        if (strpos($request->room, 'partner') !== false) {
            $array = explode("_", $request->room);
            $staff_id = $array[2];
        } else {
            $this->staff_id = $request->user()->staff_id;
        }

        $this->intent   = $request->intent;

        if ($this->intent == "AmazonAlexaDefaultIntent") {
            $location   = $request->room;
            $info = DB::table('alexa_intents_active_in_hotels AS ai')
                ->select([
                    'ai.is_active',
                    'dt.dept_tag_id',
                    'ai.time',
                    'h.hotel_id',
                    'h.time_zone as time_zone_text',
                    'hr.room_id',
                ])->join('alexa_intents AS a', function ($join) {
                    $join->on('a.id', '=', 'ai.alexa_intents_id');
                })->join('hotels AS h', function ($join) {
                    $join->on('h.hotel_id', '=', 'ai.hotel_id');
                })->join('staff_hotels AS sh', function ($join) {
                    $join->on('sh.hotel_id', '=', 'ai.hotel_id');
                })->join('hotel_rooms AS hr', function ($join) {
                    $join->on('hr.hotel_id', '=', 'ai.hotel_id');
                })->leftJoin('dept_tag AS dt', function ($join) {
                    $join->on('dt.dept_id', '=', 'ai.dept_id');
                    $join->on('dt.tag_id', '=', 'ai.tag_id');
                })
                ->where('a.intent_name', '=', $intent)
                ->where('hr.location', '=', $location)
                ->where('sh.staff_id', '=', $staff_id)
                ->first();

            if ($info) {
                if ($info->is_active == 1) {
                    $info->state = true;
                    $timezone = $info->time_zone_text;
                    date_default_timezone_set($timezone);
                    $info->time_zone = (date("Z") / 3600);

                    $this->hotel_id     = $info->hotel_id;
                    $this->room_id      = $info->room_id;
                    $this->dept_tag_id  = $info->dept_tag_id;
                    $timezone           = $info->time_zone_text;

                    date_default_timezone_set($timezone);

                    $rs = $this->router([]);
                    return response()->json($rs, 200);
                }
            }
        } else {

            $this->hotel_id         = $request->hotel_id;
            $this->room_id          = $request->room_id;
            $this->dept_tag_id      = $request->dept_tag_id;
            $timezone               = $request->time_zone_text;
            $time                   = $request->time;
            $data                   = $request->data;

            date_default_timezone_set($timezone);
            $vcr = $this->validateCalendarRestrictions($time);
            if ($vcr['have_range']) {
                $timeNow = date('H:i');
                if ($timeNow < $vcr['start_time'] || $timeNow > $vcr['end_time']) {
                    return response()->json([
                        "result"    => false,
                        "message"   => "The requested service is not available"
                    ], 400);
                }
            }
            $rs = $this->router($data);
            return response()->json($rs, 200);
        }
    }
    /**
     * 
     */
    private function router($data)
    {
        if (method_exists($this, $this->intent)) {
            $method = $this->intent;
            return $this->$method($data);
        }
    }
    /**
     * 
     */
    public function checkoutTime(Request $request)
    {
        $room_id  = $request->user()->last_hotel;
        $guest_checkin_details = DB::table('guest_checkin_details as gsd')
            ->select('gsd.check_out', 'h.time_zone')
            ->join('hotels as h', 'h.hotel_id', '=', 'gsd.hotel_id')
            ->where('room_no', $room_id)
            ->orderBy('sno', 'desc')
            ->first();

        $timezone = $guest_checkin_details->time_zone;
        date_default_timezone_set($timezone);

        return response()->json([
            "check_out"     => $guest_checkin_details->check_out,
            "time_zone" => (date("Z") / 3600)
        ], 200);
    }

    /**
     * 
     */
    public function room_service(Request $request)
    {

        $this->room_id  = $request->user()->last_hotel;
        $items   = $request->data;
        $rs = [];

        foreach ($items as $key => $data) {
            $this->intent = $key;

            $info = DB::table('hotel_rooms as hr')
                ->select('hr.hotel_id', 'ai.tag_id', 'ai.is_active', 'ai.time', 'h.account', 'h.time_zone')
                ->join('alexa_intents as a', 'a.intent_name', '=', 'a.intent_name')
                ->join('alexa_intents_active_in_hotels as ai', 'ai.alexa_intents_id', '=', 'a.id')
                ->join('hotels as h', 'h.hotel_id', '=', 'hr.hotel_id')
                ->where('hr.room_id', $this->room_id)
                ->where('a.intent_name', $this->intent)
                ->first();

            if (isset($info->tag_id)) {
                $this->hotel_id = $info->hotel_id;
                $this->staff_id = $info->account;
                $this->tag_id   = $info->tag_id;
                $timezone       = $info->time_zone;

                date_default_timezone_set($timezone);

                if ($info->is_active == 1) {
                    if (!empty($info->time)) {
                        $vcr = $this->validateCalendarRestrictions($info->time);
                        if ($vcr['have_range']) {
                            $timeNow = date('H:i');
                            if ($timeNow >= $vcr['start_time'] &&  $timeNow <= $vcr['end_time']) {
                                $rs[$this->intent] = $this->router($data);
                            } else {
                                $rs[$this->intent] = [
                                    "result"    => false,
                                    "message"   => "The requested service is not available"
                                ];
                            }
                        } else {
                            $rs[$this->intent] = $this->router($data);
                        }
                    }
                }
            }
        }
        return response()->json($rs, 200);
    }

    /**
     * Función para validar restricciones de calendario
     */
    private function validateCalendarRestrictions($time)
    {
        $have_range = false;
        $start_time = null;
        $end_time   = null;
        $day        = [];
        $datetime   = date('Y-m-d');
        $day_num    = date('w', strtotime($datetime));
        $time       = json_decode($time, true);

        switch ($day_num) {
            case '1':
                if (!empty($time["mo"])) {
                    $range = true;
                    $day = $time["mo"];
                }
                break;
            case '2':
                if (!empty($time["tu"])) {
                    $have_range = true;
                    $day = $time["tu"];
                }
                break;
            case '3':
                if (!empty($time["we"])) {
                    $have_range = true;
                    $day = $time["we"];
                }
                break;
            case '4':
                if (!empty($time["th"])) {
                    $have_range = true;
                    $day = $time["th"];
                }
                break;
            case '5':
                if (!empty($time["fr"])) {
                    $have_range = true;
                    $day = $time["fr"];
                }
                break;
            case '6':
                if (!empty($time["sa"])) {
                    $have_range = true;
                    $day = $time["sa"];
                }
                break;
            case '7':
                if (!empty($time["su"])) {
                    $have_range = true;
                    $day = $time["su"];
                }
                break;
        }

        if ($have_range) {
            $start_time = date('H:i', strtotime($day["start_time"]));
            $end_time = date('H:i', strtotime($day["end_time"]));
        }
        return [
            "have_range"    => $have_range,
            "start_time"    => $start_time,
            "end_time"      => $end_time
        ];
    }

    /**
     * 
     */
    private function WakeUpCallIntent($data)
    {
        $guest_checkin_details = GuestCheckinDetails::where('hotel_id', $this->hotel_id)
            ->where('room_no', $this->room_id)
            ->where('status', 1)
            ->orderBy('sno', 'DESC')
            ->first();

        if ($guest_checkin_details) {
            $guest_registration = GuestRegistration::find($guest_checkin_details->guest_id);
            if ($guest_registration) {

                $timeNow = date('Y-m-d H:i');

                if (date($data["wtime"]) >= $timeNow) {
                    $wake_up_calls = [
                        "guest_id"      => $guest_registration->guest_id,
                        "phone"         => $guest_registration->phone_no,
                        "room_no"       => HotelRoom::find($this->room_id)->location,
                        "wtime"         => date('Y-m-d H:i:00', strtotime($data["wtime"])),
                        "comment"       => '',
                        "hotel_id"      => $this->hotel_id,
                        "created_by"    => $this->staff_id,
                        "created_on"    => date('Y-m-d H:i:s'),
                        "status"        => 0,
                        "completed_on"  => null, //'1999-01-01 00:00:00',
                        "updated_by"    => 0,
                        "updated_on"    => null
                    ];
                    $wup_id = WakeUpCall::create($wake_up_calls)->wup_id;
                    if ($wup_id) {
                        return [
                            "result" => true,
                            "message" => "Your wake up call is now scheduled.",
                        ];
                    }
                }
                return [
                    "result" => false,
                    "message" => "I'm sorry, it will not be possible to create the wake-up call since the date is less than the current date",
                ];
            }
            return [
                "result" => false,
                "message" => "There is not a registered user in the room",
            ];
        }
    }

    /**
     * 
     */
    private function HousekeepingBathroomAmenityIntent($data)
    {
        DB::beginTransaction();
        try {
            $message = '';
            if (isset($data['cleaning'])) {
                $message .= 'Requesting to have their bathroom clean ';
            }

            if (count($data['amenities']) > 0) {
                if (isset($data['cleaning'])) {
                    $message .= ',';
                }
                $message .= "";
                $i = 0;
                foreach ($data['amenities'] as $a) {
                    $i++;
                    $message .= (isset($a['number']) ? (is_numeric($a['number']) ? $a['number'] : 1) : $a['quantity']) . " " . $a['amenity'] . ',';
                }
                $message = substr($message, 0, -1);
            }

            $rs = $this->createEvent($message);

            DB::commit();
            $success = true;
            return [
                "result"    => true,
                "message"   => "I will have housekeeping work on your request and they will contact you shortly",
            ];
        } catch (\Exception $e) {
            //echo $e;
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function HousekeepingBathroomCleaningIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            $message .= 'Cleaning service for the room';
            /**
             * Create event
             */
            $rs = $this->createEvent($message);

            DB::commit();
            return [
                "result"    => true,
                "message"   => "I will have housekeeping work on your request and they will contact you shortly",
            ];
        } catch (\Exception $e) {
            DB::rollback();
            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function AmazonAlexaDefaultIntent()
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $message = 'The guest is requesting assistance. Please contact the guest';
            /**
             * Create event
             */
            $rs = $this->createEvent($message);
            DB::commit();
            $success = true;
            return [
                "result"    => true,
                "message"   => "We will work on your request and the hotel staff will contact shortly",
            ];
        } catch (\Exception $e) {
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function EngineeringACIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = '';
            if ($_guest['have_guest']) {
                $issue .= 'The guest';
            } else {
                $issue .= 'The cleaning staff';
            }
            $issue .= ' reports a problem with the A/C in the room';
            if (!empty($data['damage'])) {
                $issue .= ', type of problem: ' . $data['damage'];
            }
            if (!empty($data['part'])) {
                $issue .= ', Damaged area: ' . $data['part'];
            }
            /**
             * Create event
             */
            $this->createEvent($issue);

            DB::commit();
            $success = true;

            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            return $e;
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function EngineeringBadSmellIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = '';
            if ($_guest['have_guest']) {
                $issue .= 'The guest';
            } else {
                $issue .= 'The cleaning staff';
            }
            $issue .= ' reported a bad smell in the ' . (!empty($data['PartRoom']) ? $data['PartRoom'] : 'room');
            if (!empty($data['TypeSmell'])) {
                $issue .= ', Type smell: ' . $data['TypeSmell'];
            }
            /**
             * Create event
             */
            $this->createEvent($issue);

            DB::commit();
            $success = true;

            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            return $e;
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function EngineeringToiletIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = '';
            if ($_guest['have_guest']) {
                $issue .= 'The guest';
            } else {
                $issue .= 'The Engineer staff';
            }
            $issue .= ' report problems with the toilet';
            if (!empty($data['damage'])) {
                $issue .= ', damage: ' . $data['damage'];
            }
            /**
             * Create event
             */
            $this->createEvent($issue);

            DB::commit();
            $success = true;

            return [
                "result" => true
            ];
        } catch (\Exception $e) {
            return $e;
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function EngineeringSinkIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = '';
            if ($_guest['have_guest']) {
                $issue .= 'The guest';
            } else {
                $issue .= 'The Engineer staff';
            }
            $issue .= ' report problems with the sink';
            if (!empty($data['damage'])) {
                $issue .= ', damage: ' . $data['damage'];
            }
            /**
             * Create event
             */
            $this->createEvent($issue);

            DB::commit();
            $success = true;

            return [
                "result" => true
            ];
        } catch (\Exception $e) {
            return $e;
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false
            ];
        }
    }
    /**
     * 
     */
    private function EngineeringTubIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = '';
            if ($_guest['have_guest']) {
                $issue .= 'The guest';
            } else {
                $issue .= 'The Engineer staff';
            }
            $issue .= ' report problems with the tub';
            if (!empty($data['tub_damage'])) {
                $issue .= ', damage: ' . $data['tub_damage'];
            }
            /**
             * Create event
             */

            $this->createEvent($issue);

            DB::commit();
            $success = true;

            return [
                "result" => true
            ];
        } catch (\Exception $e) {
            return $e;
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false
            ];
        }
    }
    /**
     * 
     */
    private function EngineeringBedroomIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = '';
            if ($_guest['have_guest']) {
                $issue .= 'The guest';
            } else {
                $issue .= 'The Engineer staff';
            }
            $issue .= ' report problems in the Bedroom';
            if (!empty($data['part_of_bedroom'])) {
                $issue .= ': ' . $data['part_of_bedroom'];
            }
            if (!empty($data['damage_of_bedroom'])) {
                $issue .= ', damage:' . $data['damage_of_bedroom'];
            }
            /**
             * Create event
             */

            $this->createEvent($issue);

            DB::commit();
            $success = true;

            return [
                "result" => true
            ];
        } catch (\Exception $e) {
            return $e;
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false
            ];
        }
    }
    /**
     * 
     */
    private function EngineeringMiniBarIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = '';
            if ($_guest['have_guest']) {
                $issue .= 'The guest';
            } else {
                $issue .= 'The Engineer staff';
            }
            $issue .= ' report problems width the Minibar';

            if (!empty($data['minibar_damage'])) {
                $issue .= ', damage:' . $data['minibar_damage'];
            }
            /**
             * Create event
             */

            $this->createEvent($issue);

            DB::commit();
            $success = true;

            return [
                "result" => true
            ];
        } catch (\Exception $e) {
            return $e;
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false
            ];
        }
    }
    /**
     * 
     */
    private function EngineeringInternetIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = '';
            if ($_guest['have_guest']) {
                $issue .= 'The guest';
            } else {
                $issue .= 'The maintenance staff';
            }
            $issue .= ' reports problems with the internet';
            if (!empty($data['damage_internet'])) {
                $issue .= ', damage: ' . $data['damage_internet'];
            }
            if (!empty($data['part_internet'])) {
                $issue .= ', part: ' . $data['part_internet'];
            }
            /**
             * Create event
             */

            $this->createEvent($issue);

            DB::commit();
            $success = true;

            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            return $e;
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function EngineeringWallIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = '';
            if ($_guest['have_guest']) {
                $issue .= 'The guest';
            } else {
                $issue .= 'The maintenance staff';
            }
            $issue .= ' reports problems with the walls';
            if (!empty($data['damage_wall'])) {
                $issue .= ', damage: ' . $data['damage_wall'];
            }
            if (!empty($data['part_wall'])) {
                $issue .= ', part: ' . $data['part_wall'];
            }
            /**
             * Create event
             */

            $this->createEvent($issue);

            DB::commit();
            $success = true;

            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            return $e;
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function EngineeringPhoneIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = '';
            if ($_guest['have_guest']) {
                $issue .= 'The guest';
            } else {
                $issue .= 'The maintenance staff';
            }

            $issue .= ' reports problems with the Phone';
            if (!empty($data['damage_phone'])) {
                $issue .= ', damage: ' . $data['damage_phone'];
            }
            /**
             * Create event
             */

            $this->createEvent($issue);

            DB::commit();
            $success = true;

            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            return $e;
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function EngineeringTvIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = '';
            if ($_guest['have_guest']) {
                $issue .= 'The guest';
            } else {
                $issue .= 'The maintenance staff';
            }
            $issue .= ' reports problems with the TV';
            if (!empty($data['ProblemTV'])) {
                $issue .= ', Problem: ' . $data['ProblemTV'];
            }

            if (!empty($data['partTv'])) {
                $issue .= ', Part of the TV: ' . $data['partTv'];
            }
            /**
             * Create event
             */

            $this->createEvent($issue);

            DB::commit();
            $success = true;

            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            return $e;
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function bathroom($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = '';
            if ($_guest['have_guest']) {
                $issue .= 'the guest reports problems in the bathroom of the room.';
            } else {
                $issue .= 'problems are reported in the bathroom of the room';
            }
            $issue .= ', Type of damaged: ' . $data['ProblemBathroom'];
            if (!empty($data['PartBathroom'])) {
                $issue .= ', Affected zone: ' . $data['PartBathroom'];
            }
            /**
             * Create event
             */

            $this->createEvent($issue);

            DB::commit();
            $success = true;

            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            return $e;
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function EngineeringLightIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = '';
            if ($_guest['have_guest']) {
                $issue .= 'The guest reports problems in the room';
            } else {
                $issue .= 'Problems reported in the room';
            }
            $issue .= ' with the lighting system';
            if (!empty($data['part_room'])) {
                $issue .= ', Area: ' . $data['part_room'];
            }
            if (!empty($data['light_type'])) {
                $issue .= ', light type: ' . $data['light_type'];
            }
            if (!empty($data['damage_light'])) {
                $issue .= ', damage: ' . ($data['damage_light'] == 'out' ? 'Light out' : $data['damage_light']);
            }

            /**
             * Create event
             */

            $this->createEvent($issue);

            DB::commit();
            $success = true;

            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            return $e;
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function EngineeringDoorIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = '';
            if ($_guest['have_guest']) {
                $issue .= 'The guest reports problems in the room';
            } else {
                $issue .= 'Problems reported in the room';
            }
            if (!empty($data['door_type'])) {
                $issue .= ', ' . $data['door_type'];
            }
            if (!empty($data['damage_door'])) {
                $issue .= ', damage: ' . $data['damage_door'];
            }
            /**
             * Create event
             */

            $this->createEvent($issue);

            DB::commit();
            $success = true;

            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            return $e;
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function dryer($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = '';
            if ($_guest['have_guest']) {
                $issue .= 'The guest reports problems in the room';
            } else {
                $issue .= 'Problems reported in the room';
            }
            $issue .= ', It behaves badly in the dryer of the room';
            $issue .= ', Type of problem: ' . $data['ProblemDryer'];
            /**
             * Create event
             */

            $this->createEvent($issue);

            DB::commit();
            $success = true;

            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            return $e;
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function EngineeringFridgeIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = '';
            if ($_guest['have_guest']) {
                $issue .= 'The guest reports problems in the room';
            } else {
                $issue .= 'Problems reported in the room';
            }
            $issue .= ', the fridge does not work';

            if (!empty($data['damage_fridge'])) {
                $issue .= ', Damage: ' . $data['damage_fridge'];
            }
            if (!empty($data['part_of_fridge'])) {
                $issue .= ', Damaged area: ' . $data['part_of_fridge'];
            }
            /**
             * Create event
             */

            $this->createEvent($issue);
            DB::commit();
            $success = true;
            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function FrontDeskCheckoutIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = 'Guest is requesting assistance with checkout';

            /**
             * Create event
             */


            $this->createEvent($issue);

            DB::commit();
            $success = true;
            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function LateCheckoutRequest($data)
    {
        DB::beginTransaction();
        try {
            $_guest = $this->getGuest();

            $issue = 'Guest is requesting a late checkout';



            $this->createEvent($issue);

            DB::commit();
            $success = true;
            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function CallTaxi($data)
    {
        DB::beginTransaction();
        try {
            $_guest = $this->getGuest();

            $issue = 'Guest is requesting a taxi';



            $this->createEvent($issue);

            DB::commit();
            $success = true;
            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function CallValet($data)
    {
        DB::beginTransaction();
        try {
            $_guest = $this->getGuest();

            $issue = 'Guest is requesting their car';



            $this->createEvent($issue);

            DB::commit();
            $success = true;
            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function ContactPropertySecurity($data)
    {
        DB::beginTransaction();
        try {
            $_guest = $this->getGuest();

            $issue = 'Contact Property Security';



            $this->createEvent($issue);

            DB::commit();
            $success = true;
            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function RoomCleaningRequest($data)
    {
        DB::beginTransaction();
        try {
            $_guest = $this->getGuest();

            $issue = 'Guest is requesting housekeeping service';



            $this->createEvent($issue);

            DB::commit();
            $success = true;
            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function NoRoomCleaningRequest($data)
    {
        DB::beginTransaction();
        try {
            $_guest = $this->getGuest();

            $issue = 'Guest is declining cleaning service';



            $this->createEvent($issue);

            DB::commit();
            $success = true;
            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function FrontDeskBellmanIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            $totalLuggage = $data["totalLuggage"];
            //issue
            $issue = "Guest is requesting bellman assistance: $totalLuggage bags";

            /**
             * Create event
             */

            $this->createEvent($issue);
            DB::commit();
            $success = true;
            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function EngineeringShowerIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = 'shower';

            /**
             * Create event
             */

            $this->createEvent($issue);
            DB::commit();
            $success = true;
            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function HousekeepingBedroomIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = 'Guest is requesting';

            if (!empty($data['bedroom_items'])) {
                $issue .= ' ' . $data['bedroom_items'];
            }

            if (!empty($data['cleaning_type'])) {
                $issue .= ' ' . $data['cleaning_type'];
            }

            /**
             * Create event
             */

            $this->createEvent($issue);
            DB::commit();
            $success = true;
            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function HousekeepingMiniBarIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            //issue
            $issue = 'Guest is requesting';

            if (!empty($data['cleaning_type'])) {
                $issue .= ' ' . $data['cleaning_type'];
            }

            if (!empty($data['bedroom_items'])) {
                $issue .= ' ' . $data['bedroom_items'];
            }

            /**
             * Create event
             */

            $this->createEvent($issue);
            DB::commit();
            $success = true;
            return [
                "result"    => true,
                "message"   => "",
            ];
        } catch (\Exception $e) {
            $error = $e;
            $success = false;
            DB::rollback();

            return [
                "result"    => false,
                "message"   => ""
            ];
        }
    }
    /**
     * 
     */
    private function RoomServiceAlcoholicDrinkIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            $message = "";
            foreach ($data as $item) {
                $message .= $item["quantity"] . " " . ucwords(strtolower(str_replace('_', ' ', $item["name"]))) . ", ";
            }
            $message = trim($message, ', ');
            /**
             * Create event
             */

            $rs = $this->createEvent($message, $guest_id);

            DB::commit();
            return [
                "result"    => true,
                "message"   => "Your order was generated successfully, the hotel is working on your order",
            ];
        } catch (\Exception $e) {
            DB::rollback();
            return [
                "result"    => false,
                "message"   => "If an error occurred in your order, please contact the administrator"
            ];
        }
    }
    /**
     * 
     */
    private function RoomServiceDrinkIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            $message = "";
            foreach ($data as $item) {
                $message .= $item["quantity"] . " " . ucwords(strtolower(str_replace('_', ' ', $item["name"]))) . ", ";
            }
            $message = trim($message, ', ');
            /**
             * Create event
             */

            $rs = $this->createEvent($message, $guest_id);

            DB::commit();
            return [
                "result"    => true,
                "message"   => "Your order was generated successfully, the hotel is working on your order",
            ];
        } catch (\Exception $e) {
            DB::rollback();
            return [
                "result"    => false,
                "message"   => "If an error occurred in your order, please contact the administrator"
            ];
        }
    }
    /**
     * 
     */
    private function RoomServiceCocktailIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            $message = "";
            foreach ($data as $item) {
                $message .= "(" . $item["quantity"] . ") " . $item["name"] . ", ";
            }
            $message = trim($message, ', ');
            /**
             * Create event
             */

            $rs = $this->createEvent($message, $guest_id);

            DB::commit();
            return [
                "result"    => true,
                "message"   => "Your order was generated successfully, the hotel is working on your order",
            ];
        } catch (\Exception $e) {
            DB::rollback();
            return [
                "result"    => false,
                "message"   => "If an error occurred in your order, please contact the administrator"
            ];
        }
    }
    /**
     * 
     */
    private function RoomServiceBreakfastIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            $message = "Contact the guest to take the breakfast order";
            $message = trim($message, ', ');
            /**
             * Create event
             */

            $rs = $this->createEvent($message, $guest_id);

            DB::commit();
            return [
                "result"    => true,
                "message"   => "Your order was generated successfully, the hotel is working on your order",
            ];
        } catch (\Exception $e) {
            DB::rollback();
            return [
                "result"    => false,
                "message"   => "If an error occurred in your order, please contact the administrator"
            ];
        }
    }
    /**
     * 
     */
    private function RoomServiceLunchIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            $message = "Contact the guest to take the lunch order";
            $message = trim($message, ', ');
            /**
             * Create event
             */

            $rs = $this->createEvent($message, $guest_id);

            DB::commit();
            return [
                "result"    => true,
                "message"   => "Your order was generated successfully, the hotel is working on your order",
            ];
        } catch (\Exception $e) {
            DB::rollback();
            return [
                "result"    => false,
                "message"   => "If an error occurred in your order, please contact the administrator"
            ];
        }
    }
    /**
     * 
     */
    private function RoomServiceDinnerIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            $message = "Contact the guest to take the dinner order";
            $message = trim($message, ', ');
            /**
             * Create event
             */

            $rs = $this->createEvent($message, $guest_id);

            DB::commit();
            return [
                "result"    => true,
                "message"   => "Your order was generated successfully, the hotel is working on your order",
            ];
        } catch (\Exception $e) {
            DB::rollback();
            return [
                "result"    => false,
                "message"   => "If an error occurred in your order, please contact the administrator"
            ];
        }
    }
    /**
     * 
     */
    private function RoomServiceSaladIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            $message = "";
            foreach ($data as $item) {
                $message .= "(" . $item["quantity"] . ") " . $item["name"] . ", ";
            }
            $message = trim($message, ', ');
            /**
             * Create event
             */

            $rs = $this->createEvent($message, $guest_id);

            DB::commit();
            return [
                "result"    => true,
                "message"   => "Your order was generated successfully, the hotel is working on your order",
            ];
        } catch (\Exception $e) {
            DB::rollback();
            return [
                "result"    => false,
                "message"   => "If an error occurred in your order, please contact the administrator"
            ];
        }
    }
    /**
     * 
     */
    private function RoomServiceDessertIntent($data)
    {
        DB::beginTransaction();
        try {
            /**
             * Seleccinar el huesped que esta actualmente en la habitación, la función retorna:
             * Array['have_guest' => boolean, 'guest' => modelo GuestRegistration(si aplica)]
             */
            $_guest = $this->getGuest();
            $message = "";
            foreach ($data as $item) {
                $message .= "(" . $item["quantity"] . ") " . $item["name"] . ", ";
            }
            $message = trim($message, ', ');
            /**
             * Create event
             */

            $rs = $this->createEvent($message, $guest_id);

            DB::commit();
            return [
                "result"    => true,
                "message"   => "Your order was generated successfully, the hotel is working on your order",
            ];
        } catch (\Exception $e) {
            DB::rollback();
            return [
                "result"    => false,
                "message"   => "If an error occurred in your order, please contact the administrator"
            ];
        }
    }
    /**
     * 
     */
    private function createEvent($issue)
    {
        DB::beginTransaction();
        try {
            if ($this->dept_tag_id > 0) {
                $last_event         = Event::select('count_by_hotel_id')->where('hotel_id', $this->hotel_id)->orderBy('event_id', 'DESC')->first();
                $count_by_hotel_id = 1;
                if ($last_event) {
                    $count_by_hotel_id  = $last_event->count_by_hotel_id + 1;
                }

                $event_id = Event::create([
                    'hotel_id'                  => $this->hotel_id,
                    'guest_id'                  => 0,
                    'issue'                     => $issue,
                    'room_id'                   => $this->room_id,
                    'dept_tag_id'               => $this->dept_tag_id,
                    'date'                      => date('Y-m-d'),
                    'time'                      => date('H:i:s'),
                    "created_by"                => $this->staff_id,
                    "created_on"                => date('Y-m-d H:i:s'),
                    'closed_by'                 => 0,
                    'closed_on'                 => null,
                    'update_by'                 => null,
                    'delete_by'                 => 0,
                    'delete_on'                 => null,
                    'owner'                     => 0,
                    'pending_by'                => $this->staff_id,
                    'pending_on'                => date('Y-m-d H:i:s'),
                    'completed_by'              => null,
                    'completed_on'              => null,
                    'recurring_from'            => null,
                    'recurring_to'              => null,
                    'recurring_no_of_days'      => null,
                    'recurring_time'            => null,
                    'recurring_months'          => null,
                    'recurring_weeks'           => null,
                    'recurring_dates'           => null,
                    'recurring_status'          => null,
                    'second_notification_start' => null,
                    'third_notification_start'  => null,
                    'child_recurr'              => null,
                    'count_by_hotel_id'         => $count_by_hotel_id

                ])->event_id;
                $this->saveLogTracker([
                    'module_id' => 1,
                    'action'    => 'add',
                    'prim_id'   => $event_id,
                    'staff_id'  => $this->staff_id,
                    'date_time' => date('Y-m-d H:i:s'),
                    'comments'  => '',
                    'hotel_id'  => $this->hotel_id,
                    'type'      => 'Amazon Alexa'
                ]);
                DB::commit();
                return $event_id;
            }
            return 0;
        } catch (\Exception $e) {
            echo $e;
            DB::rollback();
            return 0;
        }
    }
    /**
     * 
     */
    private function getGuest()
    {
        $have_guest = false;
        $gr         = null;
        $gcd        = GuestCheckinDetails::select('guest_id')->where('hotel_id', $this->hotel_id)
            ->where('room_no', $this->room_id)->where('status', 1)
            ->orderBy('sno', 'DESC')->first();
        if ($gcd) {
            $have_guest = true;
            $gr = GuestRegistration::select('guest_id')->where('guest_id', $gcd->guest_id)->first();
        }

        return [
            "have_guest"    => $have_guest,
            'guest'         => $gr
        ];
    }

    public function alexaAuthorize(Request $request)
    {
        $this->data = [
            "client_id"     => $request->client_id,
            "response_type" => $request->response_type,
            "state"         => $request->state
        ];

        $query = http_build_query([
            'client_id'     => $this->data['client_id'],
            'redirect_uri'  => url('/') . '/alexa/callback',
            'response_type' => 'code',
            'scope'         => '',
        ]);

        return redirect(url('/') . '/oauth/authorize?' . $query);

        //return view("auth.alexa.login", $data);
    }

    public function callback(Request $request)
    {
        $http = new GuzzleHttp\Client;

        $response = $http->post('http://localhost:8000/oauth/token', [
            'form_params' => [
                'grant_type'    => 'authorization_code',
                'client_id'     => '14',
                'client_secret' => 'X3TsmnZXjUPCguUXGXwkVcV3HaSimmPGb1lcANk1',
                'redirect_uri'  => 'http://localhost:8080/callback',
                'code' => $request->code,
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    public function getRoomByAlexa(Request $request)
    {

        if (!$request->has('device_id')) {
            return response()->json([
                'status' => 400,
                'error'  => 'device_id_not_provided'
            ], 400);
        }
        $device = $request->device_id;
        $room = AlexaDevice::with(['Room'])->where('device_alexa_id', $device)->first();
        if ($room) {
            return response()->json($room, 200);
        } else {
            return response()->json([
                'status' => 404,
                'error'  => 'device_not_found'
            ], 404);
        }
    }

    public function getStaffData(Request $request)
    {
        if (!$request->has('staff_code')) {
            return response()->json([
                'status' => 400,
                'error'  => 'staff_code_not_provided'
            ], 400);
        }

        if (!$request->has('hotel_id')) {
            return response()->json([
                'status' => 400,
                'error'  => 'hotel_id_not_provided'
            ], 400);
        }

        $code = $request->staff_code;
        $hotel_id = $request->hotel_id;

        $staff = User::selectRaw("staff.staff_id,firstname,lastname,username, staff.is_active, phone_number, role_id")->where('access_code', $code)->join('staff_hotels', 'staff.staff_id', '=', 'staff_hotels.staff_id')
            ->whereRaw("staff.access_code = $code and hotel_id = $hotel_id")->first();

            
            
            if ($staff) {
            $hsk_sup = HousekeepingStaff::where('staff_id', $staff->staff_id)->where('is_active', 1)->first();
            return response()->json(['staff_data' => $staff,
                'is_supervisor' =>$hsk_sup ? $hsk_sup->is_housekeeper_super: null
            ], 200);
        } else {
            return response()->json([
                'status' => 404,
                'error'  => 'staff_not_found'
            ], 404);
        }
    }


    public function createEventAlexa(Request $request)
    {
        DB::beginTransaction();
        try {
            if (!$request->has('event')) {
                return response()->json([
                    "error_code"        => "OBJECT_NOT_PROVIDED",
                    "error_type"        => trans('error.object_not_provided'),
                    "error_description" => [
                        trans('error.object_not_provided_text', ['object' => 'Event'])
                    ],
                ], 400);
            }

            // Capturar la información
            $event = $request->event;

            // Capturar datos del usuario

            // Validar los datos del json object
            $Validator = \Validator::make($event, [
                'hotel_id'      => 'required',
                'staff_id'      => 'required',
                'room_id'       => 'required',
                'priority'      => [
                    'numeric',
                    Rule::in([1, 2, 3])
                ],
                'issue'         => 'required',
                'tag_id'        => 'required',
                'dept_id'       => 'required',
            ]);



            // Si se encuentra algun error validar
            if ($Validator->fails()) {
                return response()->json([
                    "error_code"        => "VALIDATION_ERROR",
                    "error_type"        => trans('error.validation_error'),
                    "error_description" => $Validator->errors(),
                ], 400);
            }
            $staff = StaffHotel::where('hotel_id', $event['hotel_id'])->where('staff_id', $event['staff_id'])->where('is_active', 1)->first();
            if (!$staff) {
                return response()->json([
                    "error_code"        => "VALIDATION_ERROR",
                    "error_type"        => trans('error.validation_error'),
                    "error_description" => 'INCORRECT_USER_DATA',
                ], 400);
            }

            $room = HotelRoom::where('hotel_id', $event['hotel_id'])->where('room_id', $event['room_id'])->where('active', 1)->first();
            if (!$room) {
                return response()->json([
                    "error_code"        => "VALIDATION_ERROR",
                    "error_type"        => trans('error.validation_error'),
                    "error_description" => 'ROOM_NOT_FOUND',
                ], 400);
            }

            $dept_tag = DeptTag::where('hotel_id', $event['hotel_id'])->where('dept_id', $event['dept_id'])->where('tag_id', $event['tag_id'])->where('is_active', 1)->first();
            if (!$dept_tag) {
                return response()->json([
                    "error_code"        => "VALIDATION_ERROR",
                    "error_type"        => trans('error.validation_error'),
                    "error_description" => 'DEPT_TAG_NOT_FOUND',
                ], 400);
            }

            $last_event = Event::where('hotel_id', $event['hotel_id'])
                ->orderBy('event_id', 'DESC')
                ->first();

            $count_by_hotel_id = 0;
            if ($last_event) {
                $count_by_hotel_id = $last_event->count_by_hotel_id + 1;
            }

            $data = [
                'hotel_id'                  => $event['hotel_id'],
                'guest_id'                  => 0,
                'issue'                     => $event['issue'],
                'room_id'                   => $event['room_id'],
                'dept_tag_id'               => $dept_tag->dept_tag_id,
                'date'                      => date('Y-m-d'),
                'time'                      => date('H:i:s'),
                "created_by"                => $event['staff_id'],
                "created_on"                => date('Y-m-d H:i:s'),
                'closed_by'                 => 0,
                'closed_on'                 => null,
                'update_by'                 => null,
                'delete_by'                 => 0,
                'delete_on'                 => null,
                'owner'                     => 0,
                'pending_by'                => $event['staff_id'],
                'pending_on'                => date('Y-m-d H:i:s'),
                'count_by_hotel_id'         => $count_by_hotel_id
            ];
            $now = date('Y-m-d H:i:s');
            $guest_checkin_details =  GuestCheckinDetails::select('room_no', 'guest_id')
                ->where(function ($q) use ($data, $now) {
                    $q
                        ->where('status', 1)
                        ->where('hotel_id', $data['hotel_id'])
                        ->whereRaw(DB::raw("'$now' >= check_in and '$now' <= check_out"));
                    $q->where('room_no', $data['room_id']);
                })
                ->orderBy('sno', 'DESC')
                ->first();
            $data['guest_id'] = !empty($event['guest_id']) ? $event['guest_id'] : 0;
            $Event = Event::create($data);
            DB::commit();

            return response()->json([
                "create"   => true,
                "event"    => $Event,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            \Log::info($th);
            return response()->json([
                "error_code"    => "SOMETHING_WENT_WRONG",
                "error_type"    => trans('error.something_went_wrong'),
                "error_description" => [trans('error.something_went_wrong_text'), "$e"],
            ], 500);
        }
    }


    public function searchTag(Request $request)
    {
        if (!$request->has('tag')) {
            return response()->json([
                "error_code"        => "OBJECT_NOT_PROVIDED",
                "error_type"        => trans('error.object_not_provided'),
                "error_description" => [
                    trans('error.object_not_provided_text', ['object' => 'Tag'])
                ],
            ], 400);
        }

        // Capturar la información
        $tag = $request->tag;

        // Capturar datos del usuario

        // Validar los datos del json obj ect
        $Validator = \Validator::make($tag, [
            'hotel_id'      => 'required | exists:hotels,hotel_id',
            // 'staff_id'      => 'required | exists:staff,staff_id',
            'tag_name'       => 'required',
        ]);

        if ($Validator->fails()) {
            return response()->json([
                "error_code"        => "VALIDATION_ERROR",
                "error_type"        => trans('error.validation_error'),
                "error_description" => $Validator->errors(),
            ], 400);
        }
        $_tag_name = explode(' ', $tag['tag_name']);

        $tag_data = Tag::with([
            'departments'
        ])->where('hotel_id', $tag['hotel_id'])->where('is_active', 1);

        foreach ($_tag_name as $key => $value) {
            if (strlen($value) > 3) {
                $tag_data->where('tag_name', 'LIKE', "%$value%");
            }
        }
        $tag_ids = $tag_data->pluck('tag_id');
        $tag_data = $tag_data->get();
        $opcionals_tags = Tag::with([
            'departments'
        ])->where('hotel_id', $tag['hotel_id'])->where('is_active', 1);
        if ($tag_data) {
            $opcionals_tags->whereNotIn('tag_id', $tag_ids);
        }

        $opcionals_tags->where(function ($query) use ($_tag_name) {
            $sw = 0;
            foreach ($_tag_name as $key => $value) {
                if (strlen($value) > 3) {
                    if ($sw == 0) {
                        $query->where('tag_name', 'LIKE', "%$value%");
                        $sw = 1;
                    } else {
                        $query->orWhere('tag_name', 'LIKE', "%$value%");
                    }
                }
            }
        });

        $opcionals_tags = $opcionals_tags->get();

        $data = [
            'tag' => $tag_data,
            'others_tags' => $opcionals_tags
        ];

        return response()->json($data, 200);
    }


    public function autoInspection(Request $request)
    {
        if (!$request->has('data')) {
            return response()->json([
                "error_code"        => "OBJECT_NOT_PROVIDED",
                "error_type"        => trans('error.object_not_provided'),
                "error_description" => [
                    trans('error.object_not_provided_text', ['object' => 'Data'])
                ],
            ], 400);
        }

        // Capturar la información
        $data = $request->data;

        $Validator = \Validator::make($data, [
            'hotel_id'      => 'required | exists:hotels,hotel_id',
            'staff_id'      => 'required | exists:staff,staff_id',
            'room_id'       => 'required',
        ]);

        if ($Validator->fails()) {
            return response()->json([
                "error_code"        => "VALIDATION_ERROR",
                "error_type"        => trans('error.validation_error'),
                "error_description" => $Validator->errors(),
            ], 400);
        }

        $hk_status = HousekeepingCleanings::whereDate('created_on', date('Y-m-d'))
            ->where('hotel_id', $data['hotel_id'])
            ->where('room_id', $data['room_id'])
            ->where('is_active', 1)->orderBy('cleaning_id', 'DESC')->first();

        $hsk_sup = HousekeepingStaff::where('staff_id', $data['staff_id'])->where('is_active', 1)->where('is_housekeeper_super', 0)->first();

        if ($hk_status && $hsk_sup) {

            if (in_array($hk_status->front_desk, [2, 4, 6, 7])) {
                return response()->json([
                    "response"          => "OCCUPIED_ROOM",
                    "inspection"        => false
                ], 400);
            }


            $hk_status->hk_status = 4;
            $hk_status->inspected_on = date('Y-m-d H:i:s');
            $hk_status->inspected_by = $data['staff_id'];
            $hk_status->updated_on = date('Y-m-d H:i:s');
            $hk_status->updated_by = $data['staff_id'];
            $hk_status->hk_status_on = date('Y-m-d H:i:s');
            $hk_status->hk_status_by = $data['staff_id'];
            $hk_status->save();

            return response()->json([
                "response"          => "INSPECTION_SUCCESS",
                "inspection"        => true
            ], 200);
        }
        return response()->json([
            "error_code"          => "VALIDATION_ERROR",
            "error_description" => 'NOT_ASSIGNED',
        ], 400);
    }
    public function changeHskStatus(Request $request)
    {
        if (!$request->has('data')) {
            return response()->json([
                "error_code"        => "OBJECT_NOT_PROVIDED",
                "error_type"        => trans('error.object_not_provided'),
                "error_description" => [
                    trans('error.object_not_provided_text', ['object' => 'Data'])
                ],
            ], 400);
        }

        // Capturar la información
        $data = $request->data;

        $Validator = \Validator::make($data, [
            'hotel_id'      => 'required | exists:hotels,hotel_id',
            'staff_id'      => 'required | exists:staff,staff_id',
            'room_id'       => 'required',
            'hk_status'      => [
                'numeric',
                Rule::in([1, 2, 3, 4, 5])
            ],
        ]);
 

        if ($request->isMethod('get')) {
            $hk_cleaning = HousekeepingCleanings::select(['cleaning_id','room_id','housekeeper_id', 'supervisor_id', 'hk_status', 'front_desk_status', 'assigned_date', 'hotel_id','created_on', 'is_active'])->whereDate('created_on', date('Y-m-d'))
            ->where('hotel_id', $data['hotel_id'])
            ->where('room_id', $data['room_id'])
            ->where('is_active', 1)->orderBy('cleaning_id', 'DESC')->first();

            if($hk_cleaning){
                return response()->json($hk_cleaning);
            }else{
                return response()->json([
                    "error_code"          => "VALIDATION_ERROR",
                    "error_description" => 'NOT_ASSIGNED',
                ], 400);
            }
        }
        $hk_cleaning = HousekeepingCleanings::whereDate('created_on', date('Y-m-d'))
            ->where('hotel_id', $data['hotel_id'])
            ->where('room_id', $data['room_id'])
            ->where('is_active', 1)->orderBy('cleaning_id', 'DESC')->first();





        $hsk_staff = HousekeepingStaff::where('staff_id', $data['staff_id'])->where('is_active', 1)->first();

        $this->configTimeZone($data['hotel_id']);
        $this->now = date('Y-m-d H:i:s');
        if ($hk_cleaning && $hsk_staff) {
            if (array_has($data, 'hk_status')) {

                switch ($data['hk_status']) {
                    case 1:
                        $hk_cleaning->started_on = null;
                        $hk_cleaning->hk_status_on = null;
                        $hk_cleaning->hk_status_by = null;
                        $hk_cleaning->is_paused = 1;
                        $hk_cleaning->cleaning_duration = null;
                        $hk_cleaning->hk_status = $data['hk_status'];
                        $hk_cleaning->save();
                        return response()->json([
                            "response"          => "CHANGE_SUCCESS",
                            "hk_status"        => true
                        ], 200);
                        break;
                    case 2:
                        $hk_cleaning->started_on = date('Y-m-d H:i:s');
                        $hk_cleaning->hk_status_on = date('Y-m-d H:i:s');
                        $hk_cleaning->hk_status_by = $data['staff_id'];
                        $hk_cleaning->is_paused = 0;
                        $hk_cleaning->hk_status = $data['hk_status'];
                        $hk_cleaning->save();
                        return response()->json([
                            "response"          => "CHANGE_SUCCESS",
                            "hk_status"        => true
                        ], 200);
                        break;
                    case 3:
                        $hk_cleaning->hk_status_on = date('Y-m-d H:i:s');
                        $hk_cleaning->hk_status_by = $data['staff_id'];
                        $hk_cleaning->hk_status = $data['hk_status'];
                        $hk_cleaning->ended_on = date('Y-m-d H:i:s');
                        $hk_cleaning->cleaning_duration = $this->calcular_duration($hk_cleaning);
                        $hk_cleaning->save();

                        $hk_cleaning_next = HousekeepingCleanings::select('hotel_id','room_id','hk_status')->with(
                            [
                                'Room' => function ($query) {
                                    $query->select(['room_id', 'location']);
                                }
                            ]
                        )->whereDate('created_on', date('Y-m-d'))
                        ->where('hotel_id', $data['hotel_id'])
                        ->where('room_id','!=', $data['room_id'])
                        ->where('housekeeper_id', $data['staff_id'])
                        ->whereNotIn('hk_status', [3, 4])
                        ->where('is_active', 1)->orderBy('cleaning_order', 'ASC')->orderBy('hk_status', 'ASC')->first();
                        return response()->json([
                            "response"          => "CHANGE_SUCCESS",
                            "hk_status"        => true,
                            "next_room"        => $hk_cleaning_next
                        ], 200);
                        break;
                        
                    default:
                        return response()->json([
                            "error_code"          => "INCORRECT_HK_STATUS",
                        ], 400);
                        break;
                }
            } elseif (array_has($data, 'is_paused')) {
                switch ($data['is_paused']) {
                    case 1:
                        $hk_cleaning->cleaning_duration = $this->calcular_duration($hk_cleaning);
                        $hk_cleaning->is_paused = 1;
                        $hk_cleaning->paused_on = date('Y-m-d H:i:s');
                        break;
                    case 0:
                        $hk_cleaning->is_paused = 0;
                        $hk_cleaning->paused_on = date('Y-m-d H:i:s');
                        break;
                    default:
                        return response()->json([
                            "error_code"          => "INCORRECT_HK_STATUS",
                        ], 400);
                        break;
                }
            }
            
        }else{
            return response()->json([
                "error_code"          => "VALIDATION_ERROR",
                "error_description" => 'NOT_ASSIGNED',
            ], 400);
        }
    }


    public function durationHelper($duration_curr, $paused_on = null, $started_on = null)
    {
        if (is_null($started_on) && is_null($paused_on)) return false;
        $duration = $duration_curr ?: 0;

        $diff = (new DateTime($paused_on ?: $started_on))->diff(new DateTime($this->now));
        $seconds = ($diff->h * 3600) + ($diff->i * 60) + ($diff->s);
        $duration += $seconds;
        return $duration;
    }

    public function calcular_duration($value)
    {
        if ($value->is_paused == 0) {
            $duration = $this->durationHelper($value->cleaning_duration, $value->paused_on, $value->started_on);
        } else {
            $duration = $value->cleaning_duration ? $value->cleaning_duration : '0';
        }
        return $duration;
    }



    
    public function alexaAuth(Request $request)
    {
        $client_id = $request->client_id;
        // $client_secret = $request['secret'];
        $redirect_uri = $request->redirect_uri;

        $data = OauthClient::where('id', $client_id)->where('redirect', 'LIKE',"%".$redirect_uri."%")->first();
        \Log::info(json_encode($data));
        $credentials = [
            'client_id' => $client_id,
            // 'client_secret' => $client_secret,
            'redirect_uri' => $redirect_uri,
            'state'         => $request->state
        ];
        \Log::info(json_encode($credentials));
        if ($data) {
            return view('auth.alexa.login')->with('data', $credentials);
        }

    }

    public function singIn(Request $request)
    {
        $user = $request->username;
        $pass = md5($request->password);
        $client_id = $request->client_id;
        // $client_secret = $request['secret'];
        $redirect_uri = $request->redirect_uri;
        $staff = User::where('username', $user)->where('password', $pass)->where('is_active', 1)->first();
        \Log::info(json_encode($staff));

        $credentials = [
            // 'client_id' => $client_id,
            // 'client_secret' => $client_secret,
            // 'redirect_uri' => $redirect_uri,
            'state'         => $request->state,
            'code'          => $this->generarCodigo(80),
            'statusCode'    => 301
        ];
        \Log::info(json_encode($credentials));

        $codeGrant = OauthCode::create([
            'id' => $credentials['code'],
            'user_id' => $staff->staff_id,
            'client_id' => $client_id,
            'scopes' => '[]',
            'revoked' => 0,
            'expires_at' => date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s'). '+3 hours'))
        ]);

        if ($staff) {
            return Redirect::to($redirect_uri."?".http_build_query($credentials));
        }

        
    }

    function generarCodigo($length) {
        return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length); 
    }

    public function generateToken(Request $request)
    {
        $client_id = $request->client_id;
        $code = $request->code;
        $client_secret = $request->secret;
        $redirect_uri = $request->redirect_uri;
        $staff = User::find(OauthCode::find($code)->user_id);
        \Log::info(json_encode($staff));

        $token = $staff->createToken('Alexa-Integration',['*']);
        return response()->json([
            'access_token' => $token->accessToken,
            'token_type' => 'bearer',
            'expires_in' => strtotime($token->token['expires_at']) - strtotime($token->token['created_at']),
            'refresh_token' => $token->accessToken
        ],200);
    }

    public function getGuestData(Request $request)
    {
        $data = $request->data;
        $Validator = \Validator::make($data, [
            'hotel_id'      => 'required | exists:hotels,hotel_id',
            'staff_id'      => 'required | exists:staff,staff_id',
            'room_id'       => 'required',
        ]);

        if ($Validator->fails()) {
            return response()->json([
                "error_code"        => "VALIDATION_ERROR",
                "error_type"        => trans('error.validation_error'),
                "error_description" => $Validator->errors(),
            ], 400);
        }
        $have_guest = false;
        $gr         = null;
        $gcd        = GuestCheckinDetails::where('hotel_id', $data['hotel_id'])
            ->where('room_no', $data['room_id'])->where('status', 1)
            ->orderBy('check_in', 'ASC')->first();
        if ($gcd) {
            $have_guest = true;
            $gr = GuestRegistration::where('guest_id', $gcd->guest_id)->first();
        }

        return response()->json( [
            "have_guest"    => $have_guest,
            'guest'         => $gr,
            "reservation"   => $gcd
        ]);
    }

    public function saveDevice(Request $request) {
        DB::enableQueryLog(); // Enable query log
        if (!$request->has('data')) {
            return response()->json([
                'status' => 400,
                'error'  => 'the "data" object has not been found.'
            ], 400);
        }

        $data = $request->data;

        $Validator = \Validator::make($data, [
            'hotel_id'   => 'required | exists:hotels,hotel_id',
            'device_id'  => 'required',
            'room'       => 'required',
            'staff_code' => 'required',
            ]);
            
            if ($Validator->fails()) {
                return response()->json([
                    "error_code"        => "VALIDATION_ERROR",
                    "error_type"        => trans('error.validation_error'),
                    "error_description" => $Validator->errors(),
                ], 400);
        }
        
        $code = $data['staff_code'];
        $hotel_id = $data['hotel_id'];
        $room = $data['room'];
        $device_id = $data['device_id'];
        
        //Consulto si el usuario es admin basandome en el access_code
        $is_admin = User::selectRaw("staff.staff_id")->join('hotels', 'staff.staff_id', '=', 'hotels.account')
        ->whereRaw("staff.access_code = $code and hotels.hotel_id = $hotel_id and staff.is_active = 1")->first();
        \Log::info($is_admin);
        if ( !$is_admin ) {

            return response()->json([
                'msg' => 'YOU DO NOT HAVE THE AUTHORIZATION TO PERFORM THIS ACTION.'
            ], 400);
        
        }

        //Obtengo el id del room para luego poder almacenarlo en la tabla alexa_device
        $room_id = HotelRoom::select('room_id')
                              ->where('location', $room)
                              ->where('hotel_id', $hotel_id)->first();
        if ( !$room_id ) {
            return response()->json([
                'msg' => 'ROOM NOT FOUND.'
            ], 400);
        }

        /**
         * Consulto si el room_id ya esta en la tabla, en caso tal de que este se actualizara el device_alexa_id
         * si no se creara un campo nuevo en la tabla
         */
        $room_alexa = AlexaDevice::where('room_id', $room_id->room_id)->first();

        if ( $room_alexa ) {
            $room_alexa->device_alexa_id = $device_id;
            $room_alexa->save();
            return response()->json([
                'data' => $room_alexa,
                'msg' => 'UPDATED SUCCESSFULLY.'
            ], 200);
        } else {
            $alexaDevice = new AlexaDevice;
            $alexaDevice->device_alexa_id = $device_id;
            $alexaDevice->room_id = $room_id->room_id;
            $alexaDevice->hotel_id = $hotel_id;
            $alexaDevice->save();
            return response()->json([
                'data' => $alexaDevice,
                'msg' => 'CREATED SUCCESSFULLY.'
            ], 200);
        }
        

        
    }

}
