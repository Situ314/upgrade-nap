<?php

namespace App\Console\Commands;

use App\Models\GuestCheckinDetails;
use App\Models\GuestRegistration;
use App\Models\Hotel;
use App\Models\IntegrationsActive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Spatie\ArrayToXml\ArrayToXml;



class CancelReservation extends Command
{
    private $url;
    private $pms_hotel_id;
    private $agreed_upon_key;
    private $integrations;
    private $integration;
    private $hotel_id;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cancel:reservation';

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
        try {
            
            $this->integrations = IntegrationsActive::where('int_id', '1')->where('state', 1)->get();

            foreach ($this->integrations as $value) {
                $this->integration  = $value;
                if (array_has($this->integration->config, 'url')) {
                    $this->url              = $this->integration->config['url'];
                    $this->hotel_id = $this->integration->hotel_id;
                    $hotel = Hotel::findOrFail($this->hotel_id);

                    $this->pms_hotel_id     = $this->integration->config['hotel_id'];
                    $this->agreed_upon_key  = $this->integration->config['agreed_upon_key'];
                    if (!isset($this->integration->config['sno_sync'])) {
                        $config = $this->integration->config;
                        $config['sno_sync'] = 0;
                        $this->integration->config = $config;
                    }
                    $sno_sync = $this->integration->config['sno_sync'];
                    $this->pms_hotel_id     = $this->integration->pms_hotel_id;
                    date_default_timezone_set($hotel->time_zone);

                    $dateYesterday = date('Y-m-d');

                    $reservations = GuestCheckinDetails::where('hotel_id', $this->hotel_id)
                        ->whereDate('check_in', '<=', $dateYesterday)
                        ->whereIn('reservation_status', [0, 1])
                        ->where('reservation_number', '!=', '')
                        ->where('sno', '>=', $sno_sync)
                        ->orderBy('sno', 'DESC')
                        ->limit(20)
                        ->get();

                    foreach ($reservations as  $key => $reservation) {
                        if ($key == 0) {
                            $sno_sync = $reservation->sno;
                        }
                        $guest = GuestRegistration::select('lastname')->where('guest_id', $reservation->guest_id)->where('hotel_id', $this->hotel_id)->first();
                        if ($guest) {
                            $this->GetReservationStatus($reservation->reservation_number, $guest->lastname);
                        }
                    }
                    $config = $this->integration->config;
                    $config['sno_sync'] = $sno_sync;
                    $this->integration->config = $config;
                    $this->integration->save();
                }
            }
            date_default_timezone_set('UTC');
        } catch (Exception $e) {
            \Log::error('Error-cron-checkIn \n' . $e);
        }
    }


    public function makePasswordHash()
    {
        $salt = $this->getSaltToPMS($this->url, $this->pms_hotel_id);
        $PasswordHash = hash('sha256', $this->agreed_upon_key . $salt);
        return $PasswordHash;
    }

    public function getSaltToPMS($url, $pms_hotel_id)
    {
        $xml =
            '<?xml version="1.0" encoding="utf-8"?>' .
            '<Request>' .
            '<Version>1.0</Version>' .
            '<HotelId>' . $pms_hotel_id . '</HotelId>' .
            '<GetSalt/>' .
            '</Request>';
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL             => $url,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_ENCODING        => "",
            CURLOPT_MAXREDIRS       => 10,
            // CURLOPT_TIMEOUT         => 6,
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST   => "POST",
            CURLOPT_POSTFIELDS      => $xml,
            CURLOPT_SSL_VERIFYPEER  => 0,
            CURLOPT_SSL_VERIFYHOST  => 0,
            CURLOPT_HTTPHEADER      => ["Content-Type: application/xml", "cache-control: no-cache"]
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            \Log::error($err);
            return $err;
        } else {
            $xml = str_replace('<?xml version="1.0" encoding="UTF-8"?>', "", $response);
            $xml        = simplexml_load_string($xml);
            $str_json   = json_encode($xml);
            $json       = json_decode($str_json);
            return $json->Salt;
        }

        return null;
    }

    public function BuildRequestSync($ReservationNumber, $last_name, $pms_hotel_id, $salt)
    {
        $xml = [
            'Version'         => '1.0',
            'HotelId'         => $pms_hotel_id,
            'PasswordHash'    => $salt,
            'Action'          => 'ReservationInquiry',
            'RequestData'     => [
                'ReservationNumber'      => $ReservationNumber,
                'ReservationNumberKey'   => '',
                'LastName'               => $last_name
            ]
        ];
        return ArrayToXml::convert($xml, 'Request');
    }


    public function SendRequestSync($xml, $url)
    {
        if (!empty($url)) {
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS       => 10,
                // CURLOPT_TIMEOUT         => 10,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $xml,
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/xml",
                ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if ($err) {
                \Log::error($err);
                return $err;
            } else {
                return $response;
            }
        }
    }


    public function GetReservationStatus($ReservationNumber, $last_name)
    {
        try {
            $salt = $this->makePasswordHash();
            if (!empty($salt)) {
                $xml_request  = $this->BuildRequestSync($ReservationNumber, $last_name, $this->pms_hotel_id, $salt);
                $xml_response = $this->SendRequestSync($xml_request, $this->url);
                $xml          = str_replace('<?xml version="1.0" encoding="UTF-8"?>', "", $xml_response);
                $xml          = str_replace('Response', "ReservationList", $xml_response);
                $xml          = simplexml_load_string($xml);
                $str_json     = json_encode($xml);
                // \Log::info('Reservation Inquiry');
                // \Log::info($str_json);
                $json         = json_decode($str_json);
                if ($json->Status != 'failure') {
                    new \App\Jobs\MaestroPms($this->integration, $json, false, null, true);
                }
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
