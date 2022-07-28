<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncSmsReservation extends Command
{
    private $hotel_id;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:sms {hotel_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync sms miller';

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
        $this->hotel_id = (int) $this->argument('hotel_id');

        $controller = app()->make(\App\Http\Controllers\v2\SMSMillerController::class);
        app()->call([$controller, 'SyncReservation'], ['hotel_id' => $this->hotel_id]);
    }
}
