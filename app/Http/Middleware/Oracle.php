<?php

namespace App\Http\Middleware;

use Illuminate\Support\Arr;
use Closure;
use Spatie\ArrayToXml\ArrayToXml;

class Oracle
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
        $response = $request->getContent();
        $xmlString = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
        $xml = simplexml_load_string($xmlString);
        $str_json = json_encode($xml);

        // \Log::info("OPERA XML");
        // \Log::info($str_json);

        $json = json_decode($str_json, true);
        $Username = Arr::get($json, 'Header.Security.UsernameToken.Username');
        $Password = Arr::get($json, 'Header.Security.UsernameToken.Password');
        // JESUS SANCHE - 2021-09-05
        // El attr MEssageID se agrega para darle seguimiento a los mensajes de HSK,
        // se agrega un nuevo campo den la tabla Oracle_housekeeping
        $MessageID = Arr::get($json, 'Header.MessageID');

        $pms_hotel_id = '';
        if (Arr::has($json, 'Body.NewProfileRequest.ResortId')) {
            $pms_hotel_id = Arr::get($json, 'Body.NewProfileRequest.ResortId');
        }

        if (Arr::has($json, 'Body.UpdateProfileRequest.ResortId')) {
            $pms_hotel_id = Arr::get($json, 'Body.UpdateProfileRequest.ResortId');
        }

        $keys = array_keys(Arr::get($json, 'Body', []));
        if (Arr::has($json, 'Body.'.$keys[0].'.GuestStatus.ResortId')) {
            $pms_hotel_id = Arr::get($json, 'Body.'.$keys[0].'.GuestStatus.ResortId');
        }

        if (Arr::has($json, 'Body.GuestStatusNotificationExtRequest.GuestStatus.resortId')) {
            $pms_hotel_id = Arr::get($json, 'Body.GuestStatusNotificationExtRequest.GuestStatus.resortId');
        }

        if (Arr::has($json, 'Body.RoomStatusUpdateBERequest.ResortId')) {
            $pms_hotel_id = Arr::get($json, 'Body.RoomStatusUpdateBERequest.ResortId');
        }

        if (Arr::has($json, 'Body.GuestStatusNotificationRequest.GuestStatus.resortId')) {
            $pms_hotel_id = Arr::get($json, 'Body.GuestStatusNotificationRequest.GuestStatus.resortId');
        }

        if (Arr::has($json, 'Body.QueueRoomBERequest.ResortId')) {
            $pms_hotel_id = Arr::get($json, 'Body.QueueRoomBERequest.ResortId');
        }

        try {
            $this->customWriteLog('opera', $pms_hotel_id, $response);
        } catch (\Throwable $th) {
            \Log::error('ERROR SAVING LOG...');
            \Log::error($th);
        }

        $IntegrationsActive = \App\Models\IntegrationsActive::where('pms_hotel_id', $pms_hotel_id)
            ->where('int_id', 5)
            ->where('state', 1)
            ->first();

        if ($IntegrationsActive) {
            $config = $IntegrationsActive->config;
            $__username = $config['username'];
            $__password = $config['password'];

            if ($config['url_send'] == '' && $config['url_sync'] == '') {
                $xml_response = ArrayToXml::convert($this->BuildXML(), 'soap:envelope');

                return response($xml_response, 200)->header('Content-Type', 'text/xml');
            }

            if ($__username == $Username && $__password == $Password) {
                $request->merge([
                    'staff_id' => $IntegrationsActive->created_by,
                    'hotel_id' => $IntegrationsActive->hotel_id,
                    'data' => $json,
                    'config' => $config,
                    'MessageID' => $MessageID,
                    'xml' => $xmlString,
                ]);

                return $next($request);
            }

            $xml_response = ArrayToXml::convert($this->BuildXML(), 'soap:envelope');

            return response($xml_response, 200)->header('Content-Type', 'text/xml');
        } else {
            $xml_response = ArrayToXml::convert($this->BuildXML(), 'soap:envelope');

            return response($xml_response, 200)->header('Content-Type', 'text/xml');
        }
    }

    public function BuildXML()
    {
        $error = [
            '_attributes' => [
                'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
                'xmlns:soap' => 'http://schemas.xmlsoap.org/soap/envelope/',
            ],
            'soap:Body' => [
                'soap:Fault' => [
                    'faultcode' => 401,
                    'faultstring' => 'Authentication failed: missing, malformed, 
                        or invalid credentials.',
                ],
            ],
        ];

        return $error;
    }

    public function customWriteLog($folder_name, $hotel_id, $text)
    {
        $path = "/logs/$folder_name";

        if (! \Storage::has($path)) {
            \Storage::makeDirectory($path, 0775, true);
        }

        if (! \Storage::has($path.'/'.$hotel_id)) {
            \Storage::makeDirectory($path.'/'.$hotel_id, 0775, true);
        }

        $day = date('Y_m_d');
        $file = "$path/$hotel_id/$day.log";
        $hour = date('H:i:s');
        $text = "[$hour]: $text";

        \Storage::append($file, $text);

        return true;
    }
}
