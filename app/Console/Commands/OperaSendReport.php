<?php

namespace App\Console\Commands;

use \App\Models\Log\Monitoring;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use \App\Models\Hotel;
use \App\Models\IntegrationsActive;

class OperaSendReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitoring:operasendreport';

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

    public function SearchLogs($hotel_id, $date)
    {
        try {
            $oracle_reservation   = 0;
            $oracle_profile       = 0;
            $oracle_housekeeping  = 0;

            $hotel_monitoring = Monitoring::whereDate('date', $date)
                ->where('Hotel_id', $hotel_id)
                ->get();

            foreach ($hotel_monitoring as $value) {
                $oracle_reservation   += $value->detail_json['Oracle_reservation'];
                $oracle_profile       += $value->detail_json['Oracle_profile'];
                $oracle_housekeeping  += $value->detail_json['Oracle_housekeeping'];
            }

            $data = [
                'Reservation'     => $oracle_reservation,
                'Profile'         => $oracle_profile,
                'Housekeeping'    => $oracle_housekeeping,
                'Total'           => $oracle_housekeeping + $oracle_reservation + $oracle_profile
            ];
            return $data;
        } catch (Exception $e) {
            \Log::error($e);
            return null;
        }
    }

    public function SendEmail()
    {
        $QuantityLogs = [];
        $date = date('Y-m-d');
        $Hotels = IntegrationsActive::select('hotel_id', 'pms_hotel_id')
            ->where('int_id', 5)
            ->where('state', 1)
            ->get();

        foreach ($Hotels as $value) {
            $hotel_name = Hotel::find($value->hotel_id);
            $QuantityLogs[$hotel_name->hotel_name] =  $this->SearchLogs($value->hotel_id, $date);
        }

        try {
            $emails = [
                'jacevedo@mynuvola.co',
                // 'jsanchez@mynuvola.co',            
                // 'cdelaossa@mynuvola.com',
                // 'customersuccess@mynuvola.com'
            ];

            Mail::send('emails.EmailOperaPMSLog_DailyReport', ['QuantityLogs' => $QuantityLogs, "date" => $date], function ($m) use ($emails) {
                $m->from('integrations@api.mynuvola.net', 'Nuvola integrations');
                $m->to($emails);
                $m->subject('Daily Reports | Opera PMS Logs');
            });
        } catch (Exception $e) {
            \log::error($e);
        }
    }
}
