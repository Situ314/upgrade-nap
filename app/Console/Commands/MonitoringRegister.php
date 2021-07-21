<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Log\ReservationList;
use App\Models\Log\HousekeepingStatus;
use App\Models\Log\Offmarket;
use App\Models\Log\CheckIn;
use \App\Models\Log\CheckOut;
use \App\Models\Log\Monitoring;
use \App\Models\IntegrationsActive;
use App\Models\Log\OracleHousekeeping;
use App\Models\Log\OracleProfile;
use App\Models\Log\OracleReservation;

class MonitoringRegister extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitoring:register';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register Data Monitoring';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /**
         * Registrar en la BD la cantidad de registro ingresados por peridodos de 
         * tiempo establecidos en el llamdo del cron kernel.php
         * 
         * @return void
         */
        $this->RegisterLogMaestro();
        /**
         * Registrar en la BD la cantidad de registro ingresados por peridodos de 
         * tiempo establecidos en el llamdo del cron kernel.php
         * 
         * @return void
         */
        $this->RegisterLogOpera();
    }

    public function RegisterLogMaestro()
    {
        try {
            // Buscar todos los hoteles con integracion activa
            $Hotels = IntegrationsActive::select('hotel_id', 'pms_hotel_id')->where('int_id', 1)->where('state', 1)->get();
            // Capturar cantidad de registro del hotel en cada tabla de log
            foreach ($Hotels as $hotel) {                
                $detail = [
                    'ReservationList'    => $this->SearchLogsMaestro('ReservationList',     $hotel->hotel_id, $hotel->pms_hotel_id),
                    'CheckIn'            => $this->SearchLogsMaestro('CheckIn',             $hotel->hotel_id, $hotel->pms_hotel_id),
                    'CheckOut'           => $this->SearchLogsMaestro('CheckOut',            $hotel->hotel_id, $hotel->pms_hotel_id),
                    'OffMarket'          => $this->SearchLogsMaestro('Offmarket',           $hotel->hotel_id, $hotel->pms_hotel_id),
                    'HousekeepingStatus' => $this->SearchLogsMaestro('HousekeepingStatus',  $hotel->hotel_id, $hotel->pms_hotel_id),
                ];
                // Realizar calculo
                $count = $detail['ReservationList'] + $detail['CheckIn'] + $detail['CheckOut'] + $detail['OffMarket'] + $detail['HousekeepingStatus'];
                // Crear registros
                Monitoring::create([
                    'Hotel_id'      => $hotel->hotel_id,
                    'total'         => $count,
                    'detail_json'   => $detail,                    
                    'date'          => date('Y-m-d'),
                    'time'          => date('H:i:s'),
                    'int_id'        => 1
                ]);
            }
        } catch (Exception $e) {
            \Log::error("Error en RegisterLogMaestro");
            \Log::error($e);
        }
    }

    public function RegisterLogOpera()
    {
        try {
            $Hotels = IntegrationsActive::select('hotel_id', 'pms_hotel_id')->where('int_id', '5')
                ->where('state', 1)
                ->get();

            $count = 0;
            foreach ($Hotels as $value) {
                $json   = array(
                    'Oracle_reservation'    => $this->SearchLogsOpera('Oracle_reservation', $value->hotel_id, $value->pms_hotel_id),
                    'Oracle_profile'        => $this->SearchLogsOpera('Oracle_profile', $value->hotel_id, $value->pms_hotel_id),
                    'Oracle_housekeeping'   => $this->SearchLogsOpera('Oracle_housekeeping', $value->hotel_id, $value->pms_hotel_id)
                );

                $count =
                    $json['Oracle_reservation'] +
                    $json['Oracle_profile'] +
                    $json['Oracle_housekeeping'];

                $data = [
                    'Hotel_id'      => $value->hotel_id,
                    'date'          => date('Y-m-d'),
                    'time'          => date('H:i:s'),
                    'total'         => $count,
                    'detail_json'   => $json,
                    'int_id'        => 5
                ];

                Monitoring::create($data);
            }
        } catch (Exception $e) {
            \Log::error($e);
        }
    }
    

    /**
     * Bucar registro de Maestro por cada tabla de los logs
     * 
     * @return int
     */
    public function SearchLogsMaestro($tableName, $hotel_id, $pms_hotel_id)
    {
        try {
            // Validar si ese hotel ya tine registro en el log,
            // De lo contrario se trabajara con todo los registros sin tener en cuenta
            // un rango de fechas
            $count_monitoring = Monitoring::where('int_id',1)->where('Hotel_id', $hotel_id)->count();
            // Buscar el ultimo registro en la tabla monitoring para el hotel
            $date = date('Y-m-d');
            $hotel_monitoring = Monitoring::where('int_id',1)->where('Hotel_id', $hotel_id)
                ->whereDate('date', $date)
                ->orderby('date', 'desc')
                ->orderby('time', 'desc')
                ->first();
            // delimitar registros del dia por
            $start_time = null;
            if ($hotel_monitoring) {
                $start_time = $hotel_monitoring->time;
            }
            // Buscar los registro en la tabla espesificada
            $total = 0;
            $end_time = date('H:i:s');
            switch ($tableName) {
                case 'ReservationList': 
                    $total = ReservationList::where('HotelId', $pms_hotel_id);
                    break;
                case 'CheckIn': 
                    $total = CheckIn::where('HotelId', $pms_hotel_id);
                    break;
                case 'CheckOut': 
                    $total = CheckOut::where('HotelId',  $pms_hotel_id);
                    break;
                case 'Offmarket': 
                    $total = Offmarket::where('HotelId',  $pms_hotel_id);
                    break;
                case 'HousekeepingStatus': 
                    $total = HousekeepingStatus::where('HotelId',  $pms_hotel_id);
                    break;
            }
            // Agregar filtro si se encontraron registros y trabajar con todos los registro o solo con los de la facha
            // Esto para cuando se inicie la integracion por primera vez
            if ($count_monitoring > 0) $total = $total->whereDate('created_on_Date', $date);
            // Agregar filtro de rango de fechas
            if ($start_time) $total = $total->whereTime('Created_on_Time', '>=', $start_time)->whereTime('Created_on_Time', '<=', $end_time);
            return $total->count();
        } catch (\Exception $e) {
            \Log::error($e);
            return null;
        }
    }


    public function SearchLogsOpera($switch, $hotel_id, $pms_hotel_id)
    {       
        try {
            $time = date('H:i:s');
            $date = date('Y-m-d');
            
            $count_monitoring = Monitoring::where('int_id',5)->where('Hotel_id', $hotel_id)->count();

            $hotel_monitoring = Monitoring::where('int_id',5)->whereDate('date', $date)
                ->where('Hotel_id', $hotel_id)
                ->orderby('date', 'desc')
                ->orderby('time', 'desc')
                ->first();
            $time_range = null;

            if ($hotel_monitoring) {
                $time_range = $hotel_monitoring->time;
            }

            $count_data = 0;
            switch ($switch) {
                case 'Oracle_reservation':
                    $count_data = OracleReservation::where('resortId', $pms_hotel_id);

                    if ($count_monitoring > 0) {
                        $count_data = $count_data
                            ->whereDate('created_at', $date);
                    }

                    if ($time_range) {
                        $count_data = $count_data
                            ->whereTime('created_at', '<=', $time)
                            ->whereTime('created_at', '>=', $time_range);
                    }


                    $count_data = $count_data->count();
                    break;
                case 'Oracle_profile':
                    $count_data = OracleProfile::where('resortId', $pms_hotel_id);
                    if ($count_monitoring > 0) {
                        $count_data = $count_data
                            ->whereDate('created_at', $date);
                    }
                    if ($time_range) {
                        $count_data = $count_data
                            ->whereTime('created_at', '<=', $time)
                            ->whereTime('created_at', '>=', $time_range);
                    }

                    $count_data = $count_data->count();
                    break;
                case 'Oracle_housekeeping':
                    $count_data = OracleHousekeeping::where('resortId',  $pms_hotel_id);
                    if ($count_monitoring > 0) {
                        $count_data = $count_data
                            ->whereDate('created_at', $date);
                    }
                    if ($time_range) {
                        $count_data = $count_data
                            ->whereTime('created_at', '<=', $time)
                            ->whereTime('created_at', '>=', $time_range);
                    }
                    $count_data = $count_data->count();
                    break;
            }

            return $count_data;
        } catch (\Exception $e) {
            \Log::error($e);
            return null;
        }
    }
}
