<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class CheckIn extends Model
{
    protected $connection   = 'integrationsLogs';
    protected $table        = 'CheckIn';
    public $timestamps      = false;
    protected $fillable     = [
        'HotelId',
        'GuestName',
        'LastName',
        'FirstName',
        'Salutation',
        'ZipCode',
        'Country',
        'EmailAddress',
        'Cell',
        'Language',
        'Vip',
        'AccountNumber',
        'EmailOptOut',
        'RegularMailOptOut',
        'ReservationNumber',
        'ReservationNumberKey',
        'ArrivalDate',
        'DepartureDate',
        'BookingDate',
        'ReservationLastModifyDate',
        'Adults',
        'Children',
        'BuildingCode',
        'RoomCode',
        'RoomTypeCode',
        'RoomTypeDescription',
        'GuestSelection',
        'FolioNumber',
        'CreditAvailable',
        'PostRestrictions',
        'TelephoneRestrictions',
        'GroupTypeCode',
        'Source',
        'SubSource',
        'ComplimentaryUse',
        'HouseUse',
        'MealPlan',
        'RateType',
        'TotalRateAmount',
        'TotalRateAmountTaxes',
        'RoomRateAmount',
        'RoomRateAmountTaxes',
        'ResortFee',
        'ResortFeeTaxes',
        'HousekeepingFee',
        'HousekeepingFeeTaxes',
        'SpaFee',
        'SpaFeeTaxes',
        'FirstRoomNightAmount',
        'ReservationText',
        'GroupReservation',
        'GroupName',
        'Created_on_Date',
        'Created_on_Time',
        'xml'
    ];
}
