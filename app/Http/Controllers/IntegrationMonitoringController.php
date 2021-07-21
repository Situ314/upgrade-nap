<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Integrations;
use App\Models\IntegrationsActive;
use App\Models\Log\CheckIn;
use App\Models\Log\CheckOut;
use App\Models\Log\HousekeepingStatus;
use App\Models\Log\Monitoring;
use App\Models\Log\Offmarket;
use App\Models\Log\OracleHousekeeping;
use App\Models\Log\OracleProfile;
use App\Models\Log\OracleReservation;
use App\Models\Log\ReservationList;
use App\Models\Log\SMSHousekeeping;
use App\Models\Log\SMSReservation;
use Illuminate\Http\Request;

class IntegrationMonitoringController extends Controller
{
    public function getHotel()
    {
        $integrations = IntegrationsActive::selectRaw('hotel_name,integrations_active.hotel_id,integrations_active.state,integrations.title,int_id,pms_hotel_id,'. "'cdelaossa@mynuvola.com' email")
            ->join('hotels', 'hotels.hotel_id', '=', 'integrations_active.hotel_id')
            ->join('integrations', 'integrations.id', '=', 'integrations_active.int_id')
            ->where('integrations_active.state', 1)->whereIn('int_id', [1, 5])->get();

        return $integrations;
    }

    public function getStats(Request $request)
    {
        if (!$request->has('hotel_id')) {
            return response()->json(['error' => 'hotel_id not provided'], 401);
        }
        $hotel_id = $request->hotel_id;
        $date = date('Y-m-d');
        if ($request->has('date')) {
            $date = $request->date;
        }
        $response = [];
        $integration = IntegrationsActive::where('hotel_id', $hotel_id)->first();
        if ($integration) {
            switch ($integration->int_id) {
                case 1:
                    $response['logs'] = $this->getStatsMaestro($hotel_id, $date);
                    break;
                case 5:
                    $response['logs'] = $this->getStatsOpera($hotel_id, $date);
                    break;
                default:
                    $response['error'] = 'this hotel not have an integration';
                    break;
            }
        }

        if (isset($response['logs'])) {
            $response['date'] = $date;
            $response['integration'] = Integrations::where('id', $integration->int_id)->first()->title;
        }

        return $response;
    }

    public function getStatsMaestro($hotel_id, $date = null)
    {
        if ($date == null) {
            $date = date('Y-m-d');
        }
        $register = Monitoring::where('hotel_id', $hotel_id)->whereDate('date', $date)->get();

        $time  = '';
        $total = 0;
        $array = [];
        foreach ($register as  $value) {
            $json = $value->detail_json;
            $time = $value->time;
            $array_data = [
                'reservation' => $json['ReservationList'],
                'check_in' => $json['CheckIn'],
                'check_out' => $json['CheckOut'],
                'off_market' => $json['OffMarket'],
                'housekeeping' => $json['HousekeepingStatus'],
                'total'       => $value->total
            ];
            $array[] = [
                'data' =>    $array_data,
                'time' =>    $time
            ];
            $total += $value->total;
        }
        $response = [
            'hotel_id' => $hotel_id,
            'date' => $date,
            'total' => $total,
            'data' => $array
        ];
        return $response;
    }

    public function getStatsOpera($hotel_id, $date = null)
    {
        if ($date == null) {
            $date = date('Y-m-d');
        }
        $register = Monitoring::where('hotel_id', $hotel_id)->whereDate('date', $date)->get();

        $time  = '';
        $total = 0;
        $array = [];
        foreach ($register as  $value) {
            $json = $value->detail_json;
            $time = $value->time;
            $array_data = [
                'reservation' => $json['Oracle_reservation'],
                'profile' => $json['Oracle_profile'],
                'housekeeping' => $json['Oracle_housekeeping'],
                'total'       => $value->total
            ];
            $array[] = [
                'data' =>    $array_data,
                'time' =>    $time
            ];
            $total += $value->total;
        }
        $response = [
            'hotel_id' => $hotel_id,
            'date' => $date,
            'total' => $total,
            'data' => $array
        ];
        return $response;
    }


    public function getTotal($hotel_id, $date = null)
    {
        if ($date == null) {
            $date = date('Y-m-d');
        }
        $register = Monitoring::where('Hotel_id', $hotel_id)->whereDate('date', $date)->get();
    
        $is_data = false;
        foreach($register as $r){
            
            $is_data = $r ? true : false;
            break;
        }
        if(!$is_data){
            return response()->json([
                'reservation'  => 0,
                'profile'      => 0,
                'housekeeping' => 0,
                'total'        => 0
            ]);
        }else{
            $first = $register[0];
            if ($first->int_id == 5) {
                $array = [
                    'reservation'  => 0,
                    'profile'      => 0,
                    'housekeeping' => 0,
                    'total'        => 0
                ];
                foreach ($register as  $value) {
                    $json = $value->detail_json;
                    $time = $value->time;
    
                    $array['reservation'] += $json['Oracle_reservation'];
                    $array['profile'] += $json['Oracle_profile'];
                    $array['housekeeping'] += $json['Oracle_housekeeping'];
                    $array['total'] += $value->total;
                }
            } elseif ($first->int_id == 1) {
                $array = [
                    'reservation'  => 0,
                    'check_in'      => 0,
                    'check_out' => 0,
                    'off_market' => 0,
                    'housekeeping' => 0,
                    'total'        => 0
                ];
                foreach ($register as  $value) {
                    $json = $value->detail_json;
                    $time = $value->time;
    
                    $array['reservation'] += $json['ReservationList'];
                    $array['check_in'] += $json['CheckIn'];
                    $array['check_out'] += $json['CheckOut'];
                    $array['off_market'] += $json['OffMarket'];
                    $array['housekeeping'] += $json['HousekeepingStatus'];
                    $array['total'] += $value->total;
                }
            }
    
            return $array;
        }
        
        
    }

    public function test()
    {
        $integrations = IntegrationsActive::select(['hotel_id','pms_hotel_id'])->where('int_id', 15)->get();
        $oracle_housekeeping = SMSHousekeeping::select(\DB::raw('hotel_id,max(created_at) last_data'))->where('hotel_id', '!=', '')->groupBy('hotel_id')->get();
        $data = [];
        foreach ($oracle_housekeeping as $key => $value) {
            $data[$value['hotel_id']] = $value['last_data'];
        }
        $oracle_reservation = SMSReservation::select(\DB::raw('hotel_id,max(created_at) last_data'))->where('hotel_id', '!=', '')->groupBy('hotel_id')->get();
        $data2 = [];
        foreach ($oracle_reservation as $key => $value) {
            $data2[$value['hotel_id']] = $value['last_data'];
        }


        $last_data = [];
        foreach ($data2 as $key => $value) {
            $last_data[$key] = isset($data[$key]) ? (strtotime($value) > strtotime($data2[$key]) ? $value : $data2[$key]) : $value;
        }

        $hotel_data = [];
        foreach ($integrations as $key => $value) {
            if(isset($last_data[$value->hotel_id])){
                $hotel = Hotel::find($value->hotel_id);
                $hotel_data[] = [
                    'hotel_id' => $hotel->hotel_id,
                    'name' => $hotel->hotel_name,
                    'last_data' => $last_data[$value->hotel_id]
                ];
            }
        }

        return response()->json($hotel_data);
    }
}
