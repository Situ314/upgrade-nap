<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HousekeepingCleaningController extends Controller
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

        if ($this->validateHotelId($hotel_id, $staff_id)) {
            $data = \App\Models\HousekeepingCleanings::select('cleaning_id', 'room_id', 'hk_status', 'front_desk_status', 'created_on')
                ->where('hotel_id', $hotel_id)
                ->with([
                    'Room' => function ($query) {
                        $query->select('location', 'room_id');
                    },
                ])
                ->distinct()
                ->paginate($paginate);

            return response()->json($data, 200);
        }

        return response()->json([], 400);
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
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        DB::beginTransaction();
        try {
            $hkc = \App\Models\HousekeepingCleanings::find($id);
            if ($hkc) {
                /* Validate send object */
                if (! isset($request->housekeeping_cleaning)) {
                    return response()->json([
                        'update' => false,
                        'message' => 'housekeeping_cleaning object, data not provided',
                        'description' => [],
                    ], 400);
                }
                /* configure timezone  by hotel */
                $this->configTimeZone($hkc->hotel_id);

                $hkc_old = $request->housekeeping_cleaning;
                $hkc->front_desk_status = $hkc_old['front_desk_status'];
                $hkc->hk_status = $hkc_old['hk_status'];
                $hkc->updated_by = $request->user()->staff_id;
                $hkc->updated_on = date('Y-m-d H:i:s');
                $hkc->save();

                DB::commit();
                $success = true;
            } else {
                DB::rollback();

                return response()->json([
                    'update' => false,
                    'message' => 'Record not foun',
                    'description' => [],
                ], 400);
            }
        } catch (\Exception $e) {
            $error = $e;
            $success = false;
            DB::rollback();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
