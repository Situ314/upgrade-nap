<?php

namespace App\Http\Controllers\v1;

use Illuminate\Support\Arr;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\GuestCheckinDetails;
use DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Validator;

class EventsController extends Controller
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

        if (! $request->exists('hotel_id')) {
            return response()->json([
                'error' => 'Hotel id not provided',
            ], 400);
        }

        $hotel_id = $request->hotel_id;

        if (! $this->validateHotelId($hotel_id, $staff_id)) {
            return response()->json([
                'error' => 'User does not have access to the hotel',
            ], 400);
        }

        $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 1, $action = 'view');

        if (! $permission) {
            return response()->json([
                'error' => 'User does not have permission to perform this action',
            ], 400);
        }

        $data = Event::select([
            'event_id',
            'guest_id',
            'room_id',
            'issue',
            'dept_tag_id',
        ])
            ->with([
                'Guest' => function ($query) {
                    return $query
                        ->select([
                            'guest_id',
                            'firstname',
                            'lastname',
                            'email_address',
                        ]);
                },
                'Room' => function ($query) {
                    return $query
                        ->select([
                            'room_id',
                            'location',
                        ]);
                },
                'DepTag.departament',
                'DepTag.tag',
            ])
            ->where(function ($query) use ($hotel_id) {
                $query
                    ->where('hotel_id', $hotel_id)
                    ->where('active', 1);
            })
            ->paginate($paginate);

        return response()->json($data, 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            if (! $request->exists('hotel_id')) {
                return response()->json([
                    'create' => false,
                    'message' => 'Hotel id not provided',
                    'description' => [],
                ], 400);
            }
            $hotel_id = $request->hotel_id;
            $staff_id = $request->user()->staff_id;

            $this->configTimeZone($hotel_id);

            if (! $this->validateHotelId($hotel_id, $staff_id)) {
                return response()->json([
                    'create' => false,
                    'message' => 'User does not have access to the hotel',
                    'description' => [],
                ], 400);
            }

            $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 1, $action = 'create');
            if (! $permission) {
                return response()->json([
                    'create' => false,
                    'message' => 'User does not have permission to perform this action',
                    'description' => [],
                ], 400);
            }

            if (! $request->exists('events')) {
                return response()->json([
                    'create' => false,
                    'message' => 'event object, data not provided',
                    'description' => [],
                ], 400);
            }
            //Tratamineto de la informacion, con el objetivo de evitar llenar otros datos
            $event = collect($request->events);
            $event = $event->only([
                'issue',
                'dept_tag_id',
                'room_id',
                'location',
                'guest_id',
                'priority',
            ]);
            $event = $event->all();

            $validation = Validator::make($event, [
                'issue' => 'string',
                'dept_tag_id' => [
                    'numeric',
                    Rule::exists('dept_tag')->where(function ($query) use ($hotel_id) {
                        $query->where('hotel_id', $hotel_id);
                    }),
                ],
                'room_id' => [
                    'numeric',
                    'required_without:guest_id',
                    Rule::exists('hotel_rooms')->where(function ($query) use ($hotel_id) {
                        $query->where('hotel_id', $hotel_id);
                    }),
                ],
                'location' => [
                    'string',
                    'required_without:room_id',
                    Rule::exists('hotel_rooms')->where(function ($query) use ($hotel_id) {
                        $query->where('hotel_id', $hotel_id);
                    }),
                ],
                'guest_id' => [
                    'numeric',
                    'required_without:room_id',
                    Rule::exists('guest_registration')->where(function ($query) use ($hotel_id) {
                        $query->where('hotel_id', $hotel_id);
                    }),
                ],
                'priority' => [
                    'required',
                    'numeric',
                    Rule::in([1, 2, 3]),
                ],
            ]);

            if ($validation->fails()) {
                return response()->json([
                    'create' => false,
                    'message' => 'event object, failed validation',
                    'description' => $validation->errors(),
                ], 400);
            }

            $last_event = Event::where('hotel_id', $hotel_id)
                ->orderBy('event_id', 'DESC')
                ->first();
            $count_by_hotel_id = 0;
            if ($last_event) {
                $count_by_hotel_id = $last_event->count_by_hotel_id + 1;
            }
            $room_id = Arr::get($event, 'room_id', '');
            $location = Arr::get($event, 'location', '');
            $guest_id = Arr::get($event, 'guest_id', '');

            if (empty($room_id)) {
                $room = $this->getRoom($hotel_id, $staff_id, $location);
                $room_id = $room['room_id'];
            } else {
                $hotel_room = \App\Models\HotelRoom::where(function ($query) use ($room_id, $hotel_id) {
                    $query
                        ->where('room_id', $room_id)
                        ->where('hotel_id', $hotel_id);
                })
                    ->first();

                if (! $hotel_room) {
                    return response()->json([
                        'create' => false,
                        'message' => 'Room id does not exist in the hotel',
                        'description' => [],
                    ], 400);
                }
            }

            $now = date('Y-m-d H:i:s');

            $guest_checkin_details = GuestCheckinDetails::select('room_no', 'guest_id')
                ->where(function ($query) use ($room_id, $guest_id, $now, $hotel_id) {
                    $query
                        ->where('status', 1)
                        ->where('hotel_id', $hotel_id)
                        ->whereRaw(DB::raw("'$now' >= check_in and '$now' <= check_out"));

                    if (! empty($room_id)) {
                        $query->where('room_no', $room_id);
                    } else {
                        $query->where('guest_id', $guest_id);
                    }
                })
                ->orderBy('sno', 'DESC')
                ->first();

            if ($guest_checkin_details) {
                if (empty($room_id)) {
                    $event['room_no'] = $guest_checkin_details->room_no;
                } else {
                    $event['guest_id'] = $guest_checkin_details->guest_id;
                }
            }

            $event['hotel_id'] = $hotel_id;
            $event['issue'] = $this->proccessString($event['issue']);
            $event['date'] = date('Y-m-d');
            $event['time'] = date('H:i:s');
            $event['closed_by'] = 0;
            $event['count_by_hotel_id'] = $count_by_hotel_id;
            $event['created_by'] = $staff_id;
            $event['created_on'] = $now;

            $event_id = Event::create($event)->event_id;

            $this->saveLogTracker([
                'module_id' => 1,
                'action' => 'add',
                'prim_id' => $event_id,
                'staff_id' => $request->user()->staff_id,
                'date_time' => $now,
                'comments' => Arr::get($event, 'issue', ''),
                'hotel_id' => $hotel_id,
                'type' => 'API',
            ]);

            DB::commit();

            return response()->json([
                'create' => true,
                'event_id' => $event_id,
                'message' => '',
                'description' => [],
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'create' => false,
                'message' => 'Bad request',
                'description' => ["$e"],
            ], 400);
        }
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
            if (! $request->exists('hotel_id')) {
                return response()->json([
                    'update' => false,
                    'message' => 'Hotel id not provided',
                    'description' => [],
                ], 400);
            }
            $hotel_id = $request->hotel_id;
            $staff_id = $request->user()->staff_id;

            $this->configTimeZone($hotel_id);

            $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 1, $action = 'update');

            if (! $this->validateHotelId($hotel_id, $staff_id)) {
                return response()->json([
                    'update' => false,
                    'message' => 'User does not have access to the hotel',
                    'description' => [],
                ], 400);
            }
            if (! $permission) {
                return response()->json([
                    'update' => false,
                    'message' => 'User does not have permission to perform this action',
                    'description' => [],
                ], 400);
            }

            $event = \App\Models\Event::where('hotel_id', $hotel_id)->where('event_id', $id)->first();
            if (! $event) {
                return response()->json([
                    'update' => false,
                    'message' => 'Record not found',
                    'description' => [],
                ], 400);
            }

            if (! $request->exists('events')) {
                return response()->json([
                    'update' => false,
                    'message' => 'event object, data not provided',
                    'description' => [],
                ], 400);
            }
            $event_update = collect($request->events);
            $event_ipdate = $event_update->only([
                'issue',
                'dept_tag_id',
                'room_id',
                'location',
                'guest_id',
                'priority',
            ]);
            $event_update = $event_ipdate->all();

            $validation = Validator::make($event_update, [
                'issue' => 'string',
                'dept_tag_id' => [
                    'numeric',
                    Rule::exists('dept_tag')->where(function ($query) use ($hotel_id) {
                        $query->where('hotel_id', $hotel_id);
                    }),
                ],
                'room_id' => [
                    'numeric',
                    'required_without:guest_id',
                    Rule::exists('hotel_rooms')->where(function ($query) use ($hotel_id) {
                        $query->where('hotel_id', $hotel_id);
                    }),
                ],
                'location' => [
                    'string',
                    'required_without:room_id',
                    Rule::exists('hotel_rooms')->where(function ($query) use ($hotel_id) {
                        $query->where('hotel_id', $hotel_id);
                    }),
                ],
                'guest_id' => [
                    'numeric',
                    'required_without:room_id',
                    Rule::exists('guest_registration')->where(function ($query) use ($hotel_id) {
                        $query->where('hotel_id', $hotel_id);
                    }),
                ],
                'priority' => [
                    'required',
                    'numeric',
                    Rule::in([1, 2, 3]),
                ],
            ]);

            if ($validation->fails()) {
                return response()->json([
                    'update' => false,
                    'message' => 'event object, failed validation',
                    'description' => $validation->errors(),
                ], 400);
            }

            $room_id = $event_update['room_id'];
            $guest_id = $event_update['guest_id'];

            $now = date('Y-m-d H:i:s');
            $check_id = $event->created_on;

            $guest_checkin_details = GuestCheckinDetails::select('room_no', 'guest_id')
                ->where(function ($query) use ($room_id, $guest_id, $hotel_id, $check_id) {
                    $query
                        ->where('status', 1)
                        ->where('hotel_id', $hotel_id)
                        ->whereRaw(DB::raw("'$check_id' >= check_in and '$check_id' <= check_out"));

                    if (! empty($room_id)) {
                        $query->where('room_no', $room_id);
                    } else {
                        $query->where('guest_id', $guest_id);
                    }
                })
                ->orderBy('sno', 'DESC')
                ->first();

            if ($guest_checkin_details) {
                if (empty($room_id)) {
                    $event['room_no'] = $guest_checkin_details->room_no;
                } else {
                    $event['guest_id'] = $guest_checkin_details->guest_id;
                }
            }

            $event->fill($event_update);
            $event->updated_by = $staff_id;
            $event->updated_on = $now;
            $event->save();

            $this->saveLogTracker([
                'module_id' => 1,
                'action' => 'update',
                'prim_id' => $id,
                'staff_id' => $staff_id,
                'date_time' => $now,
                'comments' => $event->issue,
                'hotel_id' => $hotel_id,
                'type' => 'API',
            ]);
            DB::commit();

            return response()->json([
                'update' => true,
                'message' => '',
                'description' => [],
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'update' => false,
                'message' => 'Bad request',
                'description' => "$e",
            ], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        DB::beginTransaction();
        try {
            if (! $request->exists('hotel_id')) {
                return response()->json([
                    'delete' => false,
                    'message' => 'Hotel id not provided',
                    'description' => [],
                ], 400);
            }
            $hotel_id = $request->hotel_id;
            $staff_id = $request->user()->staff_id;

            $this->configTimeZone($hotel_id);
            $now = date('Y-m-d H:i:s');

            if (! $this->validateHotelId($hotel_id, $staff_id)) {
                return response()->json([
                    'delete' => false,
                    'message' => 'User does not have access to the hotel',
                    'description' => [],
                ], 400);
            }

            $permission = $this->getPermission($hotel_id, $staff_id, $menu_id = 1, $action = 'delete');
            if (! $permission) {
                return response()->json([
                    'delete' => false,
                    'message' => 'User does not have permission to perform this action',
                    'description' => [],
                ], 400);
            }

            $event = \App\Models\Event::where('hotel_id', $hotel_id)->where('event_id', $id)->first();
            if ($event) {
                $event->active = 2;
                $event->save();
                $this->saveLogTracker([
                    'module_id' => 1,
                    'action' => 'delete',
                    'prim_id' => $event->event_id,
                    'staff_id' => $staff_id,
                    'date_time' => $now,
                    'comments' => '',
                    'hotel_id' => $hotel_id,
                    'type' => 'API',
                ]);
            }
            DB::commit();

            return response()->json([
                'delete' => true,
                'message' => '',
                'description' => [],
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'delete' => false,
                'message' => 'Record not found',
                'description' => "$e",
            ], 400);
        }
    }
}
