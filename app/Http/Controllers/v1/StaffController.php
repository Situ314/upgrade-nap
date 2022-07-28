<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\StaffHotel;
use App\User;
use DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Validator;

class StaffController extends Controller
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
            $data = DB::table('staff_hotels as sh')
            ->select([
                's.staff_id',
                's.username',
                's.firstname',
                's.lastname',
                's.email',
                's.access_code',
            ])
            ->join('staff as s', 's.staff_id', 'sh.staff_id')
            ->where(function ($query) use ($hotel_id) {
                $query
                    ->where('sh.hotel_id', $hotel_id)
                    ->where('s.is_active', 1);
            })
            ->paginate($paginate);

            return response()->json($data, 200);
        }

        return response()->json([], 400);
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
            if (! $request->exists('staff')) {
                return response()->json([
                    'create' => false,
                    'staff_id' => 0,
                    'message' => 'staff, data not provided',
                    'description' => [],
                ], 400);
            }

            if (! $request->exists('staff_hotels')) {
                return response()->json([
                    'create' => false,
                    'staff_id' => 0,
                    'message' => 'staff_hotels, data not provided',
                    'description' => [],
                ], 400);
            }

            $reqStaff = $request->staff;
            $reqStaffHotel = $request->staff_hotels;
            /**
             * Validate information
             */
            $validation = Validator::make($reqStaff, [
                'username' => 'string|required|unique:staff',
                'lastname' => [
                    'string',
                    'required',
                    Rule::unique('staff')->where(function ($query) use ($reqStaff) {
                        $query
                            ->where('firstname', isset($reqStaff['firstname']) ? $reqStaff['firstname'] : '')
                            ->where('lastname', isset($reqStaff['lastname']) ? $reqStaff['lastname'] : '')
                            ->where('is_active', 1);
                    }),
                ],
                'firstname' => [
                    'string',
                    'required',
                    Rule::unique('staff')->where(function ($query) use ($reqStaff) {
                        $query
                            ->where('firstname', isset($reqStaff['firstname']) ? $reqStaff['firstname'] : '')
                            ->where('lastname', isset($reqStaff['lastname']) ? $reqStaff['lastname'] : '')
                            ->where('is_active', 1);
                    }),
                ],
                'access_code' => 'string|required|regex:/([0-9]{4,6})/|unique:staff',
                'phone_number' => 'string|regex:/\+[0-9]{1,4}[0-9]{6,10}/|unique:staff',
                'password' => 'string|required',
                'email' => 'string|email|unique:staff',
            ], [
                'access_code.unique' => 'Invalid access code, try another code',
            ]);

            if ($validation->fails()) {
                return response()->json([
                    'create' => false,
                    'staff_id' => 0,
                    'message' => 'Staff object, failed validation',
                    'description' => $validation->errors(),
                ], 400);
            }

            $validation = Validator::make($reqStaffHotel, [
                'hotel_id' => 'required|numeric|exists:hotels',
                'role_id' => [
                    'required',
                    'numeric',
                    Rule::exists('roles')->where('hotel_id', isset($reqStaffHotel['hotel_id']) ? $reqStaffHotel['hotel_id'] : ''),
                ],
            ]);

            if ($validation->fails()) {
                return response()->json([
                    'create' => false,
                    'staff_id' => 0,
                    'message' => 'Staff hotels object, failed validation',
                    'description' => $validation->errors(),
                ], 400);
            }

            $hotel_id = $reqStaffHotel['hotel_id'];
            $staff_id = $request->user()->staff_id;
            if (! $this->validateHotelId($reqStaffHotel['hotel_id'], $staff_id)) {
                return response()->json([
                    'create' => false,
                    'staff_id' => 0,
                    'message' => 'User does not have access to the hotel',
                    'description' => [],
                ], 400);
            }
            $this->configTimeZone($hotel_id);

            /**
             *  Validate if the current user belong to hotel
             */
            if (! ($this->validateHotelId($hotel_id, $staff_id))) {
                return response()->json([
                    'create' => false,
                    'staff_id' => 0,
                    'message' => 'the hotel_id does not belong to the current user',
                    'description' => [],
                ], 400);
            }

            /**
             * if the Object not have email, validate if the username if a email
             */
            if (empty($reqStaff['email'])) {
                if (filter_var($reqStaff['username'], FILTER_VALIDATE_EMAIL)) {
                    $reqStaff['email'] = $reqStaff['username'];
                }
            }

            /**
             * Create model Staff, save model
             */
            $staff = [
                'username' => $this->proccessString(isset($reqStaff['username']) ? $reqStaff['username'] : ''),
                'firstname' => $this->proccessString(isset($reqStaff['firstname']) ? $reqStaff['firstname'] : ''),
                'lastname' => $this->proccessString(isset($reqStaff['lastname']) ? $reqStaff['lastname'] : ''),
                'password' => md5($reqStaff['password']),
                'email' => $this->proccessString(isset($reqStaff['email']) ? $reqStaff['email'] : ''),
                'access_code' => $this->proccessString(isset($reqStaff['access_code']) ? $reqStaff['access_code'] : ''),
                'phone_number' => $this->proccessString(isset($reqStaff['phone_number']) ? $reqStaff['phone_number'] : ''),
                'description' => '',
                'is_super_admin' => 0,
                'created_on' => date('Y-m-d H:i:s'),
                'created_by' => $request->user()->staff_id,
            ];
            $staff_id = User::create($staff)->staff_id;

            $staff_hotels = [
                'staff_id' => $staff_id,
                'hotel_id' => $hotel_id,
                'role_id' => $reqStaffHotel['role_id'],
                'created_on' => date('Y-m-d H:i:s'),
                'created_by' => $request->user()->staff_id,
            ];
            StaffHotel::create($staff_hotels);

            $this->saveLogTracker([
                'module_id' => 7,
                'action' => 'add',
                'prim_id' => $staff_id,
                'staff_id' => $request->user()->staff_id,
                'date_time' => date('Y-m-d H:i:s'),
                'comments' => '',
                'hotel_id' => $hotel_id,
                'type' => 'API',
            ]);

            DB::commit();

            return response()->json([
                'create' => true,
                'staff_id' => $staff_id,
                'message' => '',
                'description' => [],
            ], 201);
        } catch (\Exception $e) {
            echo  $e;

            DB::rollback();

            return response()->json([
                'create' => false,
                'staff_id' => 0,
                'message' => 'Bad request',
                'description' => $e,
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
        try {
            DB::beginTransaction();

            if (! isset($request->hotel_id)) {
                return response()->json([
                    'update' => false,
                    'mesage' => 'Hotel id not provider',
                    'description' => [],
                ], 400);
            }
            $staff_id = $request->user()->staff_id;
            $hotel_id = $request->hotel_id;

            if (! ($this->validateHotelId($hotel_id, $staff_id))) {
                return response()->json([
                    'update' => false,
                    'message' => 'User does not belongs to the hotel',
                    'description' => [],
                ], 400);
            }

            $staff = User::find($id);

            if ($staff) {
                $staff_hotels = StaffHotel::where('staff_id', $id)->where('hotel_id', $hotel_id);

                if ($staff_hotels) {
                    if (! $request->exists('staff')) {
                        return response()->json([
                            'update' => false,
                            'message' => 'staff, data not provided',
                            'description' => [],
                        ], 400);
                    }

                    $reqStaff = $request->staff;

                    $validation = Validator::make($reqStaff, [
                        'username' => [
                            'string',
                            'required',
                            Rule::unique('staff')->ignore($id, 'staff_id'),
                        ],
                        'lastname' => [
                            'string',
                            'required',
                            Rule::unique('staff')->ignore($id, 'staff_id')->where(function ($query) use ($reqStaff) {
                                $query
                                    ->where('firstname', isset($reqStaff['firstname']) ? $reqStaff['firstname'] : '')
                                    ->where('lastname', isset($reqStaff['lastname']) ? $reqStaff['lastname'] : '')
                                    ->where('is_active', 1);
                            }),
                        ],
                        'firstname' => [
                            'string',
                            'required',
                            Rule::unique('staff')->ignore($id, 'staff_id')->where(function ($query) use ($reqStaff) {
                                $query
                                    ->where('firstname', isset($reqStaff['firstname']) ? $reqStaff['firstname'] : '')
                                    ->where('lastname', isset($reqStaff['lastname']) ? $reqStaff['lastname'] : '')
                                    ->where('is_active', 1);
                            }),
                        ],
                        'access_code' => [
                            'string',
                            'required',
                            'regex:/[0-9]{4,6}/',
                            Rule::unique('staff')->ignore($id, 'staff_id')->where('is_active', 1),
                        ],
                        'phone_number' => [
                            'string',
                            'regex:/\+[0-9]{1,4}[0-9]{6,10}/',
                            Rule::unique('staff')->ignore($id, 'staff_id'),
                        ],
                        'password' => 'string|required',
                        'email' => [
                            'string',
                            'email',
                            Rule::unique('staff')->ignore($id, 'staff_id'),
                        ],
                    ]);

                    if ($validation->fails()) {
                        return response()->json([
                            'update' => false,
                            'message' => 'Staff object, failed validation',
                            'description' => $validation->errors(),
                        ], 400);
                    }

                    $reqStaff['password'] = $staff->password;
                    $staff->fill($reqStaff);
                    $staff->save();

                    DB::commit();

                    return response()->json([
                        'update' => true,
                        'message' => '',
                        'description' => [],
                    ], 200);
                }
            }

            DB::rollback();

            return response()->json([
                'update' => false,
                'message' => 'The registration was not found at the hotel',
                'description' => [],
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            echo $e;

            return response()->json([
                'update' => false,
                'message' => 'Bad request',
                'description' => $e,
            ], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            if (! isset($request->hotel_id)) {
                return response()->json([
                    'delete' => false,
                    'mesage' => 'Hotel id not provider',
                    'description' => [],
                ], 400);
            }
            $staff_id = $request->user()->staff_id;
            $hotel_id = $request->hotel_id;

            if (! ($this->validateHotelId($hotel_id, $staff_id))) {
                return response()->json([
                    'delete' => false,
                    'message' => 'User does not belongs to the hotel',
                    'description' => [],
                ], 400);
            }

            $staff = User::find($id);

            if ($staff) {
                $staff_hotels = StaffHotel::where('staff_id', $id)->where('hotel_id', $hotel_id);
                if ($staff_hotels) {
                    $staff->is_active = 0;
                    $staff->access_code = '';
                    $staff->save();
                    $staff_id = $staff->staff_id;
                    DB::commit();

                    return response()->json([
                        'delete' => true,
                        'message' => '',
                        'description' => [],
                    ], 200);
                }
            }

            DB::rollback();

            return response()->json([
                'delete' => false,
                'message' => 'The registration was not found at the hotel',
                'description' => [],
            ], 400);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'update' => false,
                'message' => 'Bad request',
                'description' => $e,
            ], 400);
        }
    }
}
