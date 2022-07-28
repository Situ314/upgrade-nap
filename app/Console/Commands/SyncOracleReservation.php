<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncOracleReservation extends Command
{
    private $hotel_id;

    private $room_id;

    private $skip;

    private $add_0;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:oracle_reservation {hotel_id} {room_id=0} {skip=0} {add_0=false}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Oracle Reservation';

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
        if ($this->hotel_id == null) {
            return 'hotel_id required';
        }
        $this->room_id = $this->argument('room_id');
        $this->skip = $this->argument('skip');
        $this->add_0 = $this->argument('add_0');
        $this->skip_after = false;

        //find integration active
        $integration_active = \App\Models\IntegrationsActive::where('hotel_id', $this->hotel_id)
            ->where('int_id', 5)
            ->where('state', 1)
            ->first();

        $location = null;
        // execute Job for 1 location
        if ($this->room_id != 0) {
            // find hotel room
            $hotel_room = \App\Models\HotelRoom::where('hotel_id', $this->hotel_id)->where('room_id', $this->room_id)->first();
            // validate if room str lenght is > 0 and rule add_0 is set
            $location = ($this->add_0 == true && strlen($hotel_room->location) > 3) ? $location = '0'.$hotel_room->location : $location = $hotel_room->location;
            // call Job SyncOracleReservation
            dispatch((new \App\Jobs\OperaReservation($this->hotel_id, $integration_active->created_by, 'SyncOracleReservation', [], $integration_active->config, $location, $integration_active->pms_hotel_id)));
        } else {
            // execute Job for 1 all locations in hotel
            $hotel_rooms = \App\Models\HotelRoom::where('hotel_id', $this->hotel_id)->where('is_common_area', 0)->where('active', 1)->orderBy('location', 'ASC')->get();
            foreach ($hotel_rooms as $key => $hotel_room) {
                // skip room
                if ($this->skip != 0 && $this->skip >= $key) {
                    continue;
                }
                // validate if room str lenght is > 0 and rule add_0 is set
                $location = ($this->add_0 == 'true' && strlen($hotel_room->location) > 3) ? $location = '0'.$hotel_room->location : $location = $hotel_room->location;
                dispatch((new \App\Jobs\OperaReservation($this->hotel_id, $integration_active->created_by, 'SyncOracleReservation', [], $integration_active->config, $location, $integration_active->pms_hotel_id)));
                if ($this->skip_after != false) {
                    if ($location > $skip_after) {
                        break;
                    }
                }
            }
        }
    }
}
