<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\IntegrationsActive;
use App\Models\IntegrationsRoomStayntouch;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class StayNTouchController extends Controller
{
    private $config = null;

    private $hotel_id = null;

    private $pms_hotel_id = null;

    private $staff_id = null;

    public function Guest(Request $request, $hotel_id)
    {
        // \Log::info($request);

        if ($request->event == 'anonymize_guest') {
            return null;
        }

        if ($request->event == 'room_status' || $request->event == 'room_status_service') {
            // $this->Housekeeping($request, $hotel_id);
        } else {
            if ((isset($request->data['hotel_id'])) || ! isset($request->data['hotel_id'])) {
                $id = $request->data['id'];
                $this->getIntegration($hotel_id);
                $this->configTimeZone($this->hotel_id);
                $now = date('Y-m-d H:i:s');
                $this->CheckToken();
                $last = strlen($request->data['last_name']) - 1 > 0 ? str_repeat('*', strlen($request->data['last_name']) - 1) : '*';
                $first = strlen($request->data['first_name']) - 1 > 0 ? str_repeat('*', strlen($request->data['first_name']) - 1) : '*';
                if (
                    ! (substr($request->data['last_name'], 1) == $last) &&
                    ! (substr($request->data['first_name'], 1) == $first)
                ) {
                    $data = $this->GetData($id, 'guests');
                    $data = $this->formatGuest($data);
                    $this->dispatch(new \App\Jobs\StaynTouch($data, $this->hotel_id, $this->pms_hotel_id, $this->config, $this->staff_id, $now));
                }
            }
        }
    }

    public function Reservation(Request $request, $hotel_id)
    {
        if ($request->event == 'room_status' || $request->event == 'room_status_service') {
            // $this->Housekeeping($request, $hotel_id);
        } else {
            if ((isset($request->data['hotel_id'])) || ! isset($request->data['hotel_id'])) {
                $id = $request->data['id'];
                if ($this->getIntegration($request->data['hotel_id'])) {
                    $this->configTimeZone($this->hotel_id);
                    $now = date('Y-m-d H:i:s');
                    $this->CheckToken();
                    $data = $this->GetData($id, 'reservations');
                    $data = $this->formatReservation($data);
                    $this->dispatch(new \App\Jobs\StaynTouch($data, $this->hotel_id, $this->pms_hotel_id, $this->config, $this->staff_id, $now));
                }
            }
        }
    }

    public function Housekeeping(Request $request, $hotel_id)
    {
        // \Log::info($request);
        if ($request->event == 'room_status' || $request->event == 'room_status_service') {
            if ($this->getIntegration($request->data['hotel_id'])) {
                $this->configTimeZone($this->hotel_id);
                $now = date('Y-m-d H:i:s');
                $this->CheckToken();
                $data = $request->data;
                $data = $this->formatHousekeeping($data);
                $this->dispatch(new \App\Jobs\StaynTouch([$data], $this->hotel_id, $this->pms_hotel_id, $this->config, $this->staff_id, $now));
            }
        }
    }

    public function formatHousekeeping($data)
    {
        $__data = [
            'status' => Arr::get($data, 'status', ''),
            'location' => Arr::get($data, 'room.number', ''),
        ];

        return $__data;
    }

    private function CheckToken()
    {
        try {
            if (empty($this->config['access_token'])) {
                $this->config = $this->GetToken();
            }
            if (strtotime($this->config['expires_in']) < strtotime(date('Y-m-d H:i:s'))) {
                $this->config = $this->GetToken();
            }
        } catch (\Exception $e) {
            \Log::error('Error in StayNTouchController CheckToken');
            \Log::error($e);

            return null;
        }
    }

    private function GetToken()
    {
        try {
            $config = IntegrationsActive::where('int_id', 8)->where('pms_hotel_id', $this->pms_hotel_id)->first();
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $this->config['url_token'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => ['client_id' => $config->config['client_id'], 'client_secret' => $config->config['client_secret'], 'grant_type' => $config->config['grant_type']],
            ]);
            //dd($this->config['url_token']);

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if ($err) {
                return null;
            }
            $token = json_decode($response);

            $config_data = $config->config;
            $config_data['access_token'] = $token->access_token;
            $config_data['expires_in'] = date('Y-m-d H:i:s', $token->created_at + $token->expires_in);
            $config->config = $config_data;
            $config->save();
            $this->config = $config->config;

            return $config->config;
        } catch (\Exception $e) {
            \Log::error('Error in StayNTouchController GetToken');
            \Log::error($e);

            return null;
        }
    }

    public function getIntegration($hotel_id)
    {
        // \Log::info($hotel_id);
        $config = IntegrationsActive::where('int_id', 8)->where('pms_hotel_id', $hotel_id)->where('state', 1)->first();
        if ($config) {
            $this->config = $config->config;
            $this->hotel_id = $config->hotel_id;
            $this->pms_hotel_id = $config->pms_hotel_id;
            $this->staff_id = $config->created_by;

            return true;
        }

        return false;
    }

    private function GetData($id, $endpoint)
    {
        try {
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $this->config['url']."$endpoint/$id?hotel_id=$this->pms_hotel_id",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'api-version: 2.0',
                    'Authorization: Bearer '.$this->config['access_token'],
                ],
            ]);

            $response = curl_exec($curl);

            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                return null;
            }
            // \Log::info($response);
            return json_decode($response, true);
        } catch (\Exception $e) {
            \Log::error('Error in StayNTouchController GetData');
            \Log::error($e->getMessage());

            return null;
        }
    }

    public function formatReservation($data)
    {
        $__data = [
            'reservation_number' => Arr::get($data, 'id', ''),
            'check_in' => Arr::get($data, 'arrival_date').' '.Arr::get($data, 'arrival_time'),
            'check_out' => Arr::get($data, 'departure_date').' '.Arr::get($data, 'departure_time'),
            'status' => 1,
            'reservation_status' => 0,
            'location' => Arr::get($data, 'room.number', ''),
        ];

        switch ($data['status']) {
            case 'CHECKEDIN':
                $__data['status'] = 1;
                $__data['reservation_status'] = 1;
                $__data['check_in'] = Arr::get($data, 'arrival_date').' '.date('H:i:s');
                break;
            case 'CHECKEDOUT':
                $__data['status'] = 0;
                $__data['reservation_status'] = 3;
                $__data['check_out'] = Arr::get($data, 'departure_date').' '.date('H:i:s');
                break;
            case 'NOSHOW':
                $__data['status'] = 0;
                $__data['reservation_status'] = 4;
                break;
            case 'CANCELED':
                $__data['status'] = 0;
                $__data['reservation_status'] = 2;
                break;
        }

        foreach (Arr::get($data, 'guests') as $value) {
            if ($value['is_primary'] == true) {
                $__data['guest_number'] = $value['id'];
                $__data['firstname'] = Arr::get($value, 'first_name', '');
                $__data['lastname'] = Arr::get($value, 'last_name', '');
                $__data['email_address'] = Arr::get($value, 'email', '');
                $__data['dob'] = Arr::get($value, 'birthday', '');
                $phone_no = Arr::get($value, 'mobile_phone', '');
                $phone_format = '';
                if ($phone_no != '') {
                    $phone_no = utf8_decode(preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $phone_no));
                    if (substr($phone_no, 0, 1) != '+') {
                        $phone_format = str_replace(['-', '.', '?', ' ', '(', ')', '*', '/', 'na', '+'], '', $phone_no);
                        if (substr($phone_format, 0, 1) == '1') {
                            $phone_format = substr($phone_format, 1);
                        }
                        // dd(($phone_format));

                        if (! empty($phone_format) && is_numeric($phone_format)) {
                            $phone_format = "+1$phone_format";
                        }
                    } else {
                        $phone_format = str_replace(['-', '.', ' ', '(', ')', '*', '/', 'na'], '', $phone_no);
                        if (! empty($phone_format) && is_numeric($phone_format)) {
                            $phone_format = "$phone_format";
                        } else {
                            $phone_format = '';
                        }
                    }
                }
                $__data['phone_no'] = $phone_format;
                $__data['language'] = Arr::get($value, 'language', '');
                $__data['state'] = Arr::get($value, 'address.state', '');
                $__data['city'] = Arr::get($value, 'address.city', '');
                $__data['zipcode'] = Arr::get($value, 'address.postal_code', '');
                $__data['address'] = Arr::get($value, 'address.address_line1', '').','.Arr::get($value, 'address.address_line2', '');
                break;
            }
        }

        return $__data;
    }

    public function formatGuest($data)
    {
        $__data = [];
        $__data['guest_number'] = $data['id'];
        $__data['firstname'] = Arr::get($data, 'first_name', '');
        $__data['lastname'] = Arr::get($data, 'last_name', '');
        $__data['email_address'] = Arr::get($data, 'email', '');
        $__data['dob'] = Arr::get($data, 'birthday', '');
        $phone_no = Arr::get($data, 'mobile_phone', '');
        $phone_format = '';
        if ($phone_no != '') {
            $phone_no = utf8_decode(preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $phone_no));
            if (substr($phone_no, 0, 1) != '+') {
                $phone_format = str_replace(['-', '.', '?', ' ', '(', ')', '*', '/', 'na', '+'], '', $phone_no);
                if (substr($phone_format, 0, 1) == '1') {
                    $phone_format = substr($phone_format, 1);
                }
                // dd(($phone_format));

                if (! empty($phone_format) && is_numeric($phone_format)) {
                    $phone_format = "+1$phone_format";
                }
            } else {
                $phone_format = str_replace(['-', '.', ' ', '(', ')', '*', '/', 'na'], '', $phone_no);
                if (! empty($phone_format) && is_numeric($phone_format)) {
                    $phone_format = "$phone_format";
                } else {
                    $phone_format = '';
                }
            }
        }
        $__data['phone_no'] = $phone_format;
        $__data['language'] = Arr::get($data, 'language', '');
        $__data['state'] = Arr::get($data, 'address.state', '');
        $__data['city'] = Arr::get($data, 'address.city', '');
        $__data['zipcode'] = Arr::get($data, 'address.postal_code', '');
        $__data['address'] = Arr::get($data, 'address.address_line1', '').','.Arr::get($data, 'address.address_line2', '');

        return $__data;
    }

    public function createRoomConfig($hotel_id)
    {
        $IntegrationsActive = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)
            ->where('int_id', 8)
            ->where('state', 1)
            ->first();
        $this->pms_hotel_id = $IntegrationsActive->pms_hotel_id;
        $this->config = $IntegrationsActive->config;
        $this->CheckToken();
        if (! $IntegrationsActive) {
            return response()->json(['status' => false], 404);
        }

        $data = $this->getRooms($IntegrationsActive->pms_hotel_id, $IntegrationsActive->config['access_token'], $IntegrationsActive->config['url']);
        foreach ($data as $key => $room) {
            $room_data = $this->getRoom($hotel_id, 1, $room['number']);
            $room_integration = [
                'room_id' => $room_data['room_id'],
                'integration_room_id' => $room['id'],
                'hotel_id' => $hotel_id,
            ];

            $_room = IntegrationsRoomStayntouch::where('room_id', $room_data['room_id'])->where('hotel_id', $hotel_id)->first();
            if (! $_room) {
                IntegrationsRoomStayntouch::create($room_integration);
            }
        }

        return response()->json($data);
    }

    private function getRooms($pms_hotel_id, $token, $url)
    {
        try {
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $url."rooms?hotel_id=$pms_hotel_id&per_page=100",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'api-version: 2.0',
                    'Authorization: Bearer '.$token,
                ],
            ]);

            $response = curl_exec($curl);

            $err = curl_error($curl);

            curl_close($curl);
            if ($err) {
                return null;
            }
            // \Log::info($response);
            return Arr::get(json_decode($response, true), 'results', []);
        } catch (\Exception $e) {
            \Log::error('Error in StayNTouchController getRooms');
            \Log::error($e->getMessage());

            return null;
        }
    }
}
