<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $hotel_id = $request->hotel_id;
        $staff_id = $request->user()->staff_id;

        if (! $this->validateHotelId($hotel_id, $staff_id)) {
            return response()->json([], 400);
        }

        if (isset($hotel_id)) {
            $roles = Role::where('hotel_id', $hotel_id)->get();
            if ($roles) {
                return response()->json([
                    'data' => $roles,
                ], 200);
            }
        } else {
            $roles = [];

            return response()->json([
                'data' => $roles,
            ], 400);
        }
    }
}
