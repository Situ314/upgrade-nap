<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SMSMillerController extends Controller
{
    public function index(Request $request)
    {
        try {
            $hotel_id = $request->hotel_id;
            $staff_id = $request->staff_id;
            $data = $request->data;
            $config = $request->config;
            $this->configTimeZone($hotel_id);
            $now = date('Y-m-d H:i:s');

            $data = $request->data;
            $keys = array_keys(array_get($data, 'Body'));
            switch ($keys[0]) {
                case 'bookingcollection':
                    $booking = array_get($data, 'Body.bookingcollection', []);
                    $count = array_get($booking, 'bookingresults.@attributes.count');
                    $shareresults = array_get($booking, 'bookingresults.shareresults');
                    if ($count < 2) {
                        $shareresults = [$shareresults];
                    }
                    $process_data = [];
                    foreach ($shareresults as $value) {
                        $process_data[] = $this->proccessDataBooking($value, $hotel_id);
                    }
                    $resp = $this->responseBooking();
                    $this->dispatch(new \App\Jobs\SmsMiller($hotel_id, $staff_id, 'reservation', $process_data, $config, $now));
                    $this->dispatch(new \App\Jobs\SmsMillerLogs($hotel_id, $staff_id, 'reservation', $shareresults, 'bookingcollection'));

                    return response($resp, 200)->header('Content-Type', 'application/soap+xml; charset=utf-8');
                    break;

                case 'checkincollection':
                    $check_in = array_get($data, 'Body.checkincollection', []);
                    $count = array_get($check_in, 'checkinresults.@attributes.count');
                    $shareresults = array_get($check_in, 'checkinresults.shareresults');
                    if ($count < 2) {
                        $shareresults = [$shareresults];
                    }
                    $process_data = [];
                    foreach ($shareresults as $value) {
                        $process_data[] = $this->proccessDataBooking($value, $hotel_id);
                    }
                    $resp = $this->responseCheckOut();

                    $this->dispatch(new \App\Jobs\SmsMiller($hotel_id, $staff_id, 'reservation', $process_data, $config, $now));
                    $this->dispatch(new \App\Jobs\SmsMillerLogs($hotel_id, $staff_id, 'reservation', $shareresults, 'checkincollection'));

                    return response($resp, 200)->header('Content-Type', 'application/soap+xml; charset=utf-8');
                    break;

                case 'checkoutresults':
                    $check_out = array_get($data, 'Body.checkoutresults', []);
                    $process_data = $this->proccessDataCheckOut($check_out, $hotel_id);
                    $resp = $this->responseCheckOut();
                    $this->dispatch(new \App\Jobs\SmsMiller($hotel_id, $staff_id, 'checkOut', $process_data, $config, $now));
                    $this->dispatch(new \App\Jobs\SmsMillerLogs($hotel_id, $staff_id, 'checkOut', $check_out, 'checkoutresults'));

                    return response($resp, 200)->header('Content-Type', 'application/soap+xml; charset=utf-8');

                    break;

                case 'roommoveresults':
                    $room_move = array_get($data, $keys[0], []);

                    return $this->responseCheckOut();

                    break;
                case 'HousekeepingStatusResults':

                    $housekeeping = array_get($data, 'Body.HousekeepingStatusResults.HousekeepingStatus', []);
                    if (array_has($housekeeping, 'Code')) {
                        $housekeeping = [$housekeeping];
                    }
                    $process_data = [];
                    foreach ($housekeeping as $value) {
                        $process_data[] = $this->proccessDataHousekeeping($value, $hotel_id);
                    }
                    $v1 = isset($housekeeping[0]) ? $housekeeping[0]['@attributes']['unum'] : '';
                    $v2 = array_get($process_data, 0, []);
                    $v3 = array_get($v2, 'status', '');
                    $resp = $this->responseHousekeepingRS($v1, $v3);
                    $this->dispatch(new \App\Jobs\SmsMiller($hotel_id, $staff_id, 'housekeeping', $process_data, $config, $now));
                    $this->dispatch(new \App\Jobs\SmsMillerLogs($hotel_id, $staff_id, 'housekeeping', $housekeeping, 'HousekeepingStatusResults'));

                    return response($resp, 200)->header('Content-Type', 'application/soap+xml; charset=utf-8');
                    break;
                case 'HousekeepingStatusRS':

                    $housekeeping = array_get($data, 'Body.HousekeepingStatusRS.HousekeepingStatus', []);
                    if (array_has($housekeeping, 'Code')) {
                        $housekeeping = [$housekeeping];
                    }
                    $process_data = [];
                    foreach ($housekeeping as $value) {
                        $process_data[] = $this->proccessDataHousekeeping($value, $hotel_id);
                    }
                    $v1 = isset($housekeeping[0]) ? $housekeeping[0]['@attributes']['unum'] : '';
                    $v2 = array_get($process_data, 0, []);
                    $v3 = array_get($v2, 'status', '');
                    $resp = $this->responseHousekeepingRS($v1, $v3);
                    $this->dispatch(new \App\Jobs\SmsMiller($hotel_id, $staff_id, 'housekeeping', $process_data, $config, $now));
                    $this->dispatch(new \App\Jobs\SmsMillerLogs($hotel_id, $staff_id, 'housekeeping', $housekeeping, 'HousekeepingStatusResults'));

                    return response($resp, 200)->header('Content-Type', 'application/soap+xml; charset=utf-8');
                    break;
                case 'HouseKeepingStatusResults':
                    $housekeeping = array_get($data, 'Body.HouseKeepingStatusResults.HousekeepingStatus', []);
                    if (array_has($housekeeping, 'Code')) {
                        $housekeeping = [$housekeeping];
                    }
                    $process_data = [];
                    foreach ($housekeeping as $value) {
                        $process_data[] = $this->proccessDataHousekeeping($value, $hotel_id);
                    }
                    $v1 = isset($housekeeping[0]) ? $housekeeping[0]['@attributes']['unum'] : '';
                    $v2 = array_get($process_data, 0, []);
                    $v3 = array_get($v2, 'status', '');
                    $resp = $this->responseHousekeepingRS($v1, $v3);
                    $this->dispatch(new \App\Jobs\SmsMiller($hotel_id, $staff_id, 'housekeeping', $process_data, $config, $now));
                    $this->dispatch(new \App\Jobs\SmsMillerLogs($hotel_id, $staff_id, 'housekeeping', $housekeeping, 'HousekeepingStatusResults'));

                    return response($resp, 200)->header('Content-Type', 'application/soap+xml; charset=utf-8');
                    break;
                break;
                default:
                    // dd('no entra');
                    break;
            }
        } catch (\Exception $e) {
            \Log::info('ERROR en INDEX');
            \Log::error($e);
            $resp = $this->responseBooking();

            return $resp;
        }
    }

    public function proccessDataBooking($data, $hotel_id)
    {
        $this->configTimeZone($hotel_id);
        $__data = [

            'hotel_id' => $hotel_id,

            'reservation_number' => array_get($data, '@attributes.resno'),
            'guest_number' => array_get($data, 'guestresults.@attributes.guestnum'),
            'lastname' => ! empty(array_get($data, 'guestresults.last', '')) ? array_get($data, 'guestresults.last', '') : '',
            'firstname' => ! empty(array_get($data, 'guestresults.first', '')) ? array_get($data, 'guestresults.first', '') : '',
            'address' => ! empty(array_get($data, 'guestresults.address2', '')) ? array_get($data, 'guestresults.address2', '') : '',
            'city' => ! empty(array_get($data, 'guestresults.city', '')) ? array_get($data, 'guestresults.city', '') : '',
            'state' => ! empty(array_get($data, 'guestresults.state', '')) ? array_get($data, 'guestresults.state', '') : '',
            'zipcode' => ! empty(array_get($data, 'guestresults.zip', '')) ? array_get($data, 'guestresults.zip', '') : '',
            'city' => ! empty(array_get($data, 'guestresults.city', '')) ? array_get($data, 'guestresults.city', '') : '',
            'dob' => ! empty(array_get($data, 'guestresults.bday', '')) ? array_get($data, 'guestresults.bday', '') : '',
            'phone_no' => ! empty(array_get($data, 'guestresults.phone', '')) ? array_get($data, 'guestresults.phone', '') : '',
            'email_address' => ! empty(array_get($data, 'guestresults.email', '')) ? array_get($data, 'guestresults.email', '') : '',
            'check_in' => ! empty(array_get($data, 'reservationresults.arrival', '')) ? array_get($data, 'reservationresults.arrival', '') : '',
            'check_out' => ! empty(array_get($data, 'reservationresults.depart', '')) ? array_get($data, 'reservationresults.depart', '') : '',
            'location' => '',
            'reservation_status' => '',
            'status' => '',
        ];
        $phone_no = '';
        if (substr($__data['phone_no'], 0, 1) != '+') {
            $phone_no = str_replace(['-', '.', ' ', '(', ')', '*', '/', 'na', '+'], '', $__data['phone_no']);
            if (substr($phone_no, 0, 1) == '1') {
                $phone_no = substr($phone_no, 1);
            }
            if (! empty($phone_no) && is_numeric($phone_no)) {
                $phone_no = "+1$phone_no";
            } else {
                $phone_no = '';
            }
        } else {
            $phone_no = str_replace(['-', '.', ' ', '(', ')', '*', '/', 'na'], '', $__data['phone_no']);
            if (! empty($phone_no) && is_numeric($phone_no)) {
                $phone_no = "$phone_no";
            } else {
                $phone_no = '';
            }
        }

        $__data['phone_no'] = $phone_no;

        if (! empty(array_get($data, 'reservationresults.primaryshare', '')) && array_get($data, 'reservationresults.primaryshare', '') == 'yes') {
            if (! is_array(array_get($data, 'reservationresults.name'))) {
                if (! empty(array_get($data, 'reservationresults.group', ''))) {
                    $name_option = array_get($data, 'reservationresults.name');

                    $name_option = explode(',', str_replace(' ', '', $name_option));
                    if (isset($name_option[0])) {
                        $__data['lastname'] = $name_option[0];
                    }

                    if (isset($name_option[1])) {
                        $__data['firstname'] = $name_option[1];
                    }
                } else {
                    $name_option = array_get($data, 'reservationresults.name');

                    $name_option = explode(',', str_replace(' ', '', $name_option));
                    if (isset($name_option[0])) {
                        $__data['lastname'] = $name_option[0] != $__data['lastname'] && $name_option[0] != '' ? $name_option[0] : $__data['lastname'];
                    }

                    if (isset($name_option[1])) {
                        $__data['firstname'] = $name_option[1] != $__data['firstname'] && $name_option[1] != '' ? $name_option[1] : $__data['firstname'];
                    }
                }
            }
        }

        if (array_has($data, 'reservationresults.unum') && array_has($data, 'reservationresults.utyp')) {
            if (array_get($data, 'reservationresults.unum') != array_get($data, 'reservationresults.utyp')) {
                $__data['location'] = ! empty(array_get($data, 'reservationresults.unum', '')) ? array_get($data, 'reservationresults.unum') : '';
            }
        }

        if ($__data['location'] != '' && array_has($data, 'unitresults')) {
            $__data['suites'] = $this->getRoomsSuites($data['unitresults']);
        } else {
            $__data['suites'] = [];
        }

        if (array_has($data, 'reservationresults.noshow') && ! empty(array_get($data, 'reservationresults.noshow'))) {
            $__data['status'] = 0;
            $__data['reservation_status'] = 4;
        } else {
            if (array_has($data, 'reservationresults.level') && ! empty(array_get($data, 'reservationresults.level'))) {
                $time1 = '22:00:00';
                $time2 = '23:59:59';
                switch (array_get($data, 'reservationresults.level')) {
                    case 'INH':
                        $__data['status'] = 1;
                        $__data['reservation_status'] = 1;
                        $time1 = date('H:i:s');
                        break;
                    case 'CAN':
                        $__data['status'] = 0;
                        $__data['reservation_status'] = 2;
                        $time2 = date('H:i:s');
                        break;
                    case 'CNF':
                    case 'NEW':
                        $__data['status'] = 1;
                        $__data['reservation_status'] = 0;
                        break;
                    case 'OUT':
                        $__data['status'] = 0;
                        $__data['reservation_status'] = 3;
                        $time2 = date('H:i:s');
                        break;
                }

                $__data['check_in'] = $__data['check_in'] != '' ? date('Y-m-d', strtotime("$__data[check_in]")).' '.$time1 : '';
                $__data['check_out'] = $__data['check_out'] != '' ? date('Y-m-d', strtotime("$__data[check_out]")).' '.$time2 : '';
            }
        }

        return $__data;
    }

    public function proccessDataCheckOut($data, $hotel_id)
    {
        $__data = [
            'hotel_id' => $hotel_id,
            'reservation_number' => array_get($data, '@attributes.resno'),
            'guest_number' => array_get($data, 'guestnum'),
            'status' => 0,
            'reservation_status' => 3,
        ];

        return $__data;
    }

    public function proccessDataHousekeeping($data, $hotel_id)
    {
        $__data = [
            'location' => array_get($data, '@attributes.unum'),
            'status' => array_get($data, 'Code'),
        ];

        return $__data;
    }

    public function responseHousekeeping($unum, $status)
    {
        $xml = "
            <soap:Envelope xmlns:soap='http://www.w3.org/2003/05/soap-envelope' 
            xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' 
            xmlns:xsd='http://www.w3.org/2001/XMLSchema' 
            xmlns:wsa='http://schemas.xmlsoap.org/ws/2004/08/addressing' 
            xmlns:wsse='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd' 
            xmlns:wsu='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd'>
            <env:Header xmlns:env='http://www.w3.org/2003/05/soap-envelope'>
                <wsa:Action>roomstatusupdateResponse</wsa:Action>
                <wsa:MessageID>urn:uuid:efbf155a-8c1e-4381-8c48-ebf8d41de05c</wsa:MessageID>
                <wsa:RelatesTo>urn:uuid:ebf961c6-0829-42c8-b36a-152c8960c761</wsa:RelatesTo>
                <wsa:To>http://www.w3.org/2005/08/addressing/role/anonymous</wsa:To>
                <wsse:Security>
                </wsse:Security>
            </env:Header>
            <soap:Body>
                <HousekeepingStatusResults unum='$unum' status='$status'>
                    <status>
                        <code>0</code>
                        <state>success</state>
                        <description>-1</description>
                    </status>
                </HousekeepingStatusResults>
            </soap:Body>
        </soap:Envelope>
        ";

        return $xml;
    }

    public function responseHousekeepingRS($unum, $status)
    {
        $xml = "
            <soap:Envelope xmlns:soap='http://www.w3.org/2003/05/soap-envelope' 
            xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' 
            xmlns:xsd='http://www.w3.org/2001/XMLSchema' 
            xmlns:wsa='http://schemas.xmlsoap.org/ws/2004/08/addressing' 
            xmlns:wsse='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd' 
            xmlns:wsu='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd'>
            <env:Header xmlns:env='http://www.w3.org/2003/05/soap-envelope'>
                <wsa:Action>roomstatusupdateResponse</wsa:Action>
                <wsa:MessageID>urn:uuid:efbf155a-8c1e-4381-8c48-ebf8d41de05c</wsa:MessageID>
                <wsa:RelatesTo>urn:uuid:ebf961c6-0829-42c8-b36a-152c8960c761</wsa:RelatesTo>
                <wsa:To>http://www.w3.org/2005/08/addressing/role/anonymous</wsa:To>
                <wsse:Security>
                </wsse:Security>
            </env:Header>
            <soap:Body>
                <HousekeepingStatusRS unum='$unum' status='$status'>
                    <status>
                        <code>0</code>
                        <state>success</state>
                        <description>-1</description>
                    </status>
                </HousekeepingStatusRS>
            </soap:Body>
        </soap:Envelope>
        ";

        return $xml;
    }

    public function responseBooking()
    {
        return "<soap:Envelope xmlns:soap='http://www.w3.org/2003/05/soap-envelope' 
            xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' 
            xmlns:xsd='http://www.w3.org/2001/XMLSchema' 
            xmlns:wsa='http://schemas.xmlsoap.org/ws/2004/08/addressing' 
            xmlns:wsse='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd' 
            xmlns:wsu='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd'>
            <env:Header xmlns:env='http://www.w3.org/2003/05/soap-envelope'>
                <wsa:Action>bookingupdateResponse</wsa:Action>
                <wsa:MessageID>urn:uuid:ad655d62-7430-42d1-aed3-49629658bfbf</wsa:MessageID>
                <wsa:RelatesTo>urn:uuid:206a8658-0185-407d-8d09-70809e3df9e3</wsa:RelatesTo>
                <wsa:To>http://www.w3.org/2005/08/addressing/role/anonymous</wsa:To>
                <wsse:Security>
                </wsse:Security>
            </env:Header>
            <soap:Body>
                <bookingupdateresults>
                    <status>
                        <code>0</code>
                        <state>success</state>
                        <description>Booking updated</description>
                    </status>
                </bookingupdateresults>
            </soap:Body>
        </soap:Envelope>";
    }

    public function responseCheckIn()
    {
        return "<soap:Envelope xmlns:soap='http://www.w3.org/2003/05/soap-envelope' 
            xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' 
            xmlns:xsd='http://www.w3.org/2001/XMLSchema' 
            xmlns:wsa='http://schemas.xmlsoap.org/ws/2004/08/addressing' 
            xmlns:wsse='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd' 
            xmlns:wsu='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd'>
            <env:Header xmlns:env='http://www.w3.org/2003/05/soap-envelope'>
                <wsa:Action>bookingupdateResponse</wsa:Action>
                <wsa:MessageID>urn:uuid:ad655d62-7430-42d1-aed3-49629658bfbf</wsa:MessageID>
                <wsa:RelatesTo>urn:uuid:206a8658-0185-407d-8d09-70809e3df9e3</wsa:RelatesTo>
                <wsa:To>http://www.w3.org/2005/08/addressing/role/anonymous</wsa:To>
                <wsse:Security>
                </wsse:Security>
            </env:Header>
            <soap:Body>
                <checkinupdateresults>
                    <status>
                        <code>0</code>
                        <state>success</state>
                        <description>Checkin updated</description>
                    </status>
                </checkinupdateresults>
            </soap:Body>
        </soap:Envelope>";
    }

    public function responseCheckOut()
    {
        return "<soap:Envelope xmlns:soap='http://www.w3.org/2003/05/soap-envelope' 
            xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' 
            xmlns:xsd='http://www.w3.org/2001/XMLSchema' 
            xmlns:wsa='http://schemas.xmlsoap.org/ws/2004/08/addressing' 
            xmlns:wsse='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd' 
            xmlns:wsu='http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd'>
            <env:Header xmlns:env='http://www.w3.org/2003/05/soap-envelope'>
                <wsa:Action>bookingupdateResponse</wsa:Action>
                <wsa:MessageID>urn:uuid:ad655d62-7430-42d1-aed3-49629658bfbf</wsa:MessageID>
                <wsa:RelatesTo>urn:uuid:206a8658-0185-407d-8d09-70809e3df9e3</wsa:RelatesTo>
                <wsa:To>http://www.w3.org/2005/08/addressing/role/anonymous</wsa:To>
                <wsse:Security>
                </wsse:Security>
            </env:Header>
            <soap:Body>
                <checkoutupdateresults>
                    <status>
                        <code>0</code>
                        <state>success</state>
                        <description>Checkout updated</description>
                    </status>
                </checkoutupdateresults>
            </soap:Body>
        </soap:Envelope>";
    }

    public function message()
    {
        $url = 'https://cert1.springermiller.com/HTNGListener2_1/HTNGListener2_1.asmx';
        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" 
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
            xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
            xmlns:wsa="http://www.w3.org/2005/08/addressing" 
            xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" 
            xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
            <env:Header xmlns:env="http://www.w3.org/2003/05/soap-envelope">
                <wsa:Action>roomstatusupdate</wsa:Action>
                <wsa:From>
                    <wsa:Address>urn:NUVOLA</wsa:Address>
                </wsa:From>
                <wsa:MessageID>173be4b6-35ff-4294-9923-b314ca51277d</wsa:MessageID>
                <wsa:ReplyTo>
                    <wsa:Address>http://www.w3.org/2005/08/addressing/role/anonymous</wsa:Address>
                </wsa:ReplyTo>
                <wsa:To>http://pdcert1sthv2/HTNGListener2_1/HTNGListener2_1.asmx</wsa:To>
                <wsse:Security env:mustUnderstand="true">
                    <wsu:Timestamp wsu:Id="Timestamp-3951faf8-165d-46ad-bd76-8a862d3931cb">
                    <wsu:Created>2020-03-31T14:45:18Z</wsu:Created>
                    <wsu:Expires>2020-03-31T19:56:27Z</wsu:Expires>
                    </wsu:Timestamp>
                    <wsse:UsernameToken xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" wsu:Id="SecurityToken-7fd26e4d-7f51-41cd-8c44-2130ba08dcc7">
                        <wsse:Username>NUVOLA</wsse:Username>
                        <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">NUVOLA</wsse:Password>
                        <wsse:Nonce>YBVrblRQg28LzdJOCUk7IA==</wsse:Nonce>
                        <wsu:Created>2020-03-31T14:45:18Z</wsu:Created>
                    </wsse:UsernameToken>
                </wsse:Security>
            </env:Header>
            <soap:Body>
                <roomstatusupdate unum="A107" status="X" />
            </soap:Body>
        </soap:Envelope>';
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8; action=roomstatusupdate'],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        return $response;
    }

    public function getRoomsSuites($data)
    {
        $rooms = [
            $data['unum'] => [],
        ];
        $_rooms = [];
        if (! empty($data['ustecom1'])) {
            $_rooms[] = $data['ustecom1'];
        }
        if (! empty($data['ustecom2'])) {
            $_rooms[] = $data['ustecom2'];
        }
        if (! empty($data['ustecom3'])) {
            $_rooms[] = $data['ustecom3'];
        }
        if (! empty($data['ustecom4'])) {
            $_rooms[] = $data['ustecom4'];
        }

        $rooms[$data['unum']] = $_rooms;

        if ($rooms[$data['unum']] == []) {
            return [];
        } else {
            return $rooms;
        }
    }

    public function getRoomStatusData($hotel_id)
    {
        $IntegrationsActive = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)
            ->where('int_id', 15)
            ->where('state', 1)
            ->first();
        date_default_timezone_set($IntegrationsActive->config['time_zone']);
        $timestamp = date('Y-m-d\TH:i:s\Z');
        $username = $IntegrationsActive->config['username_send'];
        $password = $IntegrationsActive->config['password_send'];
        $url = $IntegrationsActive->config['url_send'];
        $from = $IntegrationsActive->config['from_send'];

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:wsa="http://www.w3.org/2005/08/addressing" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
            <env:Header xmlns:env="http://www.w3.org/2003/05/soap-envelope">
                <wsa:Action>misccodeinquiry</wsa:Action>
                <wsa:From>
                    <wsa:Address>urn:'.$from.'</wsa:Address>
                </wsa:From>
                <wsa:MessageID>754cb61d-1785fd-4557-a6e5-893988ea8b43</wsa:MessageID>
                <wsa:ReplyTo>
                    <wsa:Address>http://www.w3.org/2005/08/addressing/role/anonymous</wsa:Address>
                </wsa:ReplyTo>
                <wsa:To>https://cert1.springermiller.com/HTNGListener2_1/HTNGListener2_1.asmx</wsa:To>
                <wsse:Security env:mustUnderstand="true">
                    <wsu:Timestamp wsu:Id="Timestamp-59d16605-e7d0-44e1-a650-bc2e8d3f60c1df">
                        <wsu:Created>'.$timestamp.'</wsu:Created>
                        <wsu:Expires>'.date('Y-m-d\TH:i:s\Z', strtotime($timestamp.' +5 minutes')).'</wsu:Expires>
                    </wsu:Timestamp>
                    <wsse:UsernameToken xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" wsu:Id="SecurityToken-042fd159-e951-481b-8d78-75d35f562da1">
                        <wsse:Username>'.$username.'</wsse:Username>
                        <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">'.$password.'</wsse:Password>
                        <wsu:Created>'.$timestamp.'</wsu:Created>
                        <wsse:Nonce>mGYVPBZJAlHfLjmlc3mONg==</wsse:Nonce>
                    </wsse:UsernameToken>
                </wsse:Security>
            </env:Header>
            <soap:Body>
               <HousekeepingStatusRQ unum="" UnitType="ALL" HskpStatus="" />
            </soap:Body>
        </soap:Envelope>';

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8;',
                'SOAPAction: HousekeepingStatusRQ',
            ],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return $err;
        } else {
            $xmlString = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
            $xml = simplexml_load_string($xmlString);
            $str_json = json_encode($xml);
            $json = json_decode($str_json, true);

            return array_get($json, 'Body');
        }
    }

    public function SyncHSK($hotel_id)
    {
        $IntegrationsActive = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)
            ->where('int_id', 15)
            ->where('state', 1)
            ->first();

        $data = $this->getRoomStatusData($hotel_id);
        $housekeeping = array_get($data, 'HousekeepingStatusRS.HousekeepingStatus', []);
        if (array_has($housekeeping, 'Code')) {
            $housekeeping = [$housekeeping];
        }
        $process_data = [];
        foreach ($housekeeping as $value) {
            $process_data[] = $this->proccessDataHousekeeping($value, $hotel_id);
        }
        $this->configTimeZone($hotel_id);
        $now = date('Y-m-d H:i:s');
        $this->dispatch(new \App\Jobs\SmsMiller($hotel_id, $IntegrationsActive->created_by, 'housekeeping', $process_data, $IntegrationsActive->config, $now));

        return response()->json([
            'sync' => true,
            'data' => $process_data,
        ]);
    }

    public function SyncReservation($hotel_id, $room_no = null)
    {
        $IntegrationsActive = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)
            ->where('int_id', 15)
            ->where('state', 1)
            ->first();
        $this->configTimeZone($hotel_id);
        $now = date('Y-m-d H:i:s');
        if (! $room_no) {
            $data = $this->getReservationData($hotel_id);
            $check_in = array_get($data, 'checkincollection.checkinresults', []);
            $__process_data = [];
            foreach ($check_in as $value) {
                $count = array_get($value, '@attributes.count');
                $shareresults = array_get($value, 'shareresults');
                if ($count < 2) {
                    $shareresults = [$shareresults];
                }
                $process_data = [];
                foreach ($shareresults as $value2) {
                    $process_data[] = $this->proccessDataBooking($value2, $hotel_id);
                }
                $this->dispatch(new \App\Jobs\SmsMiller($hotel_id, $IntegrationsActive->created_by, 'reservation', $process_data, $IntegrationsActive->config, $now));
                $__process_data[] = $process_data;
            }
            // return response()->json($__process_data);

            return response()->json([
                'sync' => true,
                'data' => $__process_data,
            ]);
        } else {
            $room = $this->getRoom($hotel_id, $IntegrationsActive->created_by, $room_no);
            $data = $this->getCheckInDataRoom($hotel_id, $room['room']);
            $check_in = array_get($data, 'checkincollection', []);
            $count = array_get($check_in, 'checkinresults.@attributes.count');
            $shareresults = array_get($check_in, 'checkinresults.shareresults');
            if ($count < 2) {
                $shareresults = [$shareresults];
            }
            $process_data = [];
            foreach ($shareresults as $value) {
                $process_data[] = $this->proccessDataBooking($value, $hotel_id);
            }

            $this->dispatch(new \App\Jobs\SmsMiller($hotel_id, $IntegrationsActive->created_by, 'reservation', $process_data, $IntegrationsActive->config, $now));
            $__process_data = $process_data;
        }
        // return response()->json($__process_data);

        return response()->json([
            'sync' => true,
            'data' => $__process_data,
        ]);
    }

    public function getReservationData($hotel_id, $room_no = null)
    {
        $IntegrationsActive = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)
            ->where('int_id', 15)
            ->where('state', 1)
            ->first();
        date_default_timezone_set($IntegrationsActive->config['time_zone']);
        $timestamp = date('Y-m-d\TH:i:s\Z');
        $username = $IntegrationsActive->config['username_send'];
        $password = $IntegrationsActive->config['password_send'];
        $url = $IntegrationsActive->config['url_send'];
        $from = $IntegrationsActive->config['from_send'];

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:wsa="http://www.w3.org/2005/08/addressing" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
            <env:Header xmlns:env="http://www.w3.org/2003/05/soap-envelope">
                <wsa:Action>misccodeinquiry</wsa:Action>
                <wsa:From>
                    <wsa:Address>urn:'.$from.'</wsa:Address>
                </wsa:From>
                <wsa:MessageID>754cb61d-1785fd-4557-a6e5-893988ea8b43</wsa:MessageID>
                <wsa:ReplyTo>
                    <wsa:Address>http://www.w3.org/2005/08/addressing/role/anonymous</wsa:Address>
                </wsa:ReplyTo>
                <wsa:To>https://cert1.springermiller.com/HTNGListener2_1/HTNGListener2_1.asmx</wsa:To>
                <wsse:Security env:mustUnderstand="true">
                    <wsu:Timestamp wsu:Id="Timestamp-59d16605-e7d0-44e1-a650-bc2e8d3f60c1df">
                        <wsu:Created>'.$timestamp.'</wsu:Created>
                        <wsu:Expires>'.date('Y-m-d\TH:i:s\Z', strtotime($timestamp.' +5 minutes')).'</wsu:Expires>
                    </wsu:Timestamp>
                    <wsse:UsernameToken xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" wsu:Id="SecurityToken-042fd159-e951-481b-8d78-75d35f562da1">
                        <wsse:Username>'.$username.'</wsse:Username>
                        <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">'.$password.'</wsse:Password>
                        <wsu:Created>'.$timestamp.'</wsu:Created>
                        <wsse:Nonce>mGYVPBZJAlHfLjmlc3mONg==</wsse:Nonce>
                    </wsse:UsernameToken>
                </wsse:Security>
            </env:Header>
            <soap:Body>
                    <checkininquiry unum="ALL" />
            </soap:Body>
        </soap:Envelope>';
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8;',
                'SOAPAction: checkininquiry',
            ],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return $err;
        } else {
            // return $response;
            $xmlString = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
            $xml = simplexml_load_string($xmlString);
            $str_json = json_encode($xml);
            $json = json_decode($str_json, true);

            return array_get($json, 'Body');
        }
    }

    public function getCheckInDataRoom($hotel_id, $room_no = null)
    {
        $IntegrationsActive = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)
            ->where('int_id', 15)
            ->where('state', 1)
            ->first();

        date_default_timezone_set($IntegrationsActive->config['time_zone']);
        $timestamp = date('Y-m-d\TH:i:s\Z');
        $username = $IntegrationsActive->config['username_send'];
        $password = $IntegrationsActive->config['password_send'];
        $url = $IntegrationsActive->config['url_send'];
        $from = $IntegrationsActive->config['from_send'];

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:wsa="http://www.w3.org/2005/08/addressing" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
            <env:Header xmlns:env="http://www.w3.org/2003/05/soap-envelope">
                <wsa:Action>misccodeinquiry</wsa:Action>
                <wsa:From>
                    <wsa:Address>urn:'.$from.'</wsa:Address>
                </wsa:From>
                <wsa:MessageID>754cb61d-1785fd-4557-a6e5-893988ea8b43</wsa:MessageID>
                <wsa:ReplyTo>
                    <wsa:Address>http://www.w3.org/2005/08/addressing/role/anonymous</wsa:Address>
                </wsa:ReplyTo>
                <wsa:To>https://cert1.springermiller.com/HTNGListener2_1/HTNGListener2_1.asmx</wsa:To>
                <wsse:Security env:mustUnderstand="true">
                    <wsu:Timestamp wsu:Id="Timestamp-59d16605-e7d0-44e1-a650-bc2e8d3f60c1df">
                        <wsu:Created>'.$timestamp.'</wsu:Created>
                        <wsu:Expires>'.date('Y-m-d\TH:i:s\Z', strtotime($timestamp.' +5 minutes')).'</wsu:Expires>
                    </wsu:Timestamp>
                    <wsse:UsernameToken xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" wsu:Id="SecurityToken-042fd159-e951-481b-8d78-75d35f562da1">
                        <wsse:Username>'.$username.'</wsse:Username>
                        <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">'.$password.'</wsse:Password>
                        <wsu:Created>'.$timestamp.'</wsu:Created>
                        <wsse:Nonce>mGYVPBZJAlHfLjmlc3mONg==</wsse:Nonce>
                    </wsse:UsernameToken>
                </wsse:Security>
            </env:Header>
            <soap:Body>
                    <checkininquiry unum="'.$room_no.'" />
            </soap:Body>
        </soap:Envelope>';
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8;',
                'SOAPAction: checkininquiry',
            ],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return $err;
        } else {
            // return $response;
            $xmlString = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
            $xml = simplexml_load_string($xmlString);
            $str_json = json_encode($xml);
            $json = json_decode($str_json, true);

            return array_get($json, 'Body');
        }
    }

    public function getReservationDataRoom($hotel_id)
    {
        $IntegrationsActive = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)
            ->where('int_id', 15)
            ->where('state', 1)
            ->first();

        date_default_timezone_set($IntegrationsActive->config['time_zone']);
        $timestamp = date('Y-m-d\TH:i:s\Z');
        $username = $IntegrationsActive->config['username_send'];
        $password = $IntegrationsActive->config['password_send'];
        $url = $IntegrationsActive->config['url_send'];
        $from = $IntegrationsActive->config['from_send'];

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:wsa="http://www.w3.org/2005/08/addressing" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
            <env:Header xmlns:env="http://www.w3.org/2003/05/soap-envelope">
                <wsa:Action>misccodeinquiry</wsa:Action>
                <wsa:From>
                    <wsa:Address>urn:'.$from.'</wsa:Address>
                </wsa:From>
                <wsa:MessageID>754cb61d-1785fd-4557-a6e5-893988ea8b43</wsa:MessageID>
                <wsa:ReplyTo>
                    <wsa:Address>http://www.w3.org/2005/08/addressing/role/anonymous</wsa:Address>
                </wsa:ReplyTo>
                <wsa:To>https://cert1.springermiller.com/HTNGListener2_1/HTNGListener2_1.asmx</wsa:To>
                <wsse:Security env:mustUnderstand="true">
                    <wsu:Timestamp wsu:Id="Timestamp-59d16605-e7d0-44e1-a650-bc2e8d3f60c1df">
                        <wsu:Created>'.$timestamp.'</wsu:Created>
                        <wsu:Expires>'.date('Y-m-d\TH:i:s\Z', strtotime($timestamp.' +5 minutes')).'</wsu:Expires>
                    </wsu:Timestamp>
                    <wsse:UsernameToken xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" wsu:Id="SecurityToken-042fd159-e951-481b-8d78-75d35f562da1">
                        <wsse:Username>'.$username.'</wsse:Username>
                        <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">'.$password.'</wsse:Password>
                        <wsu:Created>'.$timestamp.'</wsu:Created>
                        <wsse:Nonce>mGYVPBZJAlHfLjmlc3mONg==</wsse:Nonce>
                    </wsse:UsernameToken>
                </wsse:Security>
            </env:Header>
            <soap:Body>
                    <bookinginquiry unum="A206" />
            </soap:Body>
        </soap:Envelope>';

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8;',
                'SOAPAction: bookinginquiry',
            ],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return $err;
        } else {
            $xmlString = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
            $xml = simplexml_load_string($xmlString);
            $str_json = json_encode($xml);
            $json = json_decode($str_json, true);

            return array_get($json, 'Body');
        }
    }

    public function fetchData($hotel_id)
    {
        $data = $this->getReservationData($hotel_id);
        $check_in = array_get($data, 'checkincollection.checkinresults', []);
        $__process_data = [];
        foreach ($check_in as $value) {
            $count = array_get($value, '@attributes.count');
            $shareresults = array_get($value, 'shareresults');
            if ($count < 2) {
                $shareresults = [$shareresults];
            }
            $process_data = [];
            foreach ($shareresults as $value2) {
                $process_data[] = $this->proccessDataBooking($value2, $hotel_id);
            }
            $__process_data[] = $process_data;
        }
        // return response()->json($__process_data);

        return response()->json([
            'fetch' => true,
            'data' => $__process_data,
        ]);
    }

    public function fetchHSK($hotel_id)
    {
        $data = $this->getRoomStatusData($hotel_id);
        $housekeeping = array_get($data, 'HousekeepingStatusRS.HousekeepingStatus', []);
        if (array_has($housekeeping, 'Code')) {
            $housekeeping = [$housekeeping];
        }
        $process_data = [];
        foreach ($housekeeping as $value) {
            $process_data[] = $this->proccessDataHousekeeping($value, $hotel_id);
        }

        return response()->json([
            'fetch' => true,
            'data' => $process_data,
        ]);
    }

    public function allCodesInquiry($hotel_id)
    {
        $resp = $this->allCodesInquiryRequest($hotel_id);
        $data = [];

        foreach ($resp['multipropresult'] as $key => $multipropresult) {
            foreach (array_get($multipropresult, 'roomtyperesults.roomtyperesult', []) as $key2 => $roomtyperesult) {
                foreach (array_get($roomtyperesult, 'unitresults.unitresult', []) as $key3 => $unitresults) {
                    if ($unitresults['currentzone'] == 98) {
                        $data[] = [
                            'location' => $unitresults['@attributes']['code'],
                            'hk_status' => $unitresults['hkstatus'],
                        ];
                    }
                }
            }
        }

        return response()->json($data);
    }

    public function allCodesInquiryRequest($hotel_id)
    {
        $IntegrationsActive = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)
        ->where('int_id', 15)
        ->where('state', 1)
        ->first();

        date_default_timezone_set($IntegrationsActive->config['time_zone']);
        $timestamp = date('Y-m-d\TH:i:s\Z');
        $username = $IntegrationsActive->config['username_send'];
        $password = $IntegrationsActive->config['password_send'];
        $url = $IntegrationsActive->config['url_send'];
        $from = $IntegrationsActive->config['from_send'];

        $xml = '<?xml version="1.0" encoding="utf-8"?>
        <soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope"
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xmlns:xsd="http://www.w3.org/2001/XMLSchema"
            xmlns:wsa="http://www.w3.org/2005/08/addressing"
            xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
            xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
            <env:Header xmlns:env="http://www.w3.org/2003/05/soap-envelope">
                <wsa:Action>allcodesinquiry</wsa:Action>
                <wsa:From>
                    <wsa:Address>urn:NUVOLA</wsa:Address>
                </wsa:From>
                <wsa:MessageID>3901d00b-5642-4902-8f8b-f50888424b50</wsa:MessageID>
                <wsa:ReplyTo>
                    <wsa:Address>http://www.w3.org/2005/08/addressing/role/anonymous</wsa:Address>
                </wsa:ReplyTo>
                <wsa:To>http://pdcert1sthv2/HTNGListener2_1/HTNGListener2_1.asmx</wsa:To>
                <wsse:Security env:mustUnderstand="true">
                    <wsu:Timestamp wsu:Id="Timestamp-59d16605-e7d0-44e1-a650-bc2e8d3f60c1df">
                        <wsu:Created>'.$timestamp.'</wsu:Created>
                        <wsu:Expires>'.date('Y-m-d\TH:i:s\Z', strtotime($timestamp.' +5 minutes')).'</wsu:Expires>
                    </wsu:Timestamp>
                    <wsse:UsernameToken xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" wsu:Id="SecurityToken-042fd159-e951-481b-8d78-75d35f562da1">
                        <wsse:Username>'.$username.'</wsse:Username>
                        <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">'.$password.'</wsse:Password>
                        <wsu:Created>'.$timestamp.'</wsu:Created>
                        <wsse:Nonce>mGYVPBZJAlHfLjmlc3mONg==</wsse:Nonce>
                    </wsse:UsernameToken>
                </wsse:Security>
            </env:Header>
            <soap:Body>
                <allcodesinquiry version="1.003" Target="Production" TransactionStatusCode="Start" PrimaryLangID="en-us" MULTIP="true" CRESP="false" RMFTRS="false" RTFTRS="false" ATTACH="false" RMTYPE="true" RMNUMB="true" />
            </soap:Body>
        </soap:Envelope>';

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8;',
                'SOAPAction: allcodesinquiry',
            ],
        ]);
        $response = curl_exec($curl);

        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return $err;
        } else {
            $xmlString = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $response);
            $xml = simplexml_load_string($xmlString);
            $str_json = json_encode($xml);
            $json = json_decode($str_json, true);

            return array_get($json, 'Body.allcodesresult.multipropresults');
        }
    }
}
