<?php

namespace App\Console\Commands;

use \App\Models\Log\Monitoring;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use \App\Models\Hotel;
use \App\Models\IntegrationsActive;

class SendReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitoring:sendreport';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

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
        $this->SendEmail();
    }

    public function SendEmail()
    {
        $logs = [];
        $date = date('Y-m-d');
        $Hotels = IntegrationsActive::select('hotel_id', 'pms_hotel_id')->where('int_id', 1)->where('state', 1)->get();

        foreach ($Hotels as $value) {
            $hotel = Hotel::find($value->hotel_id);
            $logs[$hotel->hotel_name] = $this->SearchLogs($value->hotel_id, $date);
        }

        try {
            $emails = [
                // 'jsanchez@mynuvola.co',
                'support@mynuvola.com',
                // 'fidel@mynuvola.com',
                // 'asanchez@mynuvola.com',
                // 'cdelaossa@mynuvola.com',
                // 'customersuccess@mynuvola.com'
            ];
            $parameters = [
                'QuantityLogs'  => $logs,
                "date"          => $date
            ];

            Mail::send('emails.EmailMaestroPMSLog_DailyReport', $parameters, function ($m) use ($emails) {
                $m->from('integrations@api.mynuvola.net', 'Nuvola integrations');
                $m->to($emails);
                $m->subject('Daily Reports | Maestro PMS Logs');
            });
        } catch (Exception $e) {
            \Log::error("Error en SendEmail");
            \log::error("$e");
        }
    }

    public function SearchLogs($hotel_id, $date)
    {
        try {
            $reservationlist    = 0;
            $checkin            = 0;
            $checkout           = 0;
            $offmarket          = 0;
            $housekeeping       = 0;
            
            $hotel_monitoring = Monitoring::whereDate('date', $date)->where('Hotel_id', $hotel_id)->get();

            foreach ($hotel_monitoring as $value) {
                $reservationlist    += $value->detail_json['ReservationList'];
                $checkin            += $value->detail_json['CheckIn'];
                $checkout           += $value->detail_json['CheckOut'];
                $offmarket          += $value->detail_json['OffMarket'];
                $housekeeping       += $value->detail_json['HousekeepingStatus'];
            }

            $data = [
                'ReservationList'     => $reservationlist,
                'CheckIn'             => $checkin,
                'CheckOut'            => $checkout,
                'OffMarket'           => $offmarket,
                'HousekeepingStatus'  => $housekeeping,
                'Total'               => $housekeeping + $reservationlist + $checkin + $checkout + $offmarket
            ];

            return $data;
        } catch (Exception $e) {
            \Log::error("Error en SearchLogs");
            \Log::error("$e");
            return null;
        }
    }

    
}
