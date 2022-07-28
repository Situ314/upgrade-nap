<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\IntegrationsActive;
use App\Models\LogTracker;
use App\Models\StaffHotel;
use DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Storage;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function writeLog($folder_name, $hotel_id, $text)
    {
        $activate_log_for_this = [
            //198, // Seaside Resort
            //208, // Avista Resort
            //216, // Prince Resort
            //217, // Grnade Shore Resort
            //231, // Grande Shore Condo
            //232, // Horizon
            //214, // Hacienda Buenaventura
            //166, // Hotel Maestro PMS
            // 266, // Opera - Wyndham Clearwater Beach Resort
        ];
        $activate_log_for_this = [];

        if (count($activate_log_for_this) == 0 || in_array($hotel_id, $activate_log_for_this)) {
            $hotel_name = '';
            $path = "/logs/$folder_name";

            if ($hotel_id) {
                $this->configTimeZone($hotel_id);
                $hotel_name = Hotel::find($hotel_id)->hotel_name;
                $hotel_name = str_replace([' '], '_', $hotel_name);
                $hotel_name = str_replace(['.'], '_', $hotel_name);
                $hotel_name = str_replace(['&'], '', $hotel_name);
                $hotel_name = strtolower($hotel_name);
                $path .= "/$hotel_id"."__$hotel_name";
            }

            if (! Storage::has($path)) {
                Storage::makeDirectory($path, 0775, true);
            }

            $day = date('Y_m_d');
            $file = "$path/$day.log";
            $hour = date('H:i:s');
            $text = "[$hour]: $text";

            Storage::append($file, $text);
        }
    }

    public function customWriteLog($folder_name, $hotel_id, $text)
    {
        $path = "/logs/$folder_name";

        if (! Storage::has($path)) {
            Storage::makeDirectory($path, 0775, true);
        }

        if (! Storage::has($path.'/'.$hotel_id)) {
            Storage::makeDirectory($path.'/'.$hotel_id, 0775, true);
        }

        $day = date('Y_m_d');
        $file = "$path/$hotel_id/$day.log";
        $hour = date('H:i:s');
        $text = "[$hour]: $text";

        Storage::append($file, $text);

        return true;
    }

    public function validateHotelId($hotel_id, $staff_id)
    {
        $itsYourHotel = false;
        $staff_hotel = StaffHotel::where('hotel_id', $hotel_id)->where('staff_id', $staff_id)->first();
        if ($staff_hotel) {
            $itsYourHotel = true;
        }

        return $itsYourHotel;
    }

    public function saveLogTracker($__log_tracker)
    {
        $track_id = LogTracker::create($__log_tracker)->track_id;

        return $track_id;
    }

    public function configTimeZone($hotel_id)
    {
        $timezone = Hotel::find($hotel_id)->time_zone;
        date_default_timezone_set($timezone);
    }

    public function proccessString($string, $replace_data = null)
    {
        if ($replace_data) {
            return (isset($string) && is_string($string)) ?
            str_replace($replace_data['replace'], $replace_data['by'], addslashes($string)) : '';
        }

        return (isset($string) && is_string($string)) ? addslashes($string) : '';
    }

    public function validateAngelStatus($hotel_id)
    {
        $query =
            "SELECT rp.view from role_permission rp 
            INNER JOIN menus m ON m.menu_id = 22
            INNER JOIN roles r ON r.hotel_id = $hotel_id AND r.role_name = 'Hotel Admin'
            WHERE rp.role_id = r.role_id AND rp.menu_id = m.menu_id
            LIMIT 1";

        $result = DB::select($query);
        if ($result && count($result) > 0) {
            return $result[0]->view;
        }

        return 0;
    }

    public function sendAngelInvitation($email, $hotel_id, $phone = '')
    {
        $blocked_hotels_angel = [243, 220, 241, 240, 149, 219, 313];
        if (! $this->isIntegration($hotel_id)) {
            \Log::info("$hotel_id $phone");

            return 'angel is blocked';
        }
        if (! in_array($hotel_id, $blocked_hotels_angel)) {
            try {
                $query =
                "SELECT rp.view from role_permission rp 
                INNER JOIN menus m ON m.menu_id = 22
                INNER JOIN roles r ON r.hotel_id = $hotel_id AND r.role_name = 'Hotel Admin'
                WHERE rp.role_id = r.role_id AND rp.menu_id = m.menu_id
                LIMIT 1";

                $result = DB::select($query);

                if (count($result) > 0) {
                    if ($result[0]->view == 1) {
                        $client = new \GuzzleHttp\Client();

                        // if(strpos(url('/'), "https://api.") !== false) {
                        //     $url_app = 'https://hotel.mynuvola.com/index.php/send_invitations';
                        // } else {
                        //     $url_app = 'https://integrations.mynuvola.com/index.php/send_invitations';
                        // }

                        $url_app = 'https://hotel.mynuvola.com/index.php/send_invitations';
                        //$url_app = 'https://integrations.mynuvola.com/index.php/send_invitations';

                        $response = $client->request('POST', $url_app, [
                            'form_params' => [
                                'email' => $email,
                                'hotel_id' => $hotel_id,
                                'phone' => $phone,
                                'type' => 'angel',
                                'guest_id' => '',
                                'staff_id' => '',
                            ],
                        ]);

                        $response = $response->getBody()->getContents();

                        $response1 = $client->request('POST', $url_app, [
                            'form_params' => [
                                'email' => $email,
                                'hotel_id' => $hotel_id,
                                'phone' => $phone,
                                'type' => 'welcome',
                                'guest_id' => '',
                                'staff_id' => '',
                            ],
                        ]);

                        $response1 = $response1->getBody()->getContents();

                        return [
                            'angel' => $response,
                            'welcome' => $response1,
                        ];
                    } else {
                        return 'Module incative';
                    }
                } else {
                    return 'Record not found';
                }
            } catch (\Exception $e) {
                return 'Error: '.$e;
            }
        } else {
            return 'no access';
        }
    }

    public function getRoom($hotel_id, $staff_id, $location)
    {
        $room = HotelRoom::where('hotel_id', $hotel_id)
        ->where(function ($query) use ($location) {
            return $query
            ->where('location', $location)
            ->orWhere('room_id', $location);
        })->first();

        if ($room) {
            return [
                'room_id' => $room->room_id,
                'room' => $room->location,
            ];
        } else {
            $room = HotelRoom::create([
                'hotel_id' => $hotel_id,
                'location' => $location,
                'created_by' => $staff_id,
                'created_on' => date('Y-m-d H:i:s'),
                'updated_by' => null,
                'updated_on' => null,
                'active' => 1,
                'angel_view' => 1,
                'device_token' => '',
            ]);

            $this->saveLogTracker([
                'hotel_id' => $hotel_id,
                'staff_id' => $staff_id,
                'prim_id' => $room->room_id,
                'module_id' => 17,
                'action' => 'add',
                'date_time' => date('Y-m-d H:i:s'),
                'comments' => '',
                'type' => 'API',
            ]);

            return [
                'room_id' => $room->room_id,
                'room' => $room->location,
            ];
        }
    }

    public function getPermission($hotel_id, $staff_id, $menu_id, $action)
    {
        $staff_hotels = DB::table('staff_hotels as sh')
        ->select("rp.$action as action")
        ->join('roles as r', 'r.role_id', '=', 'sh.role_id')
        ->join('role_permission as rp', 'rp.role_id', '=', 'r.role_id')
        ->where(function ($q) use ($hotel_id, $staff_id, $menu_id) {
            $q
                ->where('sh.hotel_id', $hotel_id)
                ->where('sh.staff_id', $staff_id)
                ->where('sh.is_active', 1)
                ->where('rp.menu_id', $menu_id);
        })
        ->first();
        if ($staff_hotels) {
            return $staff_hotels->action == 1 ? true : false;
        }

        return null;
    }

    public function isIntegration($hotel_id)
    {
        $int = IntegrationsActive::where('hotel_id', $hotel_id)->where('state', 1)->first();
        if ($int) {
            return $int->sms_angel_active == 1 ? true : false;
        }

        return true;
    }
}

/*
    public function CountryCode($code = 'US') {
        $code = strtoupper($code);
        $countryArray = array(
            'AD'=>array('name'=>'ANDORRA','code'=>'376'),
            'AE'=>array('name'=>'UNITED ARAB EMIRATES','code'=>'971'),
            'AF'=>array('name'=>'AFGHANISTAN','code'=>'93'),
            'AG'=>array('name'=>'ANTIGUA AND BARBUDA','code'=>'1268'),
            'AI'=>array('name'=>'ANGUILLA','code'=>'1264'),
            'AL'=>array('name'=>'ALBANIA','code'=>'355'),
            'AM'=>array('name'=>'ARMENIA','code'=>'374'),
            'AN'=>array('name'=>'NETHERLANDS ANTILLES','code'=>'599'),
            'AO'=>array('name'=>'ANGOLA','code'=>'244'),
            'AQ'=>array('name'=>'ANTARCTICA','code'=>'672'),
            'AR'=>array('name'=>'ARGENTINA','code'=>'54'),
            'AS'=>array('name'=>'AMERICAN SAMOA','code'=>'1684'),
            'AT'=>array('name'=>'AUSTRIA','code'=>'43'),
            'AU'=>array('name'=>'AUSTRALIA','code'=>'61'),
            'AW'=>array('name'=>'ARUBA','code'=>'297'),
            'AZ'=>array('name'=>'AZERBAIJAN','code'=>'994'),
            'BA'=>array('name'=>'BOSNIA AND HERZEGOVINA','code'=>'387'),
            'BB'=>array('name'=>'BARBADOS','code'=>'1246'),
            'BD'=>array('name'=>'BANGLADESH','code'=>'880'),
            'BE'=>array('name'=>'BELGIUM','code'=>'32'),
            'BF'=>array('name'=>'BURKINA FASO','code'=>'226'),
            'BG'=>array('name'=>'BULGARIA','code'=>'359'),
            'BH'=>array('name'=>'BAHRAIN','code'=>'973'),
            'BI'=>array('name'=>'BURUNDI','code'=>'257'),
            'BJ'=>array('name'=>'BENIN','code'=>'229'),
            'BL'=>array('name'=>'SAINT BARTHELEMY','code'=>'590'),
            'BM'=>array('name'=>'BERMUDA','code'=>'1441'),
            'BN'=>array('name'=>'BRUNEI DARUSSALAM','code'=>'673'),
            'BO'=>array('name'=>'BOLIVIA','code'=>'591'),
            'BR'=>array('name'=>'BRAZIL','code'=>'55'),
            'BS'=>array('name'=>'BAHAMAS','code'=>'1242'),
            'BT'=>array('name'=>'BHUTAN','code'=>'975'),
            'BW'=>array('name'=>'BOTSWANA','code'=>'267'),
            'BY'=>array('name'=>'BELARUS','code'=>'375'),
            'BZ'=>array('name'=>'BELIZE','code'=>'501'),
            'CA'=>array('name'=>'CANADA','code'=>'1'),
            'CC'=>array('name'=>'COCOS (KEELING) ISLANDS','code'=>'61'),
            'CD'=>array('name'=>'CONGO, THE DEMOCRATIC REPUBLIC OF THE','code'=>'243'),
            'CF'=>array('name'=>'CENTRAL AFRICAN REPUBLIC','code'=>'236'),
            'CG'=>array('name'=>'CONGO','code'=>'242'),
            'CH'=>array('name'=>'SWITZERLAND','code'=>'41'),
            'CI'=>array('name'=>'COTE D IVOIRE','code'=>'225'),
            'CK'=>array('name'=>'COOK ISLANDS','code'=>'682'),
            'CL'=>array('name'=>'CHILE','code'=>'56'),
            'CM'=>array('name'=>'CAMEROON','code'=>'237'),
            'CN'=>array('name'=>'CHINA','code'=>'86'),
            'CO'=>array('name'=>'COLOMBIA','code'=>'57'),
            'CR'=>array('name'=>'COSTA RICA','code'=>'506'),
            'CU'=>array('name'=>'CUBA','code'=>'53'),
            'CV'=>array('name'=>'CAPE VERDE','code'=>'238'),
            'CX'=>array('name'=>'CHRISTMAS ISLAND','code'=>'61'),
            'CY'=>array('name'=>'CYPRUS','code'=>'357'),
            'CZ'=>array('name'=>'CZECH REPUBLIC','code'=>'420'),
            'DE'=>array('name'=>'GERMANY','code'=>'49'),
            'DJ'=>array('name'=>'DJIBOUTI','code'=>'253'),
            'DK'=>array('name'=>'DENMARK','code'=>'45'),
            'DM'=>array('name'=>'DOMINICA','code'=>'1767'),
            'DO'=>array('name'=>'DOMINICAN REPUBLIC','code'=>'1809'),
            'DZ'=>array('name'=>'ALGERIA','code'=>'213'),
            'EC'=>array('name'=>'ECUADOR','code'=>'593'),
            'EE'=>array('name'=>'ESTONIA','code'=>'372'),
            'EG'=>array('name'=>'EGYPT','code'=>'20'),
            'ER'=>array('name'=>'ERITREA','code'=>'291'),
            'ES'=>array('name'=>'SPAIN','code'=>'34'),
            'ET'=>array('name'=>'ETHIOPIA','code'=>'251'),
            'FI'=>array('name'=>'FINLAND','code'=>'358'),
            'FJ'=>array('name'=>'FIJI','code'=>'679'),
            'FK'=>array('name'=>'FALKLAND ISLANDS (MALVINAS)','code'=>'500'),
            'FM'=>array('name'=>'MICRONESIA, FEDERATED STATES OF','code'=>'691'),
            'FO'=>array('name'=>'FAROE ISLANDS','code'=>'298'),
            'FR'=>array('name'=>'FRANCE','code'=>'33'),
            'GA'=>array('name'=>'GABON','code'=>'241'),
            'GB'=>array('name'=>'UNITED KINGDOM','code'=>'44'),
            'GD'=>array('name'=>'GRENADA','code'=>'1473'),
            'GE'=>array('name'=>'GEORGIA','code'=>'995'),
            'GH'=>array('name'=>'GHANA','code'=>'233'),
            'GI'=>array('name'=>'GIBRALTAR','code'=>'350'),
            'GL'=>array('name'=>'GREENLAND','code'=>'299'),
            'GM'=>array('name'=>'GAMBIA','code'=>'220'),
            'GN'=>array('name'=>'GUINEA','code'=>'224'),
            'GQ'=>array('name'=>'EQUATORIAL GUINEA','code'=>'240'),
            'GR'=>array('name'=>'GREECE','code'=>'30'),
            'GT'=>array('name'=>'GUATEMALA','code'=>'502'),
            'GU'=>array('name'=>'GUAM','code'=>'1671'),
            'GW'=>array('name'=>'GUINEA-BISSAU','code'=>'245'),
            'GY'=>array('name'=>'GUYANA','code'=>'592'),
            'HK'=>array('name'=>'HONG KONG','code'=>'852'),
            'HN'=>array('name'=>'HONDURAS','code'=>'504'),
            'HR'=>array('name'=>'CROATIA','code'=>'385'),
            'HT'=>array('name'=>'HAITI','code'=>'509'),
            'HU'=>array('name'=>'HUNGARY','code'=>'36'),
            'ID'=>array('name'=>'INDONESIA','code'=>'62'),
            'IE'=>array('name'=>'IRELAND','code'=>'353'),
            'IL'=>array('name'=>'ISRAEL','code'=>'972'),
            'IM'=>array('name'=>'ISLE OF MAN','code'=>'44'),
            'IN'=>array('name'=>'INDIA','code'=>'91'),
            'IQ'=>array('name'=>'IRAQ','code'=>'964'),
            'IR'=>array('name'=>'IRAN, ISLAMIC REPUBLIC OF','code'=>'98'),
            'IS'=>array('name'=>'ICELAND','code'=>'354'),
            'IT'=>array('name'=>'ITALY','code'=>'39'),
            'JM'=>array('name'=>'JAMAICA','code'=>'1876'),
            'JO'=>array('name'=>'JORDAN','code'=>'962'),
            'JP'=>array('name'=>'JAPAN','code'=>'81'),
            'KE'=>array('name'=>'KENYA','code'=>'254'),
            'KG'=>array('name'=>'KYRGYZSTAN','code'=>'996'),
            'KH'=>array('name'=>'CAMBODIA','code'=>'855'),
            'KI'=>array('name'=>'KIRIBATI','code'=>'686'),
            'KM'=>array('name'=>'COMOROS','code'=>'269'),
            'KN'=>array('name'=>'SAINT KITTS AND NEVIS','code'=>'1869'),
            'KP'=>array('name'=>'KOREA DEMOCRATIC PEOPLES REPUBLIC OF','code'=>'850'),
            'KR'=>array('name'=>'KOREA REPUBLIC OF','code'=>'82'),
            'KW'=>array('name'=>'KUWAIT','code'=>'965'),
            'KY'=>array('name'=>'CAYMAN ISLANDS','code'=>'1345'),
            'KZ'=>array('name'=>'KAZAKSTAN','code'=>'7'),
            'LA'=>array('name'=>'LAO PEOPLES DEMOCRATIC REPUBLIC','code'=>'856'),
            'LB'=>array('name'=>'LEBANON','code'=>'961'),
            'LC'=>array('name'=>'SAINT LUCIA','code'=>'1758'),
            'LI'=>array('name'=>'LIECHTENSTEIN','code'=>'423'),
            'LK'=>array('name'=>'SRI LANKA','code'=>'94'),
            'LR'=>array('name'=>'LIBERIA','code'=>'231'),
            'LS'=>array('name'=>'LESOTHO','code'=>'266'),
            'LT'=>array('name'=>'LITHUANIA','code'=>'370'),
            'LU'=>array('name'=>'LUXEMBOURG','code'=>'352'),
            'LV'=>array('name'=>'LATVIA','code'=>'371'),
            'LY'=>array('name'=>'LIBYAN ARAB JAMAHIRIYA','code'=>'218'),
            'MA'=>array('name'=>'MOROCCO','code'=>'212'),
            'MC'=>array('name'=>'MONACO','code'=>'377'),
            'MD'=>array('name'=>'MOLDOVA, REPUBLIC OF','code'=>'373'),
            'ME'=>array('name'=>'MONTENEGRO','code'=>'382'),
            'MF'=>array('name'=>'SAINT MARTIN','code'=>'1599'),
            'MG'=>array('name'=>'MADAGASCAR','code'=>'261'),
            'MH'=>array('name'=>'MARSHALL ISLANDS','code'=>'692'),
            'MK'=>array('name'=>'MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF','code'=>'389'),
            'ML'=>array('name'=>'MALI','code'=>'223'),
            'MM'=>array('name'=>'MYANMAR','code'=>'95'),
            'MN'=>array('name'=>'MONGOLIA','code'=>'976'),
            'MO'=>array('name'=>'MACAU','code'=>'853'),
            'MP'=>array('name'=>'NORTHERN MARIANA ISLANDS','code'=>'1670'),
            'MR'=>array('name'=>'MAURITANIA','code'=>'222'),
            'MS'=>array('name'=>'MONTSERRAT','code'=>'1664'),
            'MT'=>array('name'=>'MALTA','code'=>'356'),
            'MU'=>array('name'=>'MAURITIUS','code'=>'230'),
            'MV'=>array('name'=>'MALDIVES','code'=>'960'),
            'MW'=>array('name'=>'MALAWI','code'=>'265'),
            'MX'=>array('name'=>'MEXICO','code'=>'52'),
            'MY'=>array('name'=>'MALAYSIA','code'=>'60'),
            'MZ'=>array('name'=>'MOZAMBIQUE','code'=>'258'),
            'NA'=>array('name'=>'NAMIBIA','code'=>'264'),
            'NC'=>array('name'=>'NEW CALEDONIA','code'=>'687'),
            'NE'=>array('name'=>'NIGER','code'=>'227'),
            'NG'=>array('name'=>'NIGERIA','code'=>'234'),
            'NI'=>array('name'=>'NICARAGUA','code'=>'505'),
            'NL'=>array('name'=>'NETHERLANDS','code'=>'31'),
            'NO'=>array('name'=>'NORWAY','code'=>'47'),
            'NP'=>array('name'=>'NEPAL','code'=>'977'),
            'NR'=>array('name'=>'NAURU','code'=>'674'),
            'NU'=>array('name'=>'NIUE','code'=>'683'),
            'NZ'=>array('name'=>'NEW ZEALAND','code'=>'64'),
            'OM'=>array('name'=>'OMAN','code'=>'968'),
            'PA'=>array('name'=>'PANAMA','code'=>'507'),
            'PE'=>array('name'=>'PERU','code'=>'51'),
            'PF'=>array('name'=>'FRENCH POLYNESIA','code'=>'689'),
            'PG'=>array('name'=>'PAPUA NEW GUINEA','code'=>'675'),
            'PH'=>array('name'=>'PHILIPPINES','code'=>'63'),
            'PK'=>array('name'=>'PAKISTAN','code'=>'92'),
            'PL'=>array('name'=>'POLAND','code'=>'48'),
            'PM'=>array('name'=>'SAINT PIERRE AND MIQUELON','code'=>'508'),
            'PN'=>array('name'=>'PITCAIRN','code'=>'870'),
            'PR'=>array('name'=>'PUERTO RICO','code'=>'1'),
            'PT'=>array('name'=>'PORTUGAL','code'=>'351'),
            'PW'=>array('name'=>'PALAU','code'=>'680'),
            'PY'=>array('name'=>'PARAGUAY','code'=>'595'),
            'QA'=>array('name'=>'QATAR','code'=>'974'),
            'RO'=>array('name'=>'ROMANIA','code'=>'40'),
            'RS'=>array('name'=>'SERBIA','code'=>'381'),
            'RU'=>array('name'=>'RUSSIAN FEDERATION','code'=>'7'),
            'RW'=>array('name'=>'RWANDA','code'=>'250'),
            'SA'=>array('name'=>'SAUDI ARABIA','code'=>'966'),
            'SB'=>array('name'=>'SOLOMON ISLANDS','code'=>'677'),
            'SC'=>array('name'=>'SEYCHELLES','code'=>'248'),
            'SD'=>array('name'=>'SUDAN','code'=>'249'),
            'SE'=>array('name'=>'SWEDEN','code'=>'46'),
            'SG'=>array('name'=>'SINGAPORE','code'=>'65'),
            'SH'=>array('name'=>'SAINT HELENA','code'=>'290'),
            'SI'=>array('name'=>'SLOVENIA','code'=>'386'),
            'SK'=>array('name'=>'SLOVAKIA','code'=>'421'),
            'SL'=>array('name'=>'SIERRA LEONE','code'=>'232'),
            'SM'=>array('name'=>'SAN MARINO','code'=>'378'),
            'SN'=>array('name'=>'SENEGAL','code'=>'221'),
            'SO'=>array('name'=>'SOMALIA','code'=>'252'),
            'SR'=>array('name'=>'SURINAME','code'=>'597'),
            'ST'=>array('name'=>'SAO TOME AND PRINCIPE','code'=>'239'),
            'SV'=>array('name'=>'EL SALVADOR','code'=>'503'),
            'SY'=>array('name'=>'SYRIAN ARAB REPUBLIC','code'=>'963'),
            'SZ'=>array('name'=>'SWAZILAND','code'=>'268'),
            'TC'=>array('name'=>'TURKS AND CAICOS ISLANDS','code'=>'1649'),
            'TD'=>array('name'=>'CHAD','code'=>'235'),
            'TG'=>array('name'=>'TOGO','code'=>'228'),
            'TH'=>array('name'=>'THAILAND','code'=>'66'),
            'TJ'=>array('name'=>'TAJIKISTAN','code'=>'992'),
            'TK'=>array('name'=>'TOKELAU','code'=>'690'),
            'TL'=>array('name'=>'TIMOR-LESTE','code'=>'670'),
            'TM'=>array('name'=>'TURKMENISTAN','code'=>'993'),
            'TN'=>array('name'=>'TUNISIA','code'=>'216'),
            'TO'=>array('name'=>'TONGA','code'=>'676'),
            'TR'=>array('name'=>'TURKEY','code'=>'90'),
            'TT'=>array('name'=>'TRINIDAD AND TOBAGO','code'=>'1868'),
            'TV'=>array('name'=>'TUVALU','code'=>'688'),
            'TW'=>array('name'=>'TAIWAN, PROVINCE OF CHINA','code'=>'886'),
            'TZ'=>array('name'=>'TANZANIA, UNITED REPUBLIC OF','code'=>'255'),
            'UA'=>array('name'=>'UKRAINE','code'=>'380'),
            'UG'=>array('name'=>'UGANDA','code'=>'256'),
            'US'=>array('name'=>'UNITED STATES','code'=>'1'),
            'UY'=>array('name'=>'URUGUAY','code'=>'598'),
            'UZ'=>array('name'=>'UZBEKISTAN','code'=>'998'),
            'VA'=>array('name'=>'HOLY SEE (VATICAN CITY STATE)','code'=>'39'),
            'VC'=>array('name'=>'SAINT VINCENT AND THE GRENADINES','code'=>'1784'),
            'VE'=>array('name'=>'VENEZUELA','code'=>'58'),
            'VG'=>array('name'=>'VIRGIN ISLANDS, BRITISH','code'=>'1284'),
            'VI'=>array('name'=>'VIRGIN ISLANDS, U.S.','code'=>'1340'),
            'VN'=>array('name'=>'VIET NAM','code'=>'84'),
            'VU'=>array('name'=>'VANUATU','code'=>'678'),
            'WF'=>array('name'=>'WALLIS AND FUTUNA','code'=>'681'),
            'WS'=>array('name'=>'SAMOA','code'=>'685'),
            'XK'=>array('name'=>'KOSOVO','code'=>'381'),
            'YE'=>array('name'=>'YEMEN','code'=>'967'),
            'YT'=>array('name'=>'MAYOTTE','code'=>'262'),
            'ZA'=>array('name'=>'SOUTH AFRICA','code'=>'27'),
            'ZM'=>array('name'=>'ZAMBIA','code'=>'260'),
            'ZW'=>array('name'=>'ZIMBABWE','code'=>'263')
        );
        return isset($countryArray[$code]["code"]) ? $countryArray[$code]["code"] : '1';
    }

*/
