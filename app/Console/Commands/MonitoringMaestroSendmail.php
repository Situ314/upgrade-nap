<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use League\Flysystem\Exception;
use Illuminate\Support\Facades\Mail;
use \App\Models\Log\Monitoring;
use \App\Models\Hotel;

class MonitoringMaestroSendmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitoring:sendmailmaestro {time}';

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
        $time = $this->argument('time');
        $this->SearchLogsControl($time);
    }


    private function SearchLogsControl($__time)
    {
        try {
            $date   = date('Y-m-d');
            $end_time   = date('H:i:s');
            $start_time  = date('H:i:s', strtotime("-$__time minutes", strtotime($end_time)));

            $Monitoring = Monitoring::where('int_id',1)->whereDate('date', $date)
                ->whereTime('time', '>=', $start_time)
                ->whereTime('time', '<=', $end_time)
                ->get();
                
            $Hotels_monitoring = $Monitoring->groupBy('Hotel_id')->map(function ($row) { 
                return $row->sum('total'); 
            });
            
            $hotels = [];
            $is_send = false;
            foreach ($Hotels_monitoring as $key => $value) {
                if ($value == 0) {
                    $hotel = Hotel::find($key);
                    // \Artisan::call('full:resync', ['' => $key]);
                    $hotels[] = $hotel;
                    $is_send = true;
                }
            }

            if ($is_send) {
                // $start_time  = date('H:i:s', strtotime('-4 hours', strtotime($start_time)));
                // $end_time    = date('H:i:s', strtotime('-4 hours', strtotime($end_time)));
                $this->SendEmail($start_time, $end_time, $hotels);
            }
        } catch (\Exception $e) {
            \Log::error("Error en SearchLogsControl");
            \Log::error("$e");
        }
    }

    private function SendEmail($time, $time2, $hotels)
    {
        try {
            $emails = [
                'ysalcedo@mynuvola.co',
                // 'jsanchez@mynuvola.co',
                // 'fidel@mynuvola.com',
                 'asanchez@mynuvola.com',
                // 'cdelaossa@mynuvola.com',
                // 'customersuccess@mynuvola.com'
            ];

            $parameters = [
                'hotels'    => $hotels,
                'time1'     => $time,
                'time2'     => $time2
            ];

            Mail::send('emails.EmailMaestroPMSLog', $parameters, function ($m) use ($emails) {
                $m->from('integrations@api.mynuvola.net', 'Nuvola integrations');
                $m->to($emails);
                $m->subject('Maestro PMS Logs Reports - Request Inquiry Report');
            });
        } catch (Exception $e) {
            \log::error($e);
        }
    }
}
