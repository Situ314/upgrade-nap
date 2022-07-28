<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class MaintenanceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        /**
         * captura de parametros iniciales
         */
        $page = (int) $request->page ?: 1;
        $paginate = $request->paginate ?: 50;
        $paginate = $paginate > 500 ? 500 : $paginate;
        $staff_id = $request->user()->staff_id;
        /**
         * Validar hotel
         * */
        if (! $request->exists('hotel_id')) {
            return response()->json(['error' => 'Hotel id not provided'], 400);
        }
        $hotel_id = $request->hotel_id;
        /**
         * Validar acceso al hotel x usuario
         */
        if (! $this->validateHotelId($hotel_id, $staff_id)) {
            return response()->json(['error' => 'User does not have access to the hotel'], 400);
        }
        /**
         *  Validar que el usuario tenga permisos para realizar esta operacion
         */
        $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 33, $action = 'view');
        if (! $permission) {
            return response()->json(['error' => 'User does not have permission to perform this action'], 400);
        }
        /**
         * Configurar timezone y capturar fecha
         */
        $this->configTimeZone($hotel_id);

        $paginate = $request->paginate ?: 50;

        $now = date('Y-m-d H:i:s');

        $__page = $page - 1;
        if ($__page > 0) {
            $__page = $__page * $paginate;
        }

        $total_record_query = "SELECT count(maintenance_records_id) as total FROM maintenance_records AS m WHERE hotel_id = $hotel_id";
        $rsTotal = \DB::select($total_record_query);
        if ($rsTotal) {
            $rsTotal = $rsTotal[0];
        }
        $total = $rsTotal->total;

        $query = "SELECT
            m.maintenance_records_id as id, 
            CASE WHEN maintenance_type = 0 THEN 'item' ELSE 'location' END as type, 
            m.room_id, 
            start_date, 
            due_date as end_date, 
            CASE WHEN priority = 1 THEN 'low' WHEN priority = 2 THEN 'medium' WHEN priority = 3 THEN 'high' ELSE 'no priority' END as priority 
        FROM maintenance_records AS m ";

        if ($request->exists('room_id') || $request->exists('location')) {
            $query .= 'INNER JOIN hotel_rooms AS h ON h.hotel_id = m.hotel_id AND h.room_id = m.room_id ';
        }

        $query .= "WHERE m.hotel_id = $hotel_id ";

        if ($request->exists('start_date') && $request->exists('end_date')) {
            $query .= '';
        }

        if ($request->exists('room_id') || $request->exists('location')) {
            if ($request->exists('room_id')) {
                $room_id = $request->room_id;
                $w = "h.room_id = $room_id ";
            } else {
                $location = $request->location;
                $w = "h.location = $location ";
            }
            $query .= "AND $w";
        }

        $query .= "LIMIT $__page, $paginate";

        $rows = \DB::select($query);

        if (count($rows) > 0) {
            $ids = '';
            foreach ($rows as $rowkey => $row) {
                $ids .= "$row->id,";
            }
            $ids = substr($ids, 0, -1);

            $items_query = "SELECT
                ri.maintenance_record_id, i.item_id, i.name
            FROM
                maintenance_records_items AS ri
                INNER JOIN
                maintenance_items AS i ON i.item_id = ri.item_id
            WHERE
            ri.hotel_id = $hotel_id
            AND ri.maintenance_record_id IN ($ids)
            ORDER BY ri.maintenance_record_id";

            $items_rows = \DB::select($items_query);

            $room_query = "SELECT 
                h.room_id, h.location, m.maintenance_records_id
            FROM
                hotel_rooms AS h
                    INNER JOIN
                maintenance_records AS m ON m.room_id = h.room_id
            WHERE
                h.hotel_id = $hotel_id and m.maintenance_records_id in ($ids)
            ";
            $room_rows = \DB::select($room_query);

            foreach ($rows as $rowkey => $row) {
                $id = $row->id;
                $__data = Arr::where($items_rows, function ($value, $key) use ($id) {
                    return $value->maintenance_record_id == $id;
                });
                $row->assets = array_values($__data);

                $__data = Arr::where($room_rows, function ($value, $key) use ($id) {
                    return $value->maintenance_records_id == $id;
                });
                if (count($__data) > 0) {
                    $r = array_values($__data)[0];

                    $row->room = [
                        'room_id' => $r->room_id,
                        'location' => $r->location,
                    ];
                }
            }
        }

        $last_page = ceil($total / $paginate);

        $__page = [
            'current_page' => $page,
            'data' => $rows,
            'from' => $__page + 1,
            'last_page' => $last_page,
            'next_page_url' => \Request::url().'?page='.($page + 1 == $last_page ? $last_page : $page + 1),
            'path' => \Request::url().($page == 1 ? '' : '?page='.$page),
            'per_page' => $paginate,
            'prev_page_url' => $page == 1 ? null : \Request::url().'?page='.($page - 1),
            'to' => $paginate * $page,
            'total' => $total,
        ];

        return response()->json($__page, 200);
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
        //
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
