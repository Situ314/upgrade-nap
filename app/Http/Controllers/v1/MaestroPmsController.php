<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Spatie\ArrayToXml\ArrayToXml;
use \App\Models\IntegrationsActive;
use \App\Models\MaestroPmsSalt;
use \App\Jobs\MaestroPms;
use App\Jobs\MaestroPmsLog;
use GuzzleHttp\Client;


class MaestroPmsController extends Controller
{

    public function index(Request $request)
    {
        try {
            // if(strpos($request->getContent(), 'Offmarket')  !== false) {
            // \Log::info("XML MAESTRO" . json_encode(["xml_request" => $request->getContent()]));
            //     \Log::info('----------------------------------------');
            // }
            $text = $request->getContent();
            $text = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $text);
            $xml        = simplexml_load_string($text);
            $str_json   = json_encode($xml);
            $json       = json_decode($str_json);
            
            \Log::error('XML Maestro received: '.$json->HotelId);
            
            
            
            // if (isset($json->Action)) {           	
            // 	try {
            //         $text = $request->getContent();
            //         $client = new Client();
            //         $promise = $client->postAsync('https://cytluzl4uk.execute-api.us-east-1.amazonaws.com/', [
            //             'body' => $text,
            //             'headers'        => ['Content-Type' => 'application/xml']
            //         ])->then(function ($response) {
            //         });
            //         $promise->wait();
            //     } catch (\Exception $e) {
            //         \Log::error('Error Sending Async Promise TO DEV');
            //     }
            //     \Log::error('After try Sending Async Promise TO DEV');                
            // }

//            if ($json->HotelId == '1425' && isset($json->Action) && ($json->Action == 'HousekeepingStatus' || $json->Action == 'CheckIn' || $json->Action == 'CheckOut')) {
//if (($json->HotelId == '1425' || $json->HotelId == '1777') && isset($json->Action)) {
            
		if (isset($json->Action)) {
            
            	\Log::info('Before try Sending Async Promise to PROD from hotel: '.$json->HotelId);
            	try {
                    $text = $request->getContent();
                    $client = new Client();
                    $promise = $client->postAsync('https://c9ge7dpq3b.execute-api.us-east-1.amazonaws.com/', [
                        'body' => $text,
                        'headers'        => ['Content-Type' => 'application/xml']
                    ])->then(function ($response) {
                    });
                    $promise->wait();
                } catch (\Exception $e) {
                    \Log::error('Error Sending Async Promise to PROD from hotel: '.$json->HotelId);
                }
                \Log::error('After try Sending Async Promise to PROD from hotel: '.$json->HotelId);

                $xml_response = ArrayToXml::convert([
                    'HotelId'       => $json->HotelId,
                    'PasswordHash'  => $json->PasswordHash,
                    'Status'        => 'success',
                    'Message'       => ''
                ], 'Response');
                
                return response($xml_response, 200)->header('Content-Type', 'text/xml');
            }

            // if( $json->HotelId == '1803' || $json->HotelId == '2305' || $json->HotelId == '1802' || $json->HotelId == '1777') {
            //     \Log::info('------------------ Mensajes XML MAESTRO ----------------------');
            //     \Log::info($json->HotelId);
            //     \Log::info($request->getContent());
            //     \Log::info('----------------------------------------');
            // }

            $maestroIntegration = IntegrationsActive::where('pms_hotel_id', $json->HotelId)
                ->whereHas('integration', function ($q) {
                    $q->where('name', 'maestro_pms');
                })->first();

            if ($maestroIntegration) {
                $hotel_id           = $maestroIntegration->hotel_id;
                $user_id            = $maestroIntegration->created_by;
                $agreed_upon_key    = $maestroIntegration->config["agreed_upon_key"];

                $this->configTimeZone($hotel_id);

                if (isset($json->GetSalt)) {
                    $salt = $this->getSalt($hotel_id);
                    $xml_response = ArrayToXml::convert([
                        'HotelId'   => $json->HotelId,
                        'Salt'      => $salt
                    ], 'Response');


                    return response($xml_response, 200)->header('Content-Type', 'text/xml');
                }
                /**
                 * Si es un mensaje para procesar informacion,
                 * se le envia al Job para realizar esta tarea en segundo lano
                 */

                //$this->writeLog("MaestroPasswordHash", $hotel_id, "**: ".json_encode($maestroIntegration));

                $validatePasswordHash = $this->validatePasswordHash($hotel_id, $json->PasswordHash, $agreed_upon_key);
                if ($validatePasswordHash) {

                    $this->dispatch((new MaestroPms($maestroIntegration, $json)));

                    $xml_response = ArrayToXml::convert([
                        'HotelId'       => $json->HotelId,
                        'PasswordHash'  => $json->PasswordHash,
                        'Status'        => 'success',
                        'Message'       => ''
                    ], 'Response');


                    $this->dispatch((new MaestroPmsLog($json, $request->getContent())));

                    // \Log::info("RESPUESTA MAESTRO" . json_encode(["xml_response" => $xml_response]));
                    return response($xml_response, 200)->header('Content-Type', 'text/xml');
                }
            }

            $xml_response = ArrayToXml::convert([
                'HotelId'       => $json->HotelId,
                'PasswordHash'  => $json->PasswordHash,
                'Status'        => 'failure',
                'Message'       => 'Inactive integration'
            ], 'Response');

            if ($json->HotelId == '1803' || $json->HotelId == '2305' || $json->HotelId == '1802' || $json->HotelId == '1777') {
                \Log::info('------------------ Mensajes XML Respnse error inactive int ----------------------');
                \Log::info($xml_response);
                \Log::info('----------------------------------------');
            }

            return response($xml_response, 200)->header('Content-Type', 'text/xml');
        } catch (\Exception $e) {
            \Log::error('Error try XML MAESTRO');
            \Log::error($e);
            \Log::error('MAESTRO index');
            \Log::error($request->getContent());
            echo $e;
        }
    }

    public function getSalt($hotel_id)
    {
        $this->configTimeZone($hotel_id);
        $salt = $this->generateRandomString();
        //consultar si exitessa 
        $maestroPmsSalt =  MaestroPmsSalt::where('hotel_id', $hotel_id)->first();
        if (!$maestroPmsSalt) {
            $maestroPmsSalt = new MaestroPmsSalt(['hotel_id' => $hotel_id]);
        }
        $maestroPmsSalt->salt = $salt;
        $maestroPmsSalt->created_on = date('Y-m-d H:i:s');
        $maestroPmsSalt->save();

        return $salt;
    }

    public function generateRandomString()
    {
        $length = 10;
        $salt = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
        if (MaestroPmsSalt::where('salt', $salt)->first()) {
            $this->generateRandomString();
        }
        return $salt;
    }

    public function validatePasswordHash($hotel_id, $pass_hash, $agreed_upon_key)
    {
        // if ($hotel_id == 267) {
        //     return true;
        // }
        $MaestroPmsSalt = MaestroPmsSalt::where('hotel_id', $hotel_id)->first();

        if ($MaestroPmsSalt) {
            $current_date   = date('Y-m-d H:i:s');
            $created_on     = $MaestroPmsSalt->created_on;
            $diferencia     = strtotime($current_date) - strtotime($created_on);
            if ($diferencia > 112233445566778899) {
                return false;
            }
            if (strcmp(hash('sha256', $agreed_upon_key . $MaestroPmsSalt->salt), $pass_hash) == 0) {
                return true;
            }
        }
        return false;
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
            CURLOPT_TIMEOUT         => 6,
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
            \Log::error("--->");
            \Log::error($err);

            return $err;
        } else {
            \Log::error("--->");
            \Log::error($xml);
            $xml = str_replace('<?xml version="1.0" encoding="UTF-8"?>', "", $response);

            $xml        = simplexml_load_string($xml);
            $str_json   = json_encode($xml);
            $json       = json_decode($str_json);
            return $json->Salt;
        }

        return null;
    }

    public function makePasswordHash($url, $pms_hotel_id, $agreed_upon_key)
    {
        $salt = $this->getSaltToPMS($url, $pms_hotel_id);
        $PasswordHash = hash('sha256', $agreed_upon_key . $salt);
        return $PasswordHash;
    }

    public function GetDataSync($hotel_id, $room_id = null)
    {
        $integration = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)
            ->where('int_id', 1)
            ->first();

        if ($integration) {
            $this->dispatch((new MaestroPms($integration, null, true, $room_id)));
            return response()->json(['sync' => true], 200);
        }

        return response()->json(['sync' => false], 200);
    }
}
