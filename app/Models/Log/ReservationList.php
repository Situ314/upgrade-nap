<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class ReservationList extends Model
{
    protected $connection = 'integrationsLogs';

    protected $table = 'ReservationList';

    public $timestamps = false;

    protected $fillable = [
        'HotelId',
        'ReservationNumber',
        'ReservationNumberKey',
        'Adults',
        'Children',
        'ArrivalDate',
        'DepartureDate',
        'Nights',
        'Status',
        'ReservationStatus',
        'CurrencyCode',
        'RoomRevenue',
        'FoodRevenue',
        'BanquetRevenue',
        'OtherRevenue',
        'GroupReservation',
        'GroupName',
        'GuestName',
        'Salutation',
        'FirstName',
        'LastName',
        'MiddleInitial',
        'MiddleName',
        'Street',
        'Address2',
        'City',
        'State',
        'ZipCode',
        'Country',
        'EmailAddress',
        'Email',
        'Phone',
        'Fax',
        'Company',
        'Language',
        'Vip',
        'ClientCode',
        'LoyaltyID',
        'SpecialRequests',
        'GuestType',
        'Source',
        'CreditAvailable',
        'FolioNumber',
        'PostRestrictions',
        'TelephoneRestrictions',
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
        'BuildingCode',
        'RoomCode',
        'RoomTypeCode',
        'EmailOptOut',
        'RegularMailOptOut',
        'RoomTypeDescription',
        'SharerReservationNumber',
        'Created_on_Date',
        'Created_on_Time',
        'xml',
    ];
}
