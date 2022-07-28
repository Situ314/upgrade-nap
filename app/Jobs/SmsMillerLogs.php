<?php

namespace App\Jobs;

use Illuminate\Support\Arr;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SmsMillerLogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $hotel_id;

    private $staff_id;

    private $type;

    private $data;

    private $now;

    private $config;

    private $HotelHousekeepingConfig;

    private $SuitesRooms;

    private $ExtraStatus;

    private $title;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($hotel_id, $staff_id, $type, $data, $title)
    {
        // \Log::info(json_encode($data));
        $this->hotel_id = $hotel_id;
        $this->staff_id = $staff_id;
        $this->type = $type;
        $this->data = $data;
        $this->title = $title;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (method_exists($this, $this->type)) {
            $method = $this->type;
            $this->$method();
        }
    }

    public function reservation()
    {
        $reservation = $this->data;
        foreach ($reservation as  $r) {
            $data = [
                'guestnum' => Arr::get($r, 'guestresults.@attributes.guestnum', ''),
                'phone' => Arr::get($r, 'guestresults.phone', ''),
                'phone2' => Arr::get($r, 'guestresults.phone2', ''),
                'fax' => Arr::get($r, 'guestresults.fax', ''),
                'email' => Arr::get($r, 'guestresults.email', ''),
                'city' => Arr::get($r, 'guestresults.city', ''),
                'state' => Arr::get($r, 'guestresults.state', ''),
                'zip' => Arr::get($r, 'guestresults.zip', ''),
                'last' => Arr::get($r, 'guestresults.last', ''),
                'first' => Arr::get($r, 'guestresults.first', ''),
                'unum' => Arr::get($r, 'reservationresults.unum', ''),
                'resno' => Arr::get($r, '@attributes.resno', ''),
                'arrival' => Arr::get($r, 'reservationresults.arrival', ''),
                'depart' => Arr::get($r, 'reservationresults.depart', ''),
                'level' => Arr::get($r, 'reservationresults.level', ''),
                'primaryshare' => Arr::get($r, 'reservationresults.primaryshare', ''),
                'group' => Arr::get($r, 'reservationresults.group', ''),
                'utyp' => Arr::get($r, 'reservationresults.utyp', ''),
                'hotel_id' => $this->hotel_id,
                'type' => $this->title,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $data = array_map(function ($n) {
                return is_array($n) ? '' : $n;
            }, $data);
            \App\Models\Log\SMSReservation::create($data);
        }
    }

    public function checkOut()
    {
        $r = $this->data;

        $data = [
            'guestnum' => Arr::get($r, 'guestnum', ''),
            'resno' => Arr::get($r, '@attributes.resno'),
            'hotel_id' => $this->hotel_id,
            'type' => $this->title,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $data = array_map(function ($n) {
            return is_array($n) ? '' : $n;
        }, $data);
        \App\Models\Log\SMSReservation::create($data);
    }

    public function housekeeping()
    {
        $h = $this->data;

        foreach ($h as $r) {
            $data = [
                'location' => Arr::get($r, '@attributes.unum'),
                'status' => Arr::get($r, 'Code'),
                'hotel_id' => $this->hotel_id,
                'type' => $this->title,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $data = array_map(function ($n) {
                return is_array($n) ? '' : $n;
            }, $data);
            \App\Models\Log\SMSHousekeeping::create($data);
        }
    }
}
