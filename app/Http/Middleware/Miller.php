<?php

namespace App\Http\Middleware;

use Closure;
use Spatie\ArrayToXml\ArrayToXml;

class Miller
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        $response   = $request->getContent();
        $xmlString  = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
        $xml        = simplexml_load_string($xmlString);
        \Log::info('MILLER XML');
        \Log::info($xmlString);
        $str_json   = json_encode($xml);
        // \Log::info($str_json);
        $json       = json_decode($str_json, true);
        $Username   = array_get($json, 'Header.Security.UsernameToken.Username');
        $Password   = array_get($json, 'Header.Security.UsernameToken.Password');
        $pms_hotel_id = array_get($json, 'Header.From.Address');
        $pos = strpos($pms_hotel_id, ':');
        if ($pos !== false) {
            $pms_hotel_id = substr($pms_hotel_id, $pos + 1);
        }

        $IntegrationsActive = \App\Models\IntegrationsActive::where('pms_hotel_id', $pms_hotel_id)
            ->where('int_id', 15)
            ->where('state', 1)
            ->first();
        if ($IntegrationsActive) {
            $config     = $IntegrationsActive->config;
            $__username = $config['username'];
            $__password = $config['password'];
            if ($__username == $Username && $__password == $Password) {
                if ($IntegrationsActive->hotel_id == 362) {
                    \Log::info('SMS_MILLER INNISBROOK');
                    \Log::info($response);
                }
                $request->merge([
                    "staff_id"  => $IntegrationsActive->created_by,
                    "hotel_id"  => $IntegrationsActive->hotel_id,
                    "data"      => $json,
                    "config"    => $config
                ]);
                return $next($request);
            } else {
                $xml_response = ArrayToXml::convert($this->BuildXML(), 'soap:envelope');
                return response($xml_response, 200)->header('Content-Type', 'text/xml');
            }
        } else {
            $xml_response = ArrayToXml::convert($this->BuildXML(), 'soap:envelope');
            return response($xml_response, 200)->header('Content-Type', 'text/xml');
        }
    }

    public function BuildXML()
    {
        $error = [
            "_attributes" => [
                "xmlns:xsi" => "http://www.w3.org/2001/XMLSchema-instance",
                "xmlns:xsd" => "http://www.w3.org/2001/XMLSchema",
                "xmlns:soap" => "http://schemas.xmlsoap.org/soap/envelope/"
            ],
            "soap:Body" => [
                "soap:Fault" => [
                    "faultcode" => 401,
                    "faultstring" => "Authentication failed: missing, malformed, 
                                or invalid credentials."
                ]
            ]
        ];
        return $error;
    }
}
