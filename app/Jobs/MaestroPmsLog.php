<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Log\ReservationList;
use App\Models\Log\HousekeepingStatus;
use App\Models\Log\Offmarket;
use App\Models\Log\CheckIn;
use \App\Models\Log\CheckOut;
use \App\Models\Log\Monitoring;
use DB;

class MaestroPmsLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    private $data;
    private $xml;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data, $xml = null)
    {
        date_default_timezone_set('UTC');
        $this->data = $data;
        $this->xml = $xml;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            if (isset($this->data->Action) && method_exists($this, $this->data->Action)) {
                $method = $this->data->Action;
                $this->$method($this->data);
            }    
        } catch (\Exception $e) {
            \Log::error($e);
        }        
    }

    private function ReservationList($data)
    {
        try {
            // \Log::info("-------------- ReservationList --------------");
            // \Log::info(json_encode($data));

            // if( $data->HotelId == '1803' || $data->HotelId == '2305' || $data->HotelId == '1802' || $data->HotelId == '1777') {
            //     \Log::info('------------------ Mensajes XML MAESTRO LOG ----------------------');
            //     \Log::info(json_encode($data));
            //     \Log::info('----------------------------------------');
            // }

            $reservation_data = [];
            $is_array = is_array($data->Reservations->ReservationData);

            if ($is_array) {
                $reservation_data = $data->Reservations->ReservationData;
            } else {
                $reservation_data[] = $data->Reservations->ReservationData;
            }
            foreach ($reservation_data as $value) {
                $text = '';
                if (array_key_exists('ReservationText', $value) && isset($value->ReservationText) && isset($value->ReservationText->Text)) {
                    $Data_Text = [];

                    if (!is_array($value->ReservationText->Text)) {
                        $Data_Text[] = $value->ReservationText->Text;
                    } else { 
                        $Data_Text = $value->ReservationText->Text;
                    }

                    foreach ($Data_Text as $txt) {
                        $text .= $this->ValidateLogStrings($txt);
                    }
                }
                $Reservation = [
                    'HotelId'                   => array_key_exists('HotelId', $data) ? $this->ValidateLogStrings($data->HotelId) : "",
                    'ReservationNumber'         => array_key_exists('ReservationNumber', $value) ? $this->ValidateLogStrings($value->ReservationNumber) : "",
                    'ReservationNumberKey'      => array_key_exists('ReservationNumberKey', $value) ? $this->ValidateLogStrings($value->ReservationNumberKey) : "",
                    'Adults'                    => array_key_exists('Adults', $value) ? $this->ValidateLogStrings($value->Adults) : "",
                    'Children'                  => array_key_exists('Children', $value) ? $this->ValidateLogStrings($value->Children) : "",
                    'ArrivalDate'               => array_key_exists('ArrivalDate', $value) ? $this->ValidateLogStrings($value->ArrivalDate) : "",
                    'DepartureDate'             => array_key_exists('DepartureDate', $value) ? $this->ValidateLogStrings($value->DepartureDate) : "",
                    'Nights'                    => array_key_exists('Nights', $value) ? $this->ValidateLogStrings($value->Nights) : "",
                    'Status'                    => array_key_exists('Status', $value) ? $this->ValidateLogStrings($value->Status) : "",
                    'ReservationStatus'         => array_key_exists('ReservationStatus', $value) ? $this->ValidateLogStrings($value->ReservationStatus) : "",
                    'CurrencyCode'              => array_key_exists('CurrencyCode', $value) ? $this->ValidateLogStrings($value->CurrencyCode) : "",
                    'RoomRevenue'               => array_key_exists('RoomRevenue', $value) ? $this->ValidateLogStrings($value->RoomRevenue) : "",
                    'FoodRevenue'               => array_key_exists('FoodRevenue', $value) ? $this->ValidateLogStrings($value->FoodRevenue) : "",
                    'BanquetRevenue'            => array_key_exists('BanquetRevenue', $value) ? $this->ValidateLogStrings($value->BanquetRevenue) : "",
                    'OtherRevenue'              => array_key_exists('OtherRevenue', $value) ? $this->ValidateLogStrings($value->OtherRevenue) : "",
                    'GroupReservation'          => array_key_exists('Group', $value) ? $this->ValidateLogStrings($value->Group->GroupReservation) : "",
                    'GroupName'                 => array_key_exists('Group', $value) ? $this->ValidateLogStrings($value->Group->Name) : "",
                    'GuestName'                 => array_key_exists('GuestName', $value) ? $this->ValidateLogStrings($value->GuestName) : "",
                    'Salutation'                => array_key_exists('Salutation', $value) ? $this->ValidateLogStrings($value->Salutation) : "",
                    'FirstName'                 => array_key_exists('FirstName', $value) ? $this->ValidateLogStrings($value->FirstName) : "",
                    'LastName'                  => array_key_exists('LastName', $value) ? $this->ValidateLogStrings($value->LastName) : "",
                    'MiddleInitial'             => array_key_exists('MiddleInitial', $value) ? $this->ValidateLogStrings($value->MiddleInitial) : "",
                    'MiddleName'                => array_key_exists('MiddleName', $value) ? $this->ValidateLogStrings($value->MiddleName) : "",
                    'Street'                    => array_key_exists('Street', $value) ? $this->ValidateLogStrings($value->Street) : "",
                    'Address2'                  => array_key_exists('Address2', $value) ? $this->ValidateLogStrings($value->Address2) : "",
                    'City'                      => array_key_exists('City', $value) ? $this->ValidateLogStrings($value->City) : "",
                    'State'                     => array_key_exists('State', $value) ? $this->ValidateLogStrings($value->State) : "",
                    'ZipCode'                   => array_key_exists('ZipCode', $value) ? $this->ValidateLogStrings($value->ZipCode) : "",
                    'Country'                   => array_key_exists('Country', $value) ? $this->ValidateLogStrings($value->Country) : "",
                    'EmailAddress'              => array_key_exists('EmailAddress', $value) ? $this->ValidateLogStrings($value->EmailAddress) : "",
                    'Email'                     => array_key_exists('Email', $value) ? $this->ValidateLogStrings($value->Email) : "",
                    'Phone'                     => array_key_exists('Phone', $value) ? $this->ValidateLogStrings($value->Phone) : "",
                    'Fax'                       => array_key_exists('Fax', $value) ? $this->ValidateLogStrings($value->Fax) : "",
                    'Company'                   => array_key_exists('Company', $value) ? $this->ValidateLogStrings($value->Company) : "",
                    'Language'                  => array_key_exists('Language', $value) ? $this->ValidateLogStrings($value->Language) : "",
                    'Vip'                       => array_key_exists('Vip', $value) ? $this->ValidateLogStrings($value->Vip) : "",
                    'ClientCode'                => array_key_exists('ClientCode', $value) ? $this->ValidateLogStrings($value->ClientCode) : "",
                    'LoyaltyID'                 => array_key_exists('LoyaltyID', $value) ? $this->ValidateLogStrings($value->LoyaltyID) : "",
                    'SpecialRequests'           => array_key_exists('SpecialRequests', $value) ? $this->ValidateLogStrings($value->SpecialRequests) : "",
                    'GuestType'                 => array_key_exists('GuestType', $value) ? $this->ValidateLogStrings($value->GuestType) : "",
                    'Source'                    => array_key_exists('Source', $value) ? $this->ValidateLogStrings($value->Source) : "",
                    'CreditAvailable'           => array_key_exists('CreditAvailable', $value) ? $this->ValidateLogStrings($value->CreditAvailable) : "",
                    'FolioNumber'               => array_key_exists('FolioNumber', $value) ? $this->ValidateLogStrings($value->FolioNumber) : "",
                    'PostRestrictions'          => array_key_exists('PostRestrictions', $value) ? $this->ValidateLogStrings($value->PostRestrictions) : "",
                    'TelephoneRestrictions'     => array_key_exists('TelephoneRestrictions', $value) ? $this->ValidateLogStrings($value->TelephoneRestrictions) : "",
                    'MealPlan'                  => array_key_exists('MealPlan', $value) ? $this->ValidateLogStrings($value->MealPlan) : "",
                    'RateType'                  => array_key_exists('RateType', $value) ? $this->ValidateLogStrings($value->RateType) : "",
                    'TotalRateAmount'           => array_key_exists('TotalRateAmount', $value) ? $this->ValidateLogStrings($value->TotalRateAmount) : "",
                    'RoomRateAmountTaxes'       => array_key_exists('RoomRateAmountTaxes', $value) ? $this->ValidateLogStrings($value->RoomRateAmountTaxes) : "",
                    'ResortFee'                 => array_key_exists('ResortFee', $value) ? $this->ValidateLogStrings($value->ResortFee) : "",
                    'ResortFeeTaxes'            => array_key_exists('ResortFeeTaxes', $value) ? $this->ValidateLogStrings($value->ResortFeeTaxes) : "",
                    'HousekeepingFee'           => array_key_exists('HousekeepingFee', $value) ? $this->ValidateLogStrings($value->HousekeepingFee) : "",
                    'HousekeepingFeeTaxes'      => array_key_exists('HousekeepingFeeTaxes', $value) ? $this->ValidateLogStrings($value->HousekeepingFeeTaxes) : "",
                    'SpaFee'                    => array_key_exists('SpaFee', $value) ? $this->ValidateLogStrings($value->SpaFee) : "",
                    'SpaFeeTaxes'               => array_key_exists('SpaFeeTaxes', $value) ? $this->ValidateLogStrings($value->SpaFeeTaxes) : "",
                    'FirstRoomNightAmount'      => array_key_exists('FirstRoomNightAmount', $value) ? $this->ValidateLogStrings($value->FirstRoomNightAmount) : "",
                    'ReservationText'           => $text,
                    'BuildingCode'              => array_key_exists('BuildingCode', $value) ? $this->ValidateLogStrings($value->Room->BuildingCode) : "",
                    'RoomCode'                  => (isset($value->Room) && isset($value->Room->RoomCode) && is_string($value->Room->RoomCode) && !empty($value->Room->RoomCode)) ? $value->Room->RoomCode : "",
                    'RoomTypeCode'              => array_key_exists('RoomTypeCode', $value) ? $this->ValidateLogStrings($value->Room->RoomTypeCode) : "",
                    'EmailOptOut'               => array_key_exists('EmailOptOut', $value) ? $this->ValidateLogStrings($value->EmailOptOut) : "",
                    'RegularMailOptOut'         => array_key_exists('RegularMailOptOut', $value) ? $this->ValidateLogStrings($value->RegularMailOptOut) : "",
                    'RoomTypeDescription'       => array_key_exists('RoomTypeDescription', $value) ? $this->ValidateLogStrings($value->Room->RoomTypeDescription) : "",
                    'SharerReservationNumber'   => isset($value->SharerInfo) ? $this->ValidateLogStrings($value->SharerInfo->SharerReservationNumber) : '',
                    'Created_on_Date'           => date('Y-m-d'),
                    'Created_on_Time'           => date('H:i:s'),
                    'xml'                       => $this->xml,
                ];

                // \Log::info("-------------- Reservation --------------");
                // \Log::info(json_encode($Reservation));

                ReservationList::create($Reservation);
            }
        } catch (\Exception $e) {
            \Log::info("Error in reservation list function:");
            \log::error($e);
        }
    }

    private function CheckIn($data)
    {
        
        $Check_in_data = [];
        try {
            $is_array = is_array($data->CheckInData->GuestInfo);
            if ($is_array) {
                $Check_in_data = $data->CheckInData->GuestInfo;
            } else {
                $Check_in_data[] = $data->CheckInData->GuestInfo;
            }
            foreach ($Check_in_data as $value) {
                $text = '';
                if (array_key_exists('ReservationText', $value) && isset($value->ReservationText) && isset($value->ReservationText->Text)) {
                    $Data_Text = [];
                    if (!is_array($value->ReservationText->Text)) {
                        $Data_Text[] = $value->ReservationText->Text;
                    } else {
                        $Data_Text = $value->ReservationText->Text;
                    }
                    foreach ($Data_Text as $txt) {
                        $text .= $this->ValidateLogStrings($txt);
                    }
                }
                $Check_in = [
                    'HotelId'                   =>  array_key_exists('HotelId', $data) ?  $this->ValidateLogStrings($data->HotelId) : "",
                    'GuestName'                 =>  array_key_exists('GuestName', $value) ?  $this->ValidateLogStrings($value->GuestName) : "",
                    'LastName'                  =>  array_key_exists('LastName', $value) ?  $this->ValidateLogStrings($value->LastName) : "",
                    'FirstName'                 =>  array_key_exists('FirstName', $value) ?  $this->ValidateLogStrings($value->FirstName) : "",
                    'Salutation'                =>  array_key_exists('Salutation', $value) ?  $this->ValidateLogStrings($value->Salutation) : "",
                    'ZipCode'                   =>  array_key_exists('ZipCode', $value) ?  $this->ValidateLogStrings($value->ZipCode) : "",
                    'Country'                   =>  array_key_exists('Country', $value) ?  $this->ValidateLogStrings($value->Country) : "",
                    'EmailAddress'              =>  array_key_exists('EmailAddress', $value) ?  $this->ValidateLogStrings($value->EmailAddress) : "",
                    'Cell'                      =>  array_key_exists('Cell', $value) ?  $this->ValidateLogStrings($value->Cell) : "",
                    'Language'                  =>  array_key_exists('Language', $value) ?  $this->ValidateLogStrings($value->Language) : "",
                    'Vip'                       =>  array_key_exists('Vip', $value) ?  $this->ValidateLogStrings($value->Vip) : "",
                    'AccountNumber'             =>  array_key_exists('AccountNumber', $value) ?  $this->ValidateLogStrings($value->AccountNumber) : "",
                    'EmailOptOut'               =>  array_key_exists('EmailOptOut', $value) ?  $this->ValidateLogStrings($value->EmailOptOut) : "",
                    'RegularMailOptOut'         =>  array_key_exists('RegularMailOptOut', $value) ?  $this->ValidateLogStrings($value->RegularMailOptOut) : "",
                    'ReservationNumber'         =>  array_key_exists('ReservationNumber', $value) ?  $this->ValidateLogStrings($value->ReservationNumber) : "",
                    'ReservationNumberKey'      =>  array_key_exists('ReservationNumberKey', $value) ?  $this->ValidateLogStrings($value->ReservationNumberKey) : "",
                    'ArrivalDate'               =>  array_key_exists('ArrivalDate', $value) ?  $this->ValidateLogStrings($value->ArrivalDate) : "",
                    'DepartureDate'             =>  array_key_exists('DepartureDate', $value) ?  $this->ValidateLogStrings($value->DepartureDate) : "",
                    'BookingDate'               =>  array_key_exists('DepartureDate', $value) ?  $this->ValidateLogStrings($value->DepartureDate) : "",
                    'ReservationLastModifyDate' =>  array_key_exists('BookingDate', $value) ?  $this->ValidateLogStrings($value->BookingDate) : "",
                    'Adults'                    =>  array_key_exists('Adults', $value) ?  $this->ValidateLogStrings($value->Adults) : "",
                    'Children'                  =>  array_key_exists('Children', $value) ?  $this->ValidateLogStrings($value->Children) : "",
                    'BuildingCode'              =>  array_key_exists('BuildingCode', $value) ?  $this->ValidateLogStrings($value->BuildingCode) : "",
                    'RoomCode'                  =>  array_key_exists('RoomCode', $value) ?  $this->ValidateLogStrings($value->RoomCode) : "",
                    'RoomTypeCode'              =>  array_key_exists('RoomTypeCode', $value) ?  $this->ValidateLogStrings($value->RoomTypeCode) : "",
                    'RoomTypeDescription'       =>  array_key_exists('RoomTypeDescription', $value) ?  $this->ValidateLogStrings($value->RoomTypeDescription) : "",
                    'GuestSelection'            =>  array_key_exists('GuestSelection', $value) ?  $this->ValidateLogStrings($value->GuestSelection) : "",
                    'FolioNumber'               =>  array_key_exists('FolioNumber', $value) ?  $this->ValidateLogStrings($value->FolioNumber) : "",
                    'CreditAvailable'           =>  array_key_exists('CreditAvailable', $value) ?  $this->ValidateLogStrings($value->CreditAvailable) : "",
                    'PostRestrictions'          =>  array_key_exists('PostRestrictions', $value) ?  $this->ValidateLogStrings($value->PostRestrictions) : "",
                    'TelephoneRestrictions'     =>  array_key_exists('ThelephoneRestrictions', $value) ?  $this->ValidateLogStrings($value->ThelephoneRestrictions) : "",
                    'GroupTypeCode'             =>  array_key_exists('GroupTypeCode', $value) ?  $this->ValidateLogStrings($value->GroupTypeCode) : "",
                    'Source'                    =>  array_key_exists('Source', $value) ?  $this->ValidateLogStrings($value->Source) : "",
                    'SubSource'                 =>  array_key_exists('SubSource', $value) ?  $this->ValidateLogStrings($value->SubSource) : "",
                    'ComplimentaryUse'          =>  array_key_exists('ComplimentaryUse', $value) ?  $this->ValidateLogStrings($value->ComplimentaryUse) : "",
                    'HouseUse'                  =>  array_key_exists('HouseUse', $value) ?  $this->ValidateLogStrings($value->HouseUse) : "",
                    'MealPlan'                  =>  array_key_exists('MealPlan', $value) ?  $this->ValidateLogStrings($value->MealPlan) : "",
                    'RateType'                  =>  array_key_exists('RateType', $value) ?  $this->ValidateLogStrings($value->RateType) : "",
                    'TotalRateAmount'           =>  array_key_exists('TotalRateAmount', $value) ?  $this->ValidateLogStrings($value->TotalRateAmount) : "",
                    'TotalRateAmountTaxes'      =>  array_key_exists('TotalRateAmountTaxes', $value) ?  $this->ValidateLogStrings($value->TotalRateAmountTaxes) : "",
                    'RoomRateAmount'            =>  array_key_exists('RoomRateAmount', $value) ?  $this->ValidateLogStrings($value->RoomRateAmount) : "",
                    'RoomRateAmountTaxes'       =>  array_key_exists('RoomRateAmountTaxes', $value) ?  $this->ValidateLogStrings($value->RoomRateAmountTaxes) : "",
                    'ResortFee'                 =>  array_key_exists('ResortFee', $value) ?  $this->ValidateLogStrings($value->ResortFee) : "",
                    'ResortFeeTaxes'            =>  array_key_exists('ResortFeeTaxes', $value) ?  $this->ValidateLogStrings($value->ResortFeeTaxes) : "",
                    'HousekeepingFee'           =>  array_key_exists('HousekeepingFee', $value) ?  $this->ValidateLogStrings($value->HousekeepingFee) : "",
                    'HousekeepingFeeTaxes'      =>  array_key_exists('HousekeepingFeeTaxes', $value) ?  $this->ValidateLogStrings($value->HousekeepingFeeTaxes) : "",
                    'SpaFee'                    =>  array_key_exists('SpaFee', $value) ?  $this->ValidateLogStrings($value->SpaFee) : "",
                    'SpaFeeTaxes'               =>  array_key_exists('SpaFeeTaxes', $value) ?  $this->ValidateLogStrings($value->SpaFeeTaxes) : "",
                    'FirstRoomNightAmount'      =>  array_key_exists('FirstRoomNightAmount', $value) ?  $this->ValidateLogStrings($value->FirstRoomNightAmount) : "",
                    'ReservationText'           =>  $text,
                    'GroupReservation'          =>  array_key_exists('Group', $value) ?  $this->ValidateLogStrings($value->Group->GroupReservation) : "",
                    'GroupName'                 =>  array_key_exists('Group', $value) ?  $this->ValidateLogStrings($value->Group->Name) : "",
                    'Created_on_Date'           => date('Y-m-d'),
                    'Created_on_Time'           => date('H:i:s'),
                    'xml'                       => $this->xml,
                ];
                // if( $data->HotelId == '1803' || $data->HotelId == '2305' || $data->HotelId == '1802' || $data->HotelId == '1777') {
                //     \Log::info('------------------ Mensajes  MAESTRO checkin ----------------------');
                //     \Log::info(json_encode($Check_in));
                //     \Log::info('----------------------------------------');
                // }
                CheckIn::create($Check_in);
            }
        } catch (\Exception $e) {
            DB::rollback();
            \Log::info("CheckInReservationList Error:\n");
            \log::error($e);
            $success = false;
        }
    }

    private function CheckOut($checkOut)
    {
        try {
            
            $Check_Out_data = [];
            $is_array = is_array($checkOut->CheckOutData->GuestInfo);
            if ($is_array) {
                $Check_Out_data = $checkOut->CheckOutData->GuestInfo;
            } else {
                $Check_Out_data[] = $checkOut->CheckOutData->GuestInfo;
            }
            foreach ($Check_Out_data as $value) {
                $Check_Out = [
                    'HotelId'                   => array_key_exists('HotelId', $checkOut) ?  $this->ValidateLogStrings($checkOut->HotelId) : "",
                    'GuestName'                 => array_key_exists('GuestName', $value) ?  $this->ValidateLogStrings($value->GuestName) : "",
                    'LastName'                  => array_key_exists('LastName', $value) ? $this->ValidateLogStrings($value->LastName) : "",
                    'FirstName'                 => array_key_exists('FirstName', $value) ? $this->ValidateLogStrings($value->FirstName) : "",
                    'Salutation'                => array_key_exists('Salutation', $value) ? $this->ValidateLogStrings($value->Salutation) : "",
                    'ZipCode'                   => array_key_exists('ZipCode', $value) ? $this->ValidateLogStrings($value->ZipCode) : "",
                    'Country'                   => array_key_exists('Country', $value) ? $this->ValidateLogStrings($value->Country) : "",
                    'EmailAddress'              => array_key_exists('EmailAddress', $value) ? $this->ValidateLogStrings($value->EmailAddress) : "",
                    'Cell'                      => array_key_exists('Cell', $value) ? $this->ValidateLogStrings($value->Cell) : "",
                    'GuestCellNumber'           => array_key_exists('GuestCellNumber', $value) ? $this->ValidateLogStrings($value->GuestCellNumber) : "",
                    'Language'                  => array_key_exists('Language', $value) ? $this->ValidateLogStrings($value->Language) : "",
                    'Vip'                       => array_key_exists('Vip', $value) ? $this->ValidateLogStrings($value->Vip) : "",
                    'AccountNumber'             => array_key_exists('AccountNumber', $value) ? $this->ValidateLogStrings($value->AccountNumber) : "",
                    'EmailOptOut'               => array_key_exists('EmailOptOut', $value) ? $this->ValidateLogStrings($value->EmailOptOut) : "",
                    'RegularMailOptOut'         => array_key_exists('RegularMailOptOut', $value) ? $this->ValidateLogStrings($value->RegularMailOptOut) : "",
                    'ReservationNumber'         => array_key_exists('ReservationNumber', $value) ? $this->ValidateLogStrings($value->ReservationNumber) : "",
                    'ReservationNumberKey'      => array_key_exists('ReservationNumberKey', $value) ? $this->ValidateLogStrings($value->ReservationNumberKey) : "",
                    'ArrivalDate'               => array_key_exists('ArrivalDate', $value) ? $this->ValidateLogStrings($value->ArrivalDate) : "",
                    'DepartureDate'             => array_key_exists('DepartureDate', $value) ? $this->ValidateLogStrings($value->DepartureDate) : "",
                    'BookingDate'               => array_key_exists('BookingDate', $value) ? $this->ValidateLogStrings($value->BookingDate) : "",
                    'ReservationLastModifyDate' => array_key_exists('ReservationLastModifyDate', $value) ? $this->ValidateLogStrings($value->ReservationLastModifyDate) : "",
                    'BuildingCode'              => array_key_exists('BuildingCode', $value) ? $this->ValidateLogStrings($value->BuildingCode) : "",
                    'RoomCode'                  => array_key_exists('RoomCode', $value) ? $this->ValidateLogStrings($value->RoomCode) : "",
                    'RoomTypeCode'              => array_key_exists('RoomTypeCode', $value) ? $this->ValidateLogStrings($value->RoomTypeCode) : "",
                    'RoomTypeDescription'       => array_key_exists('RoomTypeDescription', $value) ? $this->ValidateLogStrings($value->RoomTypeDescription) : "",
                    'GroupReservation'          => array_key_exists('Group', $value) ? $this->ValidateLogStrings($value->Group->GroupReservation) : "",
                    'GroupName'                 => array_key_exists('Group', $value) ? $this->ValidateLogStrings($value->Group->Name) : "",
                    'Created_on_Date'           => date('Y-m-d'),
                    'Created_on_Time'           => date('H:i:s'),
                    'xml'                       => $this->xml,

                ];
                CheckOut::create($Check_Out);
            }
        } catch (\Exception $e) {
            \Log::info("Check out error $checkOut->HotelId :");
            \log::error($e);
        }
    }

    private function HousekeepingStatus($data)
    {
        try {
            $House_Data = [];
            $is_array = is_array($data->Rooms->HousekeepingData);
            if ($is_array) {
                $House_Data = $data->Rooms->HousekeepingData;
            } else {
                $House_Data[] = $data->Rooms->HousekeepingData;
            }
            foreach ($House_Data as $value) {
                $House_keeping = [
                    'HotelId'                       => array_key_exists('HotelId', $data) ? $this->ValidateLogStrings($data->HotelId) : "",
                    'BuildingCode'                  => array_key_exists('BuildingCode', $value) ? $this->ValidateLogStrings($value->BuildingCode) : "",
                    'RoomCode'                      => array_key_exists('RoomCode', $value) ? $this->ValidateLogStrings($value->RoomCode) : "",
                    'RoomType'                      => array_key_exists('RoomType', $value) ? $this->ValidateLogStrings($value->RoomType) : "",
                    'RoomStatus'                    => array_key_exists('RoomStatus', $value) ? $this->ValidateLogStrings($value->RoomStatus) : "",
                    'HousekeepingStatus'            => array_key_exists('HousekeepingStatus', $value) ? $this->ValidateLogStrings($value->HousekeepingStatus) : "",
                    'HousekeepingStatusDescription' => array_key_exists('HousekeepingStatusDescription', $value) ? $this->ValidateLogStrings($value->HousekeepingStatusDescription) : "",
                    'Created_on_Date'           => date('Y-m-d'),
                    'Created_on_Time'           => date('H:i:s'),
                    'xml'                       => $this->xml,

                ];
                HousekeepingStatus::create($House_keeping);
            }
        } catch (\Exception $e) {
            \Log::info("Error HousekeepingStatus:");
            \log::error($e);
        }
    }


    private function OffMarket($data)
    {
        try {
            $offMarket = [];
            if (is_array($data->Rooms->OffmarketData)) {
                $offMarket = $data->Rooms->OffmarketData;
            } else {
                $offMarket[] = $data->Rooms->OffmarketData;
            }

            foreach ($offMarket as $value) {
                $text = '';
                if (array_key_exists('OffmarketText', $value) && isset($value->OffmarketText) && isset($value->OffmarketText->Text)) {
                    $Data_text = $value->OffmarketText->Text;
                    if (is_array($value->OffmarketText->Text)) {
                        $Data_text = $value->OffmarketText->Text;
                    } else {
                        $Data_text = [$value->OffmarketText->Text];
                    }
                    foreach ($Data_text as $txt) {
                        $text .= $this->ValidateLogStrings($txt);
                    }
                }
                $off_Market = [
                    'HotelId'           => array_key_exists('HotelId', $data) ? $this->ValidateLogStrings($data->HotelId) : "",
                    'OffmarketRes'      => array_key_exists('OffmarketRes', $value) ? $this->ValidateLogStrings($value->OffmarketRes) : "",
                    'BuildingCode'      => array_key_exists('BuildingCode', $value) ? $this->ValidateLogStrings($value->BuildingCode) : "",
                    'RoomType'          => array_key_exists('RoomType', $value) ? $this->ValidateLogStrings($value->RoomType) : "",
                    'RoomTypeCode'      => array_key_exists('RoomTypeCode', $value) ? $this->ValidateLogStrings($value->RoomTypeCode) : "",
                    'StartDate'         => array_key_exists('StartDate', $value) ? $this->ValidateLogStrings($value->StartDate) : "",
                    'EndDate'           => array_key_exists('EndDate', $value) ? $this->ValidateLogStrings($value->EndDate) : "",
                    'OffmarketFlag'     => array_key_exists('OffmarketFlag', $value) ? $this->ValidateLogStrings($value->OffmarketFlag) : "",
                    'OutOfInventoryFlag' => array_key_exists('OutOfInventoryFlag', $value) ? $this->ValidateLogStrings($value->OutOfInventoryFlag) : "",
                    'OffmarketText'     => $text,
                    'Created_on_Date'           => date('Y-m-d'),
                    'Created_on_Time'           => date('H:i:s'),
                    'xml'                       => $this->xml,
                ];
                Offmarket::create($off_Market);
            }
        } catch (\Exception $e) {
            \Log::info("Error Offmarket:");
            \log::error($e);
            DB::rollback();
        }
    }

    private function ValidateLogStrings($data)
    {
        return (isset($data) && is_string($data) && !empty($data)) ? $data : "";
    }
}
