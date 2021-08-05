<?php

namespace App\Http\Controllers\v3;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use Validator;

class GuestController extends Controller
{

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
        // 
        $validator = Validator::make($request->all(), [
            'hotel_id' => 'required|numeric|exists:hotels',
            'guests' => 'required|array',
            'guests.*.guest_number' => [
                "string",
                "required",
                "distinct",
                Rule::unique('integrations_guest_information')->where('hotel_id', $hotel_id)
            ],
            'guests.*.firstname' => 'required|string',
            'guests.*.lastname' => 'required|string',
            'guests.*.email' => [
                'string',
                'required_without:guests.*.phone',
                'required_if:guests.*.phone,',
                'regex:/([-0-9a-zA-Z.+_]+@[-0-9a-zA-Z.+_]+.[a-zA-Z]{2,4}|)/',
                'nullable',
            ],
            'guests.*.phone' => [
                'string',
                'required_without:guests.*.email',
                'required_if:guests.*.email,',
                'regex:/(\+[0-9]{1,4}[0-9]{6,10}|)/',
                'nullable'
            ],
            'guests.*.angel_status' => 'numeric|required|in:0,1',
            'guests.*.category' => 'numeric|in:0,1,2,3,4,5',
            'guests.*.language' => 'string|in:en,es',
            'guests.*.comment' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => "Error when trying to validate the information provided by the user",
                'errors' => $validator->errors()
            ], 400);
        }

        $staff_id = $request->user()->staff_id;
        if (!$this->validateHotelId($hotel_id, $staff_id)) {
            return response()->json([
                'status'    => "error",
                'message'   => "User does not have access to the hotel",
                'errors'    => null
            ], 400);
        }
        $this->configTimeZone($hotel_id);
        $guests = $request->guests;

        $data = [];

        foreach ($guests as $key => $guest) {
            $validateAngelStatus = isset($guest['angel_status']) ? (intval($guest['angel_status']) == 1 ? ($this->validateAngelStatus($hotel_id)) : 0) : 0;
            $guestData = [
                "hotel_id"      => $hotel_id,
                "firstname"     => $guest["firstname"],
                "lastname"      => $guest["lastname"],
                "email_address" => array_key_exists('email', $guest) ? $guest['email'] : '',
                "phone_no"      => array_key_exists('phone', $guest) ? $guest['phone'] : '',
                'angel_status'  => $validateAngelStatus,
                'language'      => array_key_exists('language', $guest) ? $guest['language'] : 'en',
                'comment'       => array_key_exists('comment', $guest) ? $guest['comment'] : '',
                'category'      => array_key_exists('category', $guest) ? $guest['category'] : 0,
                'created_by'    => $staff_id,
                'created_on'    => date('Y-m-d H:i:s'),
                'address'       => '',
                'state'         => '',
                'zipcode'       => '',
                'city'          => '',
                'is_active'     => 1
            ];
            $guestCreated = \App\Models\GuestRegistration::create($guestData);
            $guest_id = $guestCreated->guest_id;
            \App\Models\IntegrationsGuestInformation::create([
                'hotel_id'      => $hotel_id,
                'guest_id'      => $guest_id,
                'guest_number'  => $guest["guest_number"]
            ]);
            $data[] = [$guest["guest_number"] => $guest_id];

            $this->saveLogTracker([
                'module_id' => 8,
                'action'    => 'add',
                'prim_id'   => $guest_id,
                'staff_id'  => $staff_id,
                'date_time' => date("Y-m-d H:i:s"),
                'comments'  => "GUEST CREATION",
                'hotel_id'  => $hotel_id,
                'type'      => 'API-V3'
            ]);
        }

        return response()->json([
            'status'    => 'success',
            'message'   => "Successfully created",
            'data'      => $data
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
        // 
        $validator = Validator::make($request->all(), [
            'hotel_id' => 'required|numeric|exists:hotels',
            'guests' => 'required|array',
            'guests.*.guest_number' => "string|required|distinct|exists:integrations_guest_information",
            'guests.*.firstname' => 'required|string',
            'guests.*.lastname' => 'required|string',
            'guests.*.email' => [
                'string',
                'required_without:guests.*.phone',
                'required_if:guests.*.phone,',
                'regex:/([-0-9a-zA-Z.+_]+@[-0-9a-zA-Z.+_]+.[a-zA-Z]{2,4}|)/',
                'nullable',
            ],
            'guests.*.phone' => [
                'string',
                'required_without:guests.*.email',
                'required_if:guests.*.email,',
                'regex:/(\+[0-9]{1,4}[0-9]{6,10}|)/',
                'nullable'
            ],
            'guests.*.angel_status' => 'numeric|required|in:0,1',
            'guests.*.category' => 'numeric|in:0,1,2,3,4,5',
            'guests.*.language' => 'string|in:en,es',
            'guests.*.comment'  => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => "Error when trying to validate the information provided by the user",
                'errors' => $validator->errors()
            ], 400);
        }

        $staff_id = $request->user()->staff_id;
        if (!$this->validateHotelId($hotel_id, $staff_id)) {
            return response()->json([
                'status'    => "error",
                'message'   => "User does not have access to the hotel",
                'errors'    => null
            ], 400);
        }
        $this->configTimeZone($hotel_id);
        $guests = $request->guests;
        foreach ($guests as $key => $guest) {
            $guest_number = $guest["guest_number"];
            $guestInfo = \App\Models\IntegrationsGuestInformation::where('hotel_id', $hotel_id)
                ->where('guest_number', $guest_number)
                ->first();

            $findGuest = \App\Models\GuestRegistration::find($guestInfo->guest_id);
            if ($findGuest) {
                $validateAngelStatus = isset($guest['angel_status']) ? (intval($guest['angel_status']) == 1 ? ($this->validateAngelStatus($hotel_id)) : 0) : 0;
                $guestData = [
                    "firstname"     => $guest["firstname"],
                    "lastname"      => $guest["lastname"],
                    "email_address" => array_key_exists('email', $guest) ? $guest['email'] : '',
                    "phone_no"      => array_key_exists('phone', $guest) ? $guest['phone'] : '',
                    'angel_status'  => $validateAngelStatus,
                    'language'      => array_key_exists('language', $guest) ? $guest['language'] : 'en',
                    'comment'       => array_key_exists('comment', $guest) ? $guest['comment'] : '',
                    'category'      => array_key_exists('category', $guest) ? $guest['category'] : 0,
                ];

                $findGuest->fill($guestData);
                $findGuest->save();

                $this->saveLogTracker([
                    'module_id' => 8,
                    'action'    => 'update',
                    'prim_id'   => $findGuest->guest_id,
                    'staff_id'  => $staff_id,
                    'date_time' => date("Y-m-d H:i:s"),
                    'comments'  => "GUEST UPDATE",
                    'hotel_id'  => $hotel_id,
                    'type'      => 'API-V3'
                ]);
            }
        }

        return response()->json([
            'status'    => 'success',
            'message'   => "Successfully updated",
        ], 200);
    }
}
