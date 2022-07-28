<?php

namespace App\Console\Commands;

use App\Models\IntegrationsActive;
use Illuminate\Console\Command;

class ResyncCron extends Command
{
    private $hotel_id;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'full:resync {hotel_id}';

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
        $this->hotel_id = $this->argument('hotel_id');
        $integrations_opera = IntegrationsActive::where('hotel_id', $this->hotel_id)->where('state', 1)->get();

        foreach ($integrations_opera as $value) {
            if ($value->int_id == 5) {
                // \Log::info(json_encode($value));
                $this->ResysncOpera($value);
            }

            if ($value->int_id == 1) {
                $this->ResysncMaestro($value);
            }
        }
    }

    public function ResysncOpera($IntegrationsActive)
    {
        $HotelRoom = \App\Models\HotelRoom::where('hotel_id', $this->hotel_id)->where('is_common_area', 0)->where('active', 1)->orderBy('location', 'ASC')->get();
        foreach ($HotelRoom as  $room) {
            $location = $room->location;
            if ($this->hotel_id == 289 && $location > 3000) {
                break;
            }
            dispatch((new \App\Jobs\Opera($this->hotel_id, $IntegrationsActive->created_by, 'SyncOracleHSK', [], $IntegrationsActive->config, $location)));
        }
    }

    public function ResysncMaestro($integration)
    {
        (new \App\Jobs\MaestroPms($integration, null, true, null));
        // \Log::info('Entra a maestro');
    }
}
