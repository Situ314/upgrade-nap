<?php

namespace App\Console\Commands;

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
use Illuminate\Console\Command;

class RegisterMonitoringLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'register:monitoring';

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
        $this->RegisterLogMaestro();
        $this->RegisterLogOpera();
    }

    public function RegisterLogOpera()
    {
        try {
            $Hotels = IntegrationsActive::select('hotel_id', 'pms_hotel_id')->where('int_id', '5')
                ->where('state', 1)
                ->get();

            $count = 0;
            foreach ($Hotels as $value) {
                $json = [
                    'Oracle_reservation' => $this->SearchLogsOpera('Oracle_reservation', $value->hotel_id, $value->pms_hotel_id),
                    'Oracle_profile' => $this->SearchLogsOpera('Oracle_profile', $value->hotel_id, $value->pms_hotel_id),
                    'Oracle_housekeeping' => $this->SearchLogsOpera('Oracle_housekeeping', $value->hotel_id, $value->pms_hotel_id),
                ];

                $count =
                    $json['Oracle_reservation'] +
                    $json['Oracle_profile'] +
                    $json['Oracle_housekeeping'];

                $data = [
                    'Hotel_id' => $value->hotel_id,
                    'date' => date('Y-m-d'),
                    'time' => date('H:i:s'),
                    'total' => $count,
                    'detail_json' => $json,
                ];

                Monitoring::create($data);
            }
        } catch (Exception $e) {
            \Log::error($e);
        }
    }

    public function RegisterLogMaestro()
    {
        try {
            $Hotels = IntegrationsActive::select('hotel_id', 'pms_hotel_id')->where('int_id', '1')
                ->where('state', 1)
                ->get();

            $count = 0;
            foreach ($Hotels as $value) {
                $json = [
                    'ReservationList' => $this->SearchLogsMaestro('ReservationList', $value->hotel_id, $value->pms_hotel_id),
                    'CheckIn' => $this->SearchLogsMaestro('CheckIn', $value->hotel_id, $value->pms_hotel_id),
                    'CheckOut' => $this->SearchLogsMaestro('CheckOut', $value->hotel_id, $value->pms_hotel_id),
                    'OffMarket' => $this->SearchLogsMaestro('Offmarket', $value->hotel_id, $value->pms_hotel_id),
                    'HousekeepingStatus' => $this->SearchLogsMaestro('HousekeepingStatus', $value->hotel_id, $value->pms_hotel_id),
                ];

                $count =
                    $json['ReservationList'] +
                    $json['CheckIn'] +
                    $json['CheckOut'] +
                    $json['OffMarket'] +
                    $json['HousekeepingStatus'];

                $data = [
                    'Hotel_id' => $value->hotel_id,
                    'date' => date('Y-m-d'),
                    'time' => date('H:i:s'),
                    'total' => $count,
                    'detail_json' => $json,
                ];

                Monitoring::create($data);
            }
        } catch (Exception $e) {
            \Log::error($e);
        }
    }

    public function SearchLogsMaestro($switch, $hotel_id, $pms_hotel_id)
    {
        $time = date('H:i:s');
        $date = date('Y-m-d');

        try {
            $count_monitoring = Monitoring::where('Hotel_id', $hotel_id)->count();
            $hotel_monitoring = Monitoring::whereDate('date', $date)
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
                case 'ReservationList':
                    $count_data = ReservationList::where('HotelId', $pms_hotel_id);

                    if ($count_monitoring > 0) {
                        $count_data = $count_data
                            ->whereDate('created_on_Date', $date);
                    }

                    if ($time_range) {
                        $count_data = $count_data
                            ->whereTime('Created_on_Time', '<=', $time)
                            ->whereTime('Created_on_Time', '>=', $time_range);
                    }

                    $count_data = $count_data->count();
                    break;
                case 'CheckIn':
                    $count_data = CheckIn::where('HotelId', $pms_hotel_id);
                    if ($count_monitoring > 0) {
                        $count_data = $count_data
                            ->whereDate('created_on_Date', $date);
                    }
                    if ($time_range) {
                        $count_data = $count_data
                            ->whereTime('Created_on_Time', '<=', $time)
                            ->whereTime('Created_on_Time', '>=', $time_range);
                    }

                    $count_data = $count_data->count();
                    break;
                case 'CheckOut':
                    $count_data = CheckOut::where('HotelId', $pms_hotel_id);
                    if ($count_monitoring > 0) {
                        $count_data = $count_data
                            ->whereDate('created_on_Date', $date);
                    }
                    if ($time_range) {
                        $count_data = $count_data
                            ->whereTime('Created_on_Time', '<=', $time)
                            ->whereTime('Created_on_Time', '>=', $time_range);
                    }

                    $count_data = $count_data->count();
                    break;
                case 'Offmarket':
                    $count_data = Offmarket::where('HotelId', $pms_hotel_id);
                    if ($count_monitoring > 0) {
                        $count_data = $count_data
                            ->whereDate('created_on_Date', $date);
                    }
                    if ($time_range) {
                        $count_data = $count_data
                            ->whereTime('Created_on_Time', '<=', $time)
                            ->whereTime('Created_on_Time', '>=', $time_range);
                    }

                    $count_data = $count_data->count();
                    break;
                case 'HousekeepingStatus':
                    $count_data = HousekeepingStatus::where('HotelId', $pms_hotel_id);
                    if ($count_monitoring > 0) {
                        $count_data = $count_data
                            ->whereDate('created_on_Date', $date);
                    }
                    if ($time_range) {
                        $count_data = $count_data
                            ->whereTime('Created_on_Time', '<=', $time)
                            ->whereTime('Created_on_Time', '>=', $time_range);
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

    public function SearchLogsOpera($switch, $hotel_id, $pms_hotel_id)
    {
        $time = date('H:i:s');
        $date = date('Y-m-d');

        try {
            $count_monitoring = Monitoring::where('Hotel_id', $hotel_id)->count();
            $hotel_monitoring = Monitoring::whereDate('date', $date)
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
                    $count_data = OracleHousekeeping::where('resortId', $pms_hotel_id);
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
