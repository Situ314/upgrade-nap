<?php

namespace App\Helpers;

use DB;


class MainHelper
{

    public static function sendNotificationMessages($hotel_id, $guest_id, $staff_id, $email = '', $phone = '', $welcome = true, $back = false, $angel = true)
    {
        try {
            $str_query =  "";
            if ($angel) {
                $str_query .= "SELECT 'angel' type, rp.view access, g.angel_status 
                FROM role_permission rp 
                INNER JOIN menus m ON m.menu_id = 22 
                INNER JOIN roles r ON r.is_active = 1 AND r.hotel_id = $hotel_id AND lower(r.role_name) = 'hotel admin' 
                INNER JOIN guest_registration g ON g.hotel_id = r.hotel_id and g.guest_id = $guest_id 
                WHERE rp.role_id = r.role_id AND rp.menu_id = m.menu_id";
            }
            if ($angel && $welcome) {
                $str_query .= " UNION ";
            }
            if ($welcome) {
                $str_query .= "SELECT 'schat' type, rp.view access, '' angel_status
                FROM role_permission rp 
                INNER JOIN menus m ON m.menu_id = 30 
                INNER JOIN roles r ON r.is_active = 1 AND r.hotel_id = $hotel_id AND lower(r.role_name) = 'hotel admin' 
                WHERE rp.role_id = r.role_id AND rp.menu_id = m.menu_id";
            }

            if (!empty($str_query)) {
                $result = DB::select($str_query);
                if (count($result) > 0) {
                    $send_angel      = false;
                    $send_welcome    = false;

                    foreach ($result as $kResult => $vResult) {
                        if ($vResult->type == 'angel' && $vResult->access == 1) $send_angel = $vResult->angel_status == 1 ? true : false;
                        if ($vResult->type == 'schat' && $vResult->access == 1) $send_welcome = true;
                    }
                    $client = new \GuzzleHttp\Client(['verify' => false]);


                    if (strpos(url('/'), 'api-dev') !== false) {
                        $url_app = 'https://integrations.mynuvola.com/index.php/send_invitations';
                    } else {
                        $url_app = 'https://hotel.mynuvola.com/index.php/send_invitations';
                    }

                    $rs = [];
                    if ($send_angel) {
                        $response = $client->request('POST', $url_app, [
                            'form_params' => [
                                'hotel_id'  => $hotel_id,
                                'guest_id'  => '',
                                'staff_id'  => '',
                                "type"      => 'angel',
                                'email'     => $email,
                                'phone'     => $phone,
                            ]
                        ]);
                        $response = $response->getBody()->getContents();

                        $rs['angel'] = $response;
                    }

                    if ($send_welcome) {
                        $response = $client->request('POST', $url_app, [
                            'form_params' => [
                                'hotel_id'  => $hotel_id,
                                'guest_id'  => $guest_id,
                                'staff_id'  => $staff_id,
                                "type"      => 'welcome',
                                'email'     => $email,
                                'phone'     => $phone,
                                'back'      => $back,
                            ]
                        ]);
                        $response = $response->getBody()->getContents();
                        $rs['welcome'] = $response;
                    }

                    $rs['send_angel'] = $send_angel;
                    $rs['send_welcome'] = $send_welcome;

                    \Log::info("Helpers > Helper.php > sendMessages: " . json_encode($rs));

                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            \Log::error("Error in Helpers > Helper.php");
            \Log::error($e);
            return null;
        }
    }
}
