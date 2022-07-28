<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HotelsController extends Controller
{
    public function index(Request $request)
    {
        $staff_id = $request->user()->staff_id;
        $hotels = \DB::select('SELECT 
        h.hotel_id, 
        h.hotel_name, 
        l.abbreviation AS lang
        FROM staff_hotels AS sh 
        INNER JOIN hotels AS h ON sh.hotel_id = h.hotel_id 
        LEFT JOIN languages l on l.language_id = h.language_id 
        WHERE sh.staff_id = ? 
        ORDER BY h.hotel_name ASC', [$staff_id]);

        return response()->json($hotels, 200);
    }
}
