<?php

namespace App\Http\Controllers\v2;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Departament;

class DeparmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $paginate   = isset($request->paginate) ? $request->paginate : 50;
        $type       = isset($request->type) ? $request->type : "ALL";
        $staff_id   = $request->user()->staff_id;
        $hotel_id   = $request->hotel_id;
        $get_tags   = isset($request->get_tags) ? $request->get_tags : "";

        if(!$this->validateHotelId($hotel_id, $staff_id)) {
            return response()->json( [], 400 );
        }

        $Departament = Departament::select(["dept_id", "dept_name", "short_name"])->where('hotel_id',$hotel_id);

        if(strtoupper($type) == "ANGEL" ) {
            $Departament = $Departament->where('tag_type', 2);
        } else if(strtoupper($type) == "DESKTOP" ) {
            $Departament = $Departament->where('tag_type', 1);
        }

        if(!empty($get_tags) && strtoupper($get_tags) == "TRUE" ) {
            $Departament = $Departament->with('tags');
        }

        $Departament = $Departament->paginate($paginate);

        return response()->json($Departament, 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $staff_id   = $request->user()->staff_id;
        $hotel_id   = $request->hotel_id;

        if(!$this->validateHotelId($hotel_id, $staff_id)) {
            return response()->json( [], 400 );
        }

        $Departament = Departament::with('tags')->find($id);

        return response()->json($Departament, 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        return "[method: PUT] this option is disabled, go to the documentation for more information";
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        return "[method: DELETE] this option is disabled, go to the documentation for more information";
    }
}
