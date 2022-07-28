<?php

namespace App\Console\Commands;

use App\Models\Hotel;
use App\Models\Log\Monitoring;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use League\Flysystem\Exception;

class SendEmailMaestroPMSMonitoring extends Command
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
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $time = $this->argument('time');
        $this->SearchLogsControl();
    }

    private function SearchLogsControl($__time)
    {
        try {
            date_default_timezone_set('UTC');

            $date = date('Y-m-d');
            $time = date('H:i:s');

            $time2 = date('H:i:s', strtotime("-$__time hours", strtotime($time)));

            $Monitoring = Monitoring::whereDate('date', $date)
                    ->whereTime('time', '<=', $time)
                    ->whereTime('time', '>=', $time2)
                    ->get();

            $Hotels_monitoring = $Monitoring->groupBy('Hotel_id')->map(function ($row) {
                return $row->sum('total');
            });

            $hotels = [];
            $is_send = false;
            foreach ($Hotels_monitoring as $key => $value) {
                if ($value == 0) {
                    $hotel = Hotel::find($key);
                    $hotels[] = $hotel;
                    $is_send = true;
                }
            }

            if ($is_send) {
                $time = date('H:i:s', strtotime('-4 hours', strtotime($time)));
                $time2 = date('H:i:s', strtotime('-4 hours', strtotime($time2)));
                $this->SendEmail($time, $time2, $hotels);
            }
        } catch (\Exception $e) {
            \Log::error($e);
        }
    }

    private function SendEmail($time, $time2, $hotels)
    {
        try {
            $emails = [
                'jacevedo@mynuvola.co',
                'jsanchez@mynuvola.co',
                'csanchez@mynuvola.com',
                'cdelaossa@mynuvola.com',
                'customersuccess@mynuvola.com',
            ];
            Mail::send('emails.EmailMaestroPMSLog', [
                'hotels' => $hotels,
                'time1' => $time,
                'time2' => $time2,
            ], function ($m) use ($emails) {
                $m->from('integrations@api.mynuvola.net', 'Nuvola integrations');
                $m->to($emails);
                $m->subject('Maestro PMS Logs Reports - Request Inquiry Report');
            });
        } catch (Exception $e) {
            \log::error($e);
        }
    }
}
