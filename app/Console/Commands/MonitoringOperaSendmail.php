<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use League\Flysystem\Exception;
use Illuminate\Support\Facades\Mail;
use \App\Models\Log\Monitoring;
use \App\Models\Hotel;
use App\Models\IntegrationsActive;
use App\Models\Log\OracleHousekeeping;
use App\Models\Log\OracleReservation;
use App\Models\Log\SMSHousekeeping;
use App\Models\Log\SMSReservation;
use App\Models\Log\HousekeepingStatus;
use App\Models\Log\ReservationList;
use Illuminate\Support\Facades\Log;

class MonitoringOperaSendmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitoring:sendmail';
    // protected $signature = 'monitoring:sendmailopera {time}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set('UTC');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->SearchLog();
    }


    // private function SearchLogsControl($__time)
    // {
    //     try {
    //         $date   = date('Y-m-d');
    //         $end_time   = date('H:i:s');
    //         $start_time  = date('H:i:s', strtotime("-$__time minute", strtotime($end_time)));

    //         $Monitoring = Monitoring::where('int_id',5)->whereDate('date', $date)
    //             ->whereTime('time', '>=', $start_time)
    //             ->whereTime('time', '<=', $end_time)
    //             ->get();
                
    //         $Hotels_monitoring = $Monitoring->groupBy('Hotel_id')->map(function ($row) { 
    //             return $row->sum('total'); 
    //         });
            
    //         $hotels = [];
    //         $is_send = false;
    //         foreach ($Hotels_monitoring as $key => $value) {
    //             if ($value == 0) {
    //                 $hotel = Hotel::find($key);
    //                 // \Artisan::call('full:resync', ['' => $key]);
    //                 $hotels[] = $hotel;
    //                 $is_send = true;
    //             }
    //         }

    //         if ($is_send) {
    //             // $start_time  = date('H:i:s', strtotime('-2 hours', strtotime($start_time)));
    //             // $end_time    = date('H:i:s', strtotime('-2 hours', strtotime($end_time)));
    //             $this->SendEmail($start_time, $end_time, $hotels);
    //         }
    //     } catch (\Exception $e) {
    //         \Log::error("Error en SearchLogsControl");
    //         \Log::error("$e");
    //     }
    // }


    public function searchLogOpera()
    {
        $integrations = IntegrationsActive::select(['hotel_id','pms_hotel_id'])->where('int_id', 5)->get();
        $oracle_housekeeping = OracleHousekeeping::select(\DB::raw('resortId,max(created_at) last_data'))->where('resortId', '!=', '')->groupBy('resortId')->get();
        $data = [];
        foreach ($oracle_housekeeping as $key => $value) {
            $data[$value['resortId']] = $value['last_data'];
        }
        $oracle_reservation = OracleReservation::select(\DB::raw('resortId,max(created_at) last_data'))->where('resortId', '!=', '')->groupBy('resortId')->get();
        $data2 = [];
        foreach ($oracle_reservation as $key => $value) {
            $data2[$value['resortId']] = $value['last_data'];
        }


        $last_data = [];
        foreach ($data2 as $key => $value) {
            $last_data[$key] = isset($data[$key]) ? (strtotime($value) > strtotime($data2[$key]) ? $value : $data2[$key]) : $value;
        }

        $hotel_data = [];
        foreach ($integrations as $key => $value) {
            if(isset($last_data[$value->pms_hotel_id])){
                $hotel = Hotel::find($value->hotel_id);
                $hotel_data[] = [
                    'hotel_id' => $hotel->hotel_id,
                    'name' => $hotel->hotel_name,
                    'last_data' => date('Y-m-d H:i:s', strtotime("-5 hours", strtotime($last_data[$value->pms_hotel_id])))
                ];
            }
        }
        return $hotel_data;

    }

    public function searchLogMiller()
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
        return $hotel_data;
    }

    public function searchLogMaestro(){
        try {
            $integrations = IntegrationsActive::select(['hotel_id','pms_hotel_id'])->where('int_id', 1)->get();

            $oracle_housekeeping = HousekeepingStatus::select(\DB::raw("HotelId,max(CONCAT(Created_on_Date,' ',Created_on_Time)) last_data"))->where('HotelId', '!=', '')->groupBy('HotelId')->get();
            
            $data = [];
            foreach ($oracle_housekeeping as $key => $value) {
                $data[$value['HotelId']] = $value['last_data'];
            }
        
            $oracle_reservation = ReservationList::select(\DB::raw("HotelId,max(CONCAT(Created_on_Date,' ',Created_on_Time)) last_data"))->where('HotelId', '!=', '')->groupBy('HotelId')->get();
            $data2 = [];
            foreach ($oracle_reservation as $key => $value) {
                $data2[$value['HotelId']] = $value['last_data'];
            }
        
        
            $last_data = [];
            foreach ($data2 as $key => $value) {
                $last_data[$key] = isset($data[$key]) ? (strtotime($value) > strtotime($data2[$key]) ? $value : $data2[$key]) : $value;
            }
        
            $hotel_data = [];
            foreach ($integrations as $key => $value) {
                if(isset($last_data[$value->pms_hotel_id])){
                    $hotel = Hotel::find($value->hotel_id);
                    $hotel_data[] = [
                        'hotel_id' => $hotel->hotel_id,
                        'name' => $hotel->hotel_name,
                        'last_data' => $last_data[$value->pms_hotel_id]
                    ];
                }
            }
        
            return $hotel_data;
        } catch (Exception $e) {
            Log::error('searchlog dentro de maestro: ' . $e);
        }
       
    }

    public function searchLog()
    {
        try {

        $sms = $this->searchLogMiller();
        $opera = $this->searchLogOpera();
        $maestro = $this->searchLogMaestro();
        $data = array_merge($sms, $opera, $maestro);
        $this->SendEmail($data);
        } catch (Exception $e) {
            Log::error('searchlog: ' . $e);
        }

    }


    private function SendEmail($hotels)
    {
        try {
            $emails = [
                // 'fidel@mynuvola.com',
                'support@mynuvola.com',
                'asanchez@mynuvola.com',
                //  'cdelaossa@mynuvola.com',
                // 'customersuccess@mynuvola.com'
            ];

            $parameters = [
                'data_hotel'    => $hotels,
                'time1'      => 12131
            ];

            Mail::send('emails.EmailPMSLog', $parameters, function ($m) use ($emails) {
                $m->from('integrations@api.mynuvola.net', 'Nuvola integrations');
                $m->to($emails);
                $m->subject('PMS Logs Reports - Request Inquiry Report');
            });
        } catch (Exception $e) {
            Log::error('error al enviar correo command' . $e);
        }
    }
}
