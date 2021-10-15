<?php

namespace App\Helpers;

use GuzzleHttp\Client;

class OperaHelper
{
    public static function getProfileData($resort_id, $unique_id)
    {
        try {
            $integrationsActive = \App\Models\IntegrationsActive::where('pms_hotel_id', $resort_id)
                ->where('int_id', 5)
                ->where('state', 1)
                ->first();

            if ($integrationsActive) {
                $config     = $integrationsActive->config;
                $username   = $config['username'];
                $password   = $config['password'];
                $from       = $config['from_send'];
                $url        = $config['url_sync'];
                $date1      = date('Y-m-d\TH:i:s\Z');
                $date2      = date('Y-m-d\TH:i:s\Z', strtotime($date1 . ' +5 minutes'));

                $xml = "<soap:Envelope xmlns:soap='http://schemas.xmlsoap.org/soap/envelope/' xmlns:wsa='http://schemas.xmlsoap.org/ws/2004/08/addressing' xmlns:wsse='http://docs.oasisopen.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd' xmlns:wsu='http://docs.oasisopen.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd' xmlns:xsd='http://www.w3.org/2001/XMLSchema' xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' >
                    <soap:Header>
                        <wsse:Security xmlns:wsse='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd' xmlns:wsu='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd' >
                            <wsu:Timestamp wsu:Id='TS-1DB19FB15198FE10A2159249621088842'>
                                <wsu:Created>$date1</wsu:Created>
                                <wsu:Expires>$date2</wsu:Expires>
                            </wsu:Timestamp>
                            <wsse:UsernameToken wsu:Id='UsernameToken-1DB19FB15198FE10A2159249621088841'>
                                <wsse:Username>$username</wsse:Username>
                                <wsse:Password Type='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText'>$password</wsse:Password>
                                <wsu:Created>$date1</wsu:Created>
                            </wsse:UsernameToken>
                        </wsse:Security>
                        <wsa:Action>http://htng.org/PWS/2008B/SingleGuestItinerary#FetchProfile</wsa:Action>
                        <wsa:From>
                            <wsa:Address>urn:$from</wsa:Address>
                        </wsa:From>
                        <wsa:MessageID>urn:uuid:09a2b665-41d0-4654-b49d-86e7d437e371</wsa:MessageID>
                        <wsa:ReplyTo>
                            <wsa:Address>http://schemas.xmlsoap.org/ws/2004/08/addressing/role/anonymous</wsa:Address>
                        </wsa:ReplyTo>
                        <wsa:To>http://www.micros.com/HTNGActivity/</wsa:To>
                    </soap:Header>
                    <soap:Body>
                        <FetchProfileRequest xmlns='http://htng.org/PWS/2008B/SingleGuestItinerary/Name/Types'>
                            <ProfileID>$unique_id</ProfileID>
                            <ResortId>$resort_id</ResortId>
                        </FetchProfileRequest>
                    </soap:Body>
                </soap:Envelope>
                ";

                \Log::info("OperaHelper::getProfileData xml");
                \Log::info($xml);

                $header = [
                    "Content-Type: text/xml;charset=UTF-8",
                    "SOAPAction: http://htng.org/PWS/2008B/SingleGuestItinerary#FetchProfile"
                ];

                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER  => true,
                    CURLOPT_ENCODING        => "",
                    CURLOPT_MAXREDIRS       => 10,
                    CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST   => "POST",
                    CURLOPT_POSTFIELDS      => $xml,
                    CURLOPT_SSL_VERIFYPEER  => 0,
                    CURLOPT_SSL_VERIFYHOST  => 0,
                    CURLOPT_HTTPHEADER      => $header,
                ));
                $response = curl_exec($curl);
                $err = curl_error($curl);
                curl_close($curl);
                if ($err) return null;
                return $response;
            } else {
                \Log::error("OperaHelper::getProfileData no integrationsActive");
            }

            return null;
        } catch (\Exception $e) {
            \Log::error("Error in OperaHelper::getProfileData");
            \Log::error($e);
            return null;
        }
    }

    public static function sendXmlToAws($xml)
    {
        try {
            $url = 'https://zelg0qq99e.execute-api.us-east-1.amazonaws.com/Prod/profile';
            $options = [
                'headers' => ['Content-Type' => 'application/xml'],
                'body' => $xml,
            ];

            $client = new Client();
            $promise = $client->postAsync($url, $options)->then(function ($response) {
            });

            $promise->wait();
        } catch (\Exception $e) {
            \Log::error('Error in OperaHelper::sendXmlToAwl');
            \Log::error($e);
        }
    }
}
