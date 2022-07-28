<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\MonitoringRegister::class,
        Commands\MonitoringMaestroSendmail::class,
        Commands\MonitoringOperaSendmail::class,
        Commands\SendReport::class,
        Commands\OperaSendReport::class,
        Commands\CancelReservation::class,
        Commands\ResyncCron::class,
        Commands\SyncOracleReservation::class,
        Commands\SyncSmsReservation::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // monitorear los datos por hora
        // $schedule->command('monitoring:register')->cron('*/15 * * * *')->timezone('UTC');
        // Enviar correos con posible integraciones que no estan enviando informaci¨®n
        $time = 59;
        // $schedule->command("monitoring:sendmailmaestro $time")->cron("*/$time * * * *")->timezone('UTC');
        $time = 59;
        // $schedule->command("monitoring:sendmailopera $time")->cron("*/$time * * * *")->timezone('UTC');
        // Reporte diario de las integraciones
        // $schedule->command('monitoring:sendreport')->dailyAt('23:50')->timezone('UTC');

        // $schedule->command('cancel:reservation')->cron("0 0 */1 * *")->timezone('UTC');
        // $schedule->command('monitoring:operasendreport')->dailyAt('18:59')->timezone('America/New_york');
        //Resyncs  Opera/Maestro
        $schedule->command('full:resync 243')->dailyAt('07:00')->timezone('America/Chicago');
        $schedule->command('full:resync 276')->dailyAt('07:00')->timezone('America/New_York');
        $schedule->command('full:resync 277')->dailyAt('07:00')->timezone('America/New_York');
        $schedule->command('full:resync 278')->dailyAt('07:00')->timezone('America/New_York');
        $schedule->command('full:resync 279')->dailyAt('07:00')->timezone('America/New_York');
        $schedule->command('full:resync 208')->dailyAt('07:00')->timezone('America/New_York');
        $schedule->command('full:resync 240')->dailyAt('07:00')->timezone('America/New_York');
        $schedule->command('full:resync 241')->dailyAt('07:00')->timezone('America/New_York');
        $schedule->command('full:resync 267')->dailyAt('07:00')->timezone('America/New_York');
        // $schedule->command('full:resync 281')->dailyAt('06:00')->timezone('Asia/Jakarta');
        // $schedule->command('full:resync 289')->dailyAt('05:00')->timezone('America/New_York');
        $schedule->command('full:resync 275')->dailyAt('06:00')->timezone('America/New_York');
        $schedule->command('full:resync 289')->dailyAt('03:00')->timezone('America/New_York');
        $schedule->command('full:resync 207')->dailyAt('07:00')->timezone('America/New_York');
        // $schedule->command('full:resync 275')->dailyAt('07:00')->timezone('America/New_York');
        // $schedule->command('full:resync 275')->cron('0 */1 * * *')->timezone('UTC');
        $schedule->command('monitoring:sendmail')->dailyAt('08:00')->timezone('America/New_York');
        $schedule->command('monitoring:sendmail')->dailyAt('12:00')->timezone('America/New_York');
        $schedule->command('monitoring:sendmail')->dailyAt('18:00')->timezone('America/New_York');

        // $schedule->command("monitoring:sendmailmaestro $time")->dailyAt('08:00')->timezone('America/New_York');
        // $schedule->command("monitoring:sendmailmaestro $time")->dailyAt('12:00')->timezone('America/New_York');
        // $schedule->command("monitoring:sendmailmaestro $time")->dailyAt('19:35')->timezone('America/New_York');
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
