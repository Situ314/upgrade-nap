<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DeptTagController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $paginate = isset($request->paginate) ? $request->paginate : 50;

        $staff_id = $request->user()->staff_id;
        $hotel_id = $request->hotel_id;

        if (! $this->validateHotelId($hotel_id, $request->user()->staff_id)) {
            return response()->json([], 400);
        }

        if ($this->validateHotelId($hotel_id, $staff_id)) {
            $deptTag = \App\Models\DeptTag::where('hotel_id', $hotel_id)
                ->select(['dept_tag_id', 'dept_id', 'tag_id'])
                ->with('departament', 'tag')
                ->paginate($paginate);

            return response()->json($deptTag, 200);
        } else {
            return response()->json([], 400);
        }
    }
}
