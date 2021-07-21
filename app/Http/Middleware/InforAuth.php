<?php

namespace App\Http\Middleware;

use Closure;
use Spatie\ArrayToXml\ArrayToXml;


class InforAuth
{
    /**
     * @author Jose David Acevedo Camacho
     * Controla el envio de información  autenticando las credenciales que se envían en el cuerpo de la petición.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        try {
            $hotel_id = $request->route()->parameter('hotel_id');

            $response = $request->getContent();
            // \Log::info($response);
            /** Se eliminan todas las directivas adyacentes a los nombres de los atributos del xml usando expresiones regulares    */
            $xmlString = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
            $xmlString = preg_replace('/([a-zA-Z0-9_-]+):([a-zA-Z0-9_-]+)/', '$1$2', $xmlString);

            try {
                $xml       = simplexml_load_string($xmlString);
            } catch (\Exception $e) {
                /** Si el xml es enviado con errores estructurales, se retornara un error */
                $xml_response = ArrayToXml::convert([
                    "soap:Body" => [
                        'm:Response' => [
                            'm:Status' => 400,
                            'm:Message' => "Bad Request"
                        ],
                        '_attributes' => [
                            "xmlns:m" => ""
                        ]
                    ],
                    '_attributes' => [
                        'xmlns:soap' => "http://www.w3.org/2003/05/soap-envelope/",
                        'soap:encodingStyle' => "http://www.w3.org/2003/05/soap-encoding"
                    ]
                ], 'soap:Envelope');

                return response($xml_response, 400)->header('Content-Type', 'Application/xml');
            }

            $str_json  = json_encode($xml);
            $json      = json_decode($str_json);

            $integrations_infor = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)
                ->where('int_id', 7)->where('state', 1)->first();
            /** Se busca los datos de la integración en base al hotel_id que se envia como parametro en la url
             *  Ademas se valida si esta integración existe o no, y si no existe retoran un error de autenticación
             */
            if (!$integrations_infor) {
                $xml_response = ArrayToXml::convert([
                    "soap:Body" => [
                        'm:Response' => [
                            'm:Status' => 401,
                            'm:Message' => "Auth Failed"
                        ],
                        '_attributes' => [
                            "xmlns:m" => ""
                        ]
                    ],
                    '_attributes' => [
                        'xmlns:soap' => "http://www.w3.org/2003/05/soap-envelope/",
                        'soap:encodingStyle' => "http://www.w3.org/2003/05/soap-encoding"
                    ]
                ], 'soap:Envelope');

                return response($xml_response, 401)->header('Content-Type', 'Aplication/xml');
            }
            /** Se guardan los datos de configuración de la integración 
             *  Ademas se guardan las credenciales que se encuentran en la configuración de la integración
             */
            $config = $integrations_infor->config;
            $user      = $config['user'];
            $password  = $config['password'];

            /** Se valida que se halla enviado los atributos Username y Password en el encabezado del xml y se guardan en sus respectivas variables
             *  Si estos atributos no son enviados se guardaran campos vacios.
             */
            $inforUser_tenantId = !empty($json->Header->Security->UsernameToken->Username) ? $json->Header->Security->UsernameToken->Username : '';
            $inforPass = !empty($json->Header->Security->UsernameToken->Password) ? $json->Header->Security->UsernameToken->Password : '';

            /** El atributo Username envía dos datos concatenados por un @, se realiza la separación  y se toma  unicamente el username
             * ¡¡¡¡¡EXAMPLE!!!!!  username@tenantId
             */
            $inforUser = preg_replace('/(.*)@(.*)/', '$1', $inforUser_tenantId);
            $inforTentant_id = preg_replace('/(.*)@(.*)/', '$2', $inforUser_tenantId);

            /** Se realiza la comparación de las credenciales, si coinciden se guarda en el cuerpo de la petición el staff_id y la configuración de la integración
             *      y se permitira el acceso al controlador de infor.
             *  Si la autenticación falla, este retornará un error de autenticación
             */
            if ($user == $inforUser && $password == $inforPass) {
                $request->merge([
                    "staff_id"  => $integrations_infor->created_by,
                    "config"    => $integrations_infor->config
                ]);
                return $next($request);
            } else {
                $xml_response = ArrayToXml::convert([
                    "soap:Body" => [
                        'm:Response' => [
                            'm:Status' => 401,
                            'm:Message' => "Auth Failed"
                        ],
                        '_attributes' => [
                            "xmlns:m" => ""
                        ]
                    ],
                    '_attributes' => [
                        'xmlns:soap' => "http://www.w3.org/2003/05/soap-envelope/",
                        'soap:encodingStyle' => "http://www.w3.org/2003/05/soap-encoding"
                    ]
                ], 'soap:Envelope');

                return response($xml_response, 401)->header('Content-Type', 'Aplication/xml');
            }
        } catch (Exception $e) {
            \Log::error($e);
        }
    }
}
