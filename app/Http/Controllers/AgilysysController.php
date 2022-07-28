<?php

namespace App\Http\Controllers;

use App\Model\IntegrationsActive;
use Illuminate\Http\Request;
use Storage;

class AgilysysController extends Controller
{
    public function index(Request $request)
    {
        $integrations_active = IntegrationsActive::where(function ($query) {
            $query
                ->where('int_id', 11)
                ->where('state', 1);
        })
            ->get();
        $integrations_active = $integrations_active->where('config.hotel_id', 'Nuvola')->first();

        $allFiles = Storage::file('/agilisys');
        $matchingFiles = preg_grep('/^'.$integrations_active->config['hotel_id'].'_InHouseReservations\./', $allFiles);
        foreach ($matchingFiles as $path) {
            Log::info($path);
        }
    }
}
