<?php

namespace App\Http\Controllers\v3;

use App\Helpers\MainHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Validator;

class ReservationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $staff_id = $request->user()->staff_id;
        $hotel_id = isset($request->hotel_id) ? $request->hotel_id : null;
        $rooms = isset($request->rooms) ? $request->rooms : null;
        $roomIdsList = [];

        $reservation_status = 1;

        if (! $this->validateHotelId($hotel_id, $staff_id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'User does not have access to the hotel',
                'errors' => null,
            ], 400);
        }

        if (! is_null($rooms)) {
            $rooms = explode(',', $rooms);
            if (count($rooms) > 0) {
                $hotelRooms = \App\Models\HotelRoom::select(['location', 'room_id'])->where('hotel_id', $hotel_id)->whereIn('location', $rooms)->get();
                if (count($hotelRooms) > 0) {
                    foreach ($hotelRooms as $key => $value) {
                        $roomIdsList[] = $value->room_id;
                    }
                }
            }
        }

        $this->configTimeZone($hotel_id);

        $reservations = \App\Models\GuestCheckinDetails::with(['GuestPms', 'Room'])->where('hotel_id', $request->hotel_id);

        if (count($roomIdsList) > 0) {
            $reservations->whereIn('room_no', $roomIdsList);
        }

        if (! is_null($reservation_status)) {
            $reservations->where('reservation_status', $reservation_status);
        }

        $_data = $reservations->get();
        $data = [];

        foreach ($_data as $key => $value) {
            // dd($value);
            $guest_number = $value->GuestPms->guest_number;

            if (! isset($data[$guest_number])) {
                $data[$guest_number]['guest_number'] = $guest_number;
            }
            if (! isset($data[$guest_number])) {
                $data[$guest_number]['reservation'] = [];
            }

            $data[$guest_number]['reservation'][] = [
                'reservation_number' => $value->reservation_number,
                'reservation_status' => $value->reservation_status,
                'check_in' => $value->check_in,
                'check_out' => $value->check_out,
                'comment' => $value->comment,
                'room' => $value->Room->location,
            ];
        }

        $reservatons = [];
        foreach ($data as $key => $value) {
            $reservatons[] = $value;
        }

        return response()->json([
            'status' => 'success',
            'message' => '',
            'data' => $reservatons,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Capturar hotel id, por default el valor es null, en caso de no enviarlo

        $hotel_id = isset($request->hotel_id) ? $request->hotel_id : null;

        $validator = Validator::make($request->all(), [
            'hotel_id' => 'required|numeric|exists:hotels',
            'guest_number' => [
                'required',
                'string',
                Rule::exists('integrations_guest_information')->where('hotel_id', $hotel_id),
            ],
            'reservations' => 'required|array',
            'reservations.*.reservation_number' => [
                'string',
                'required',
                'distinct',
                Rule::unique('guest_checkin_details')->where('hotel_id', $hotel_id),
            ],
            'reservations.*.reservation_status' => 'required|numeric|in:0,1,2,3', //0: reserved, 1:checked in, 2: cancelled, 3: checked out
            'reservations.*.room' => [
                'string',
                'nullable',
                Rule::exists('hotel_rooms', 'location')->where(function ($q) use ($hotel_id) {
                    $q->where('hotel_id', $hotel_id)->where('active', 1);
                }),
            ],
            'reservations.*.check_in' => 'required|date_format:"Y-m-d H:i:s"',
            'reservations.*.check_out' => 'required|date_format:"Y-m-d H:i:s"',
            'reservations.*.comment' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error during the validation of the information',
                'errors' => $validator->errors(),
            ], 400);
        }

        $staff_id = $request->user()->staff_id;

        if (! $this->validateHotelId($hotel_id, $staff_id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'User does not have access to the hotel',
                'errors' => null,
            ], 400);
        }

        $this->configTimeZone($hotel_id);

        $guest_number = $request->guest_number;
        $reservations = $request->reservations;

        $guest = \App\Models\IntegrationsGuestInformation::where('hotel_id', $hotel_id)->where('guest_number', $guest_number)->first();
        $data = [];
        $send_welcome = false;
        foreach ($reservations as $key => $reservation) {
            $room = array_key_exists('room', $reservation) && ! empty($reservation['room']) ? $reservation['room'] : '';
            $room_id = 0;
            if (! empty($room)) {
                $hotel_room = $this->findRoomId($hotel_id, $room);
                $room_id = $hotel_room['room_id'];
            }

            if ($reservation['reservation_status'] == 1) {
                $send_welcome = true;
            }

            /**
             * Add Hour 23 to Check in & Check out
             */
            $check_in = $reservation['check_in'];
            $check_out = $reservation['check_out'];
            if ($reservation['reservation_status'] == 0) { //Reserved
                $check_in = date('Y-m-d', strtotime("$check_in")).' 23:59:00';
                $check_out = date('Y-m-d', strtotime("$check_out")).' 23:59:00';
            } elseif ($reservation['reservation_status'] == 1) { //Check in
                $check_out = date('Y-m-d', strtotime("$check_out")).' 23:59:00';
            }

            $reservationData = [
                'guest_id' => $guest->guest_id,
                'hotel_id' => $hotel_id,
                'room_no' => $room_id,
                'check_in' => $check_in, //$reservation["check_in"],
                'check_out' => $check_out, //$reservation["check_out"],
                'reservation_number' => $reservation['reservation_number'],
                'reservation_status' => $reservation['reservation_status'],
                'comment' => array_key_exists('comment', $reservation) ? $reservation['comment'] : '',
                'status' => (intval($reservation['reservation_status']) == 0 || intval($reservation['reservation_status']) == 1) ? 1 : 0,
            ];

            $resrvationCreated = \App\Models\GuestCheckinDetails::create($reservationData);
            $this->saveLogTracker([
                'module_id' => 8,
                'action' => 'add',
                'prim_id' => $resrvationCreated->sno,
                'staff_id' => $staff_id,
                'date_time' => date('Y-m-d H:i:s'),
                'comments' => 'RESERVATION CREATION | '.json_encode($resrvationCreated),
                'hotel_id' => $hotel_id,
                'type' => 'API-V3',
            ]);
            $data[] = [$reservation['reservation_number'] => $resrvationCreated->sno];
        }

        try {
            if ($send_welcome) {
                MainHelper::sendNotificationMessages(
                    $hotel_id,
                    $guest->guest_id,
                    $staff_id,
                    $guest->GuestRegistration->email_address,
                    $guest->GuestRegistration->phone_no
                );
            }
        } catch (\Exception $e) {
            \Log::error('Error in ReservationController > store > sendNotificationMessages');
            \Log::error($e);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully created',
            'data' => $data,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        // Capturar hotel id, por default el valor es null, en caso de no enviarlo
        $hotel_id = isset($request->hotel_id) ? $request->hotel_id : null;

        // Se agrega guest_number, para poder crear reservas en caso de no ser encontrada, el campo no es requerido
        // De no enviarse el campo, no se creara ningun registro nuevo
        // -----
        // Se quito la validacion de verificar que reservations.*.reservation_number ya se encuentre registrado,
        // para cuando no exista, crearlo
        $validator = Validator::make($request->all(), [
            'hotel_id' => 'required|numeric|exists:hotels',
            'guest_number' => [
                'string',
                Rule::exists('integrations_guest_information')->where('hotel_id', $hotel_id),
            ],
            'reservations' => 'required|array',
            'reservations.*.reservation_status' => 'required|numeric|in:0,1,2,3', //0: reserved, 1:checked in, 2: cancelled, 3: checked out
            'reservations.*.room' => [
                'string',
                'nullable',
                Rule::exists('hotel_rooms', 'location')->where(function ($q) use ($hotel_id) {
                    $q->where('hotel_id', $hotel_id)->where('active', 1);
                }),
            ],
            'reservations.*.check_in' => 'required|date_format:"Y-m-d H:i:s"',
            'reservations.*.check_out' => 'required|date_format:"Y-m-d H:i:s"',
            'reservations.*.comment' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error during the validation of the information',
                'errors' => $validator->errors(),
            ], 400);
        }

        $staff_id = $request->user()->staff_id;

        if (! $this->validateHotelId($hotel_id, $staff_id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'User does not have access to the hotel',
                'errors' => null,
            ], 400);
        }

        $this->configTimeZone($hotel_id);
        $reservations = $request->reservations;

        $guest = null;
        // Validar que exista guest_number, para poder crear las reserva nuevas
        if (isset($request->guest_number)) {
            $guest_number = $request->guest_number;
            $guest = \App\Models\IntegrationsGuestInformation::where('hotel_id', $hotel_id)->where('guest_number', $guest_number)->first();
        }

        // Validar si los registros anteriores pertenece a una suit, y el nuevo registro pretende pasar la reserva a una habitacion normal
        // en dicho caso es necesario cancelar las 2 ó 3 habitaciones y solo dejar una.

        $reservationToCancel = [];

        $limit = 9;
        foreach ($reservations as $key => $reservation) {
            $rn = $reservation['reservation_number'];
            if (strlen($rn) > $limit) {
                $reservation_number = substr($rn, 0, $limit);
                // $rooms              = substr($rn, -3);
                $reservationToCancel[$reservation_number][] = $rn;
            }
        }

        foreach ($reservationToCancel as $key => $value) {
            if (count($value) == 1) {
                $guestCheckinDetails = \App\Models\GuestCheckinDetails::where('hotel_id', $hotel_id)
                    ->where('reservation_number', 'LIKE', "$key%")
                    ->get();

                foreach ($guestCheckinDetails as $key => $r) {
                    if ($key > 0) {
                        $r->reservation_status = 0;
                        $r->status = 0;
                        $r->save();
                    }
                }
            }
        }

        foreach ($reservations as $key => $reservation) {
            $reservation_number = $reservation['reservation_number'];

            $findReservation = \App\Models\GuestCheckinDetails::where('hotel_id', $hotel_id)
                ->where('reservation_number', $reservation_number)
                ->first();

            $room = array_key_exists('room', $reservation) && ! empty($reservation['room']) ? $reservation['room'] : '';
            $room_id = 0;
            if (! empty($room)) {
                $hotel_room = $this->findRoomId($hotel_id, $room);
                $room_id = $hotel_room['room_id'];
            }

            // Validar si existe el registro, de no encontrarlo, crear el registro
            if ($findReservation) {
                if (($findReservation->room_no > 0 && ($findReservation->room_no != $room_id)) && $findReservation->reservation_status == 1) {
                    \App\Models\RoomMove::create([
                        'guest_id' => $findReservation->guest_id,
                        'current_room_no' => $findReservation->room_no,
                        'new_room_no' => $room_id,
                        'hotel_id' => $hotel_id,
                        'created_by' => $staff_id,
                        'created_on' => date('Y-m-d H:i:s'),
                        'updated_on' => null,
                        'comment' => '',
                        'phone' => '',
                        'updated_by' => 0,
                    ]);
                }

                /**
                 * Add Hour 23 to Check in & Check out
                 */
                $check_in = $reservation['check_in'];
                $check_out = $reservation['check_out'];
                if ($reservation['reservation_status'] == 0) { //Reserved
                    $check_in = date('Y-m-d', strtotime("$check_in")).' 23:59:00';
                    $check_out = date('Y-m-d', strtotime("$check_out")).' 23:59:00';
                } elseif ($reservation['reservation_status'] == 1) { //Check in
                    $check_out = date('Y-m-d', strtotime("$check_out")).' 23:59:00';
                }

                $reservationData = [
                    'room_no' => $room_id,
                    'check_in' => $check_in, //$reservation["check_in"],
                    'check_out' => $check_out, //$reservation["check_out"],
                    'reservation_number' => $reservation['reservation_number'],
                    'reservation_status' => $reservation['reservation_status'],
                    'comment' => array_key_exists('comment', $reservation) ? $reservation['comment'] : '',
                    'status' => (intval($reservation['reservation_status']) == 0 || intval($reservation['reservation_status']) == 1) ? 1 : 0,
                ];

                $findReservation->fill($reservationData);
                $findReservation->save();

                $this->saveLogTracker([
                    'module_id' => 8,
                    'action' => 'add',
                    'prim_id' => $findReservation->sno,
                    'staff_id' => $staff_id,
                    'date_time' => date('Y-m-d H:i:s'),
                    'comments' => 'RESERVATION UPDATE | '.json_encode($findReservation),
                    'hotel_id' => $hotel_id,
                    'type' => 'API-V3',
                ]);
            } else {
                if ($guest) {
                    $reservationData = [
                        'guest_id' => $guest->guest_id,
                        'hotel_id' => $hotel_id,
                        'room_no' => $room_id,
                        'check_in' => $reservation['check_in'],
                        'check_out' => $reservation['check_out'],
                        'reservation_number' => $reservation['reservation_number'],
                        'reservation_status' => $reservation['reservation_status'],
                        'comment' => array_key_exists('comment', $reservation) ? $reservation['comment'] : '',
                        'status' => (intval($reservation['reservation_status']) == 0 || intval($reservation['reservation_status']) == 1) ? 1 : 0,
                    ];
                    $resrvationCreated = \App\Models\GuestCheckinDetails::create($reservationData);
                    $this->saveLogTracker([
                        'module_id' => 8,
                        'action' => 'add',
                        'prim_id' => $resrvationCreated->sno,
                        'staff_id' => $staff_id,
                        'date_time' => date('Y-m-d H:i:s'),
                        'comments' => 'RESERVATION CREATION | '.json_encode($resrvationCreated),
                        'hotel_id' => $hotel_id,
                        'type' => 'API-V3',
                    ]);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully updated',
        ], 200);
    }

    public function findRoomId($hotel_id, $location)
    {
        $room = \App\Models\HotelRoom::where('hotel_id', $hotel_id)->where('location', $location)->first();

        return [
            'room_id' => $room->room_id,
            'room' => $room->location,
        ];
    }
}
