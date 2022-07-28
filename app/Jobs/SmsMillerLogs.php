<?php

namespace App\Jobs;

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
                'guestnum' => array_get($r, 'guestresults.@attributes.guestnum', ''),
                'phone' => array_get($r, 'guestresults.phone', ''),
                'phone2' => array_get($r, 'guestresults.phone2', ''),
                'fax' => array_get($r, 'guestresults.fax', ''),
                'email' => array_get($r, 'guestresults.email', ''),
                'city' => array_get($r, 'guestresults.city', ''),
                'state' => array_get($r, 'guestresults.state', ''),
                'zip' => array_get($r, 'guestresults.zip', ''),
                'last' => array_get($r, 'guestresults.last', ''),
                'first' => array_get($r, 'guestresults.first', ''),
                'unum' => array_get($r, 'reservationresults.unum', ''),
                'resno' => array_get($r, '@attributes.resno', ''),
                'arrival' => array_get($r, 'reservationresults.arrival', ''),
                'depart' => array_get($r, 'reservationresults.depart', ''),
                'level' => array_get($r, 'reservationresults.level', ''),
                'primaryshare' => array_get($r, 'reservationresults.primaryshare', ''),
                'group' => array_get($r, 'reservationresults.group', ''),
                'utyp' => array_get($r, 'reservationresults.utyp', ''),
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
            'guestnum' => array_get($r, 'guestnum', ''),
            'resno' => array_get($r, '@attributes.resno'),
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
                'location' => array_get($r, '@attributes.unum'),
                'status' => array_get($r, 'Code'),
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
