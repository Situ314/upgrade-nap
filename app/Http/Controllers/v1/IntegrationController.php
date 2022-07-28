<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Jobs\SendDataToSync;
use DB;
use Illuminate\Filesystem\Filesystem as File;
use Illuminate\Http\Request;
use Validator;

class IntegrationController extends Controller
{
    private $file_log;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $bluIp_id = \App\Models\Integrations::where('name', 'bluip')->frist()->id;

        $Integrations = \App\Models\Integration::where('active', true)->get();

        return response()->json($Integrations, 200);
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
            if (! isset($request->integration)) {
                return response()->json([
                    'create' => false,
                    'integration_id' => 0,
                    'message' => 'Integration, data not provided',
                    'description' => [],
                ], 400);
            }
            $integration = $request->integration;
            $this->configTimeZone($integration['nuvola_property_id']);

            if (! $this->validateHotelId($integration['nuvola_property_id'], $request->user()->staff_id)) {
                return response()->json([
                    'create' => false,
                    'integration_id' => 0,
                    'message' => 'the nuvola_property_id does not belong to the current user',
                    'description' => [],
                ], 400);
            }

            $validation = Validator::make($integration, [
                'nuvola_property_id' => 'string|required',
                'behive_property_id' => 'string|required',
                'contact_sync_enabled' => 'boolean|required',
                'task_sync_enabled' => 'boolean|required',
            ]);

            if ($validation->fails()) {
                return response()->json([
                    'create' => false,
                    'integration_id' => 0,
                    'message' => 'Integration object, failed validation',
                    'description' => $validation->errors(),
                ], 400);
            }

            $integration['active'] = true;
            $integration['created_by'] = $request->user()->staff_id;
            $integration['created_on'] = date('Y-m-d H:i:s');
            $integration['updated_on'] = null;
            $integration['updated_by'] = null;
            $__integration = \App\Models\Integration::where('nuvola_property_id', $integration['nuvola_property_id'])->first();
            if ($__integration) {
                $__integration->active = true;
                $__integration->behive_property_id = $integration['behive_property_id'];
                $__integration->contact_sync_enabled = false;
                $__integration->task_sync_enabled = false;
                $__integration->created_by = $request->user()->staff_id;
                $__integration->created_on = date('Y-m-d H:i:s');
                $__integration->save();
                $integration_id = $__integration->integration_id;
            } else {
                $integration_id = \App\Models\Integration::create($integration)->integration_id;
            }

            DB::commit();
            $success = true;
        } catch (\Exception $e) {
            $error = $e;
            $success = false;
            DB::rollback();
        }
        if ($success) {
            return response()->json([
                'create' => true,
                'integration_id' => $integration_id,
                'message' => '',
                'description' => [],
            ], 201);
        } else {
            return response()->json([
                'create' => false,
                'integration_id' => 0,
                'message' => 'Bad request',
                'description' => $error,
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
            $integration = \App\Models\Integration::find($id);
            if ($integration) {
                if (! isset($request->integration)) {
                    return response()->json([
                        'update' => false,
                        'message' => 'Integration, data not provided',
                        'description' => [],
                    ], 400);
                }
                $integration_old = $request->integration;

                $validation = Validator::make($integration_old, [
                    'nuvola_property_id' => 'numeric|required',
                    'contact_sync_enabled' => 'boolean|required',
                    'task_sync_enabled' => 'boolean|required',
                ]);
                if ($validation->fails()) {
                    return response()->json([
                        'update' => false,
                        'message' => 'Integration object, failed validation',
                        'description' => $validation->errors(),
                    ], 400);
                }
                $this->configTimeZone($integration_old['nuvola_property_id']);

                $integration->contact_sync_enabled = $integration_old['contact_sync_enabled'];
                $integration->task_sync_enabled = $integration_old['task_sync_enabled'];
                $integration->updated_by = $request->user()->staff_id;
                $integration->updated_on = date('Y-m-d H:i:s');

                $integration->save();

                if ($integration->task_sync_enabled) {
                    $job = (new SendDataToSync('tasks', $integration->nuvola_property_id, null, $id, 'insert'));
                    dispatch($job);
                } elseif ($integration->contact_sync_enabled) {
                    $job = (new SendDataToSync('groups_and_contacts', $integration->nuvola_property_id, null, $id, 'insert'));
                    dispatch($job);
                }

                DB::commit();
                $success = true;
            } else {
                return response()->json([
                    'update' => false,
                    'message' => 'record not found',
                    'description' => [
                    ],
                ], 400);
            }
        } catch (\Exception $e) {
            return $e;
            $error = $e;
            $success = false;
            DB::rollback();
        }

        if ($success) {
            return response()->json([
                'update' => true,
                'message' => '',
                'description' => [
                ],
            ], 201);
        } else {
            return response()->json([
                'update' => false,
                'message' => 'Bad request',
                'description' => $error,
            ], 400);
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
        $integration = \App\Models\Integration::where('integration_id', $id)->where('active', true)->first();
        if ($integration) {
            $integration->active = 0;
            $integration->save();

            return response()->json([
                'delete' => true,
                'message' => '',
                'description' => [],
            ], 200);
        } else {
            return response()->json([
                'delete' => false,
                'message' => 'record not found',
                'description' => [],
            ], 400);
        }
    }

    public function syncContacts(Request $request)
    {
        $error = [];
        $integrationId = $request->integrationId;
        $integration = \App\Models\Integration::find($integrationId);
        $insert = [];
        $failed = [];
        if ($integration) {
            $this->configTimeZone($integration->nuvola_property_id);

            if ($integration->active == 1) {
                if ($integration->contact_sync_enabled == 1) {
                    DB::beginTransaction();
                    try {
                        $contacts = $request->contacts;

                        if (! empty($contacts['delete'])) {
                            foreach ($contacts['delete'] as $c) {
                                $staff_hotels = \App\Models\StaffHotel::where('staff_id', $c['staff_id'])->first();
                                $staff = \App\User::find($c['staff_id']);
                                if ($staff) {
                                    $staff->is_active = 2;
                                    $staff->is_api = true;
                                    $staff->save();
                                    $staff_hotels->delete();
                                }
                            }
                        }

                        if (! empty($contacts['update'])) {
                            foreach ($contacts['update'] as $c) {
                                $staff = \App\User::find($c['staff_id']);
                                if ($staff) {
                                    if ($staff->is_active == 1) {
                                        $staff->firstname = isset($c['firstname']) ? $c['firstname'] : '';
                                        $staff->lastname = isset($c['lastname']) ? $c['lastname'] : '';
                                        $staff->username = isset($c['username']) ? $c['username'] : '';

                                        if (! isset($c['email'])) {
                                            $staff->email = '';
                                            if (filter_var($c['username'], FILTER_VALIDATE_EMAIL)) {
                                                $staff->email = $c['username'];
                                            }
                                        }

                                        $staff->updated_on = date('Y-m-d H:i:s');
                                        $staff->updated_by = $request->user()->staff_id;
                                        $staff->is_api = true;
                                        $staff->save();
                                    }
                                }
                            }
                        }

                        if (! empty($contacts['insert'])) {
                            foreach ($contacts['insert'] as $c) {
                                if (isset($c['email'])) {
                                    $email = '';
                                    if (filter_var($c['username'], FILTER_VALIDATE_EMAIL)) {
                                        $email = $c['username'];
                                    }
                                } else {
                                    $email = $c['email'];
                                }

                                $staff = [
                                    'hotel_id' => $integration->nuvola_property_id,
                                    'firstname' => isset($c['firstname']) ? $c['firstname'] : '',
                                    'lastname' => isset($c['lastname']) ? $c['lastname'] : '',
                                    'username' => isset($c['username']) ? $c['username'] : '',
                                    'password' => null,
                                    'email' => $email,
                                    'description' => '',
                                    'access_code' => null,
                                    'is_super_admin' => 0,
                                    'staff_img' => null,
                                    'staff_img_size' => 0,
                                    'staff_img_type' => 0,
                                    'is_active' => 1,
                                    'created_on' => date('Y-m-d H:i:s'),
                                    'created_by' => $request->user()->staff_id,
                                    'updated_on' => null,
                                    'updated_by' => null,
                                    'staff_img_name' => null,
                                    'id_user_nuvola32' => null,
                                    'push_key' => null,
                                    'push_android' => null,
                                    'executive' => null,
                                    'email_executive' => null,
                                    'show_tuto' => null,
                                    'badge' => 0,
                                    'is_temporal' => 0,
                                    'tutorial' => 0,
                                    'indicative' => null,
                                    'phone_number' => null,
                                    'badge_notifications_ids' => '',
                                    'is_api' => true,
                                    'is_active' => 1,
                                ];

                                $validation1 = Validator::make($staff, ['username' => 'string|required|unique:staff']);
                                $validation2 = Validator::make($staff, [
                                    'username' => 'string|required',
                                    'firstname' => 'string|required',
                                    'lastname' => 'string|required',
                                ]);
                                if ($validation2->fails()) {
                                    $failed[] = [
                                        'data' => $c,
                                        'error' => $validation2->errors(),
                                    ];
                                } else {
                                    if ($validation1->fails()) {
                                        $hotel_id = is_string($integration->nuvola_property_id) ? intval($integration->nuvola_property_id) : $integration->nuvola_property_id;

                                        $__staff = \App\User::join('staff_hotels', 'staff_hotels.staff_id', '=', 'staff.staff_id')
                                            ->select('staff.*')
                                            ->where('staff.username', $c['username'])
                                            ->where('staff_hotels.hotel_id', $hotel_id)
                                            ->first();

                                        if ($__staff) {
                                            if ($__staff->is_active != 1) {
                                                $__staff->is_active = 1;
                                                $__staff->save();
                                                $insert[] = $__staff;
                                            } else {
                                                $failed[] = [
                                                    'data' => $c,
                                                    'error' => $validation1->errors(),
                                                ];
                                            }
                                        } else {
                                            $__staff = \App\User::create($staff);
                                            $insert[] = $__staff;
                                        }
                                    } else {
                                        $__staff = \App\User::create($staff);
                                        $insert[] = $__staff;
                                    }
                                    $__staff_id = $__staff->staff_id;

                                    $__staff_hotels = \App\Models\StaffHotel::where('staff_id', $__staff_id)->where('hotel_id', $integration->nuvola_property_id)->first();
                                    if (! $__staff_hotels) {
                                        $staff_hotels = [
                                            'staff_id' => $__staff_id,
                                            'hotel_id' => $integration->nuvola_property_id,
                                            'role_id' => 0,
                                            'deparament_id' => -1,
                                            'tag_id' => 0,
                                            'shift_id' => 0,
                                            'is_active' => 1,
                                            'created_on' => date('Y-m-d H:i:s'),
                                            'created_by' => $request->user()->staff_id,
                                            'updated_on' => null,
                                            'updated_by' => null,
                                            'wake_up_calls' => null,
                                            'update_beta_by' => null,
                                        ];

                                        \App\Models\StaffHotel::create($staff_hotels);
                                    }
                                }
                            }
                        }

                        DB::commit();
                        $success = true;
                    } catch (\Exception $e) {
                        $error = $e;
                        $success = false;
                        DB::rollback();
                    }
                    if ($success) {
                        return response()->json([
                            'SyncContacts' => true,
                            'message' => 'Processes performed correctly',
                            'insert' => $insert,
                            'failed' => $failed,
                        ], 200);
                    }
                }
            }
        }

        return response()->json([
            'SyncContacts' => false,
            'message' => 'the integration is not registered in the system or an error occurred in the process',
            'description' => $error,
            'insert' => [],
            'failed' => [],
        ], 400);
    }

    public function syncGroups(Request $request)
    {
        $error = [];
        $integrationId = $request->integrationId;
        $integration = \App\Models\Integration::find($integrationId);
        $insert = [];
        $failed = [];
        if ($integration) {
            $this->configTimeZone($integration->nuvola_property_id);

            if ($integration->active == 1) {
                DB::beginTransaction();
                try {
                    $group = $request->groups;

                    if (! empty($group['delete'])) {
                        foreach ($group['delete'] as $c) {
                            /**
                             * Eliminacion logica
                             */
                            $dept = \App\Models\Departament::find($c['dept_id']);
                            $dept->is_active = 2;
                            $dept->save();
                            $dept_tag = \App\Models\DeptTag::where('dept_id', $dept->dept_id)->first();
                            if ($dept_tag) {
                                $dept_tag->status = 2;
                                $dept_tag->save();
                            }
                        }
                    }

                    if (! empty($group['update'])) {
                        foreach ($group['update'] as $c) {
                            $dept = \App\Models\Departament::find($c['dept_id']);
                            if ($dept) {
                                if ($dept->is_active == 1) {
                                    $dept->dept_name = isset($c['groupName']) ? $c['groupName'] : '';
                                    $dept->short_name = isset($c['initials']) ? $c['initials'] : '';
                                    $dept->dep_default = isset($c['dep_default']) ? $c['dep_default'] : 0;
                                    $dept->updated_on = date('Y-m-d H:i:s');
                                    $dept->updated_by = $request->user()->staff_id;
                                    $dept->save();
                                }
                            }
                        }
                    }

                    if (! empty($group['insert'])) {
                        foreach ($group['insert'] as $c) {
                            $dept = [
                                'hotel_id' => $integration->nuvola_property_id,
                                'dept_name' => isset($c['groupName']) ? $c['groupName'] : '',
                                'short_name' => isset($c['initials']) ? $c['initials'] : '',
                                'dep_default' => 0,
                                'created_on' => date('Y-m-d H:i:s'),
                                'created_by' => $request->user()->staff_id,
                                'updated_on' => null,
                                'updated_by' => null,
                                'color' => null,
                                'predetermined_target_2' => null,
                                'predetermined_target_3' => null,
                                'is_api' => 1,
                            ];
                            /**
                             * validar datos entrante
                             */
                            $validation = Validator::make(
                                $dept,
                                [
                                    'dept_name' => 'string|required|unique:departments',
                                    'short_name' => 'string|required',
                                ]
                            );
                            /**
                             * Validar que no exista el departamento a crear,
                             * si no existe se crea sin mas,
                             */
                            if ($validation->fails()) {
                                $hotel_id = is_string($integration->nuvola_property_id) ? intval($integration->nuvola_property_id) : $integration->nuvola_property_id;
                                $__dept = \App\Models\Departament::where('dept_name', $dept['dept_name'])->where('hotel_id', $hotel_id)->first();
                                /**
                                 * Buscamos el registro que se quiere insertar
                                 */
                                if ($__dept) {
                                    /**
                                     * Si el registro existe y esta activo, retornamos un error,
                                     * si existe pero esta inactivo(eliminado logicamente), se actualiza el registro
                                     */
                                    if ($__dept->is_active != 1) {
                                        $__dept->is_active = 1;
                                        $__dept->save();
                                        $dept_tag = \App\Models\DeptTag::where('dept_id', $__dept->dept_id)->first();
                                        if ($dept_tag) {
                                            $dept_tag->status = 1;
                                            $dept_tag->save();
                                        }
                                        /*
                                        else
                                        {
                                            $rs = $this->saveGroups($dept, $integration->nuvola_property_id, $request->user()->staff_id);
                                            $insert[] = $rs;
                                        }
                                        */

                                        /**
                                         * Se agrega el registro en el array para retornalo a BeHive
                                         */
                                        $m = \App\Models\DeptTag::where('dept_id', $__dept->dept_id)
                                            ->select(['dept_tag_id', 'dept_id', 'tag_id'])
                                            ->with('departament', 'tag')
                                            ->first();

                                        $insert[] = $m;
                                    } else {
                                        $failed[] = [
                                            'data' => $c,
                                            'error' => $validation->errors(),
                                        ];
                                    }
                                }
                                /*else
                                {
                                   $rs = $this->saveGroups($dept, $integration->nuvola_property_id, $request->user()->staff_id);
                                   $insert[] = $rs;
                                }*/
                            } else {
                                $rs = $this->saveGroups($dept, $integration->nuvola_property_id, $request->user()->staff_id);
                                $insert[] = $rs;
                            }
                        }
                    }

                    DB::commit();
                    $success = true;
                } catch (\Exception $e) {
                    $error = $e;
                    $success = false;
                    DB::rollback();
                }
                if ($success) {
                    return response()->json([
                        'syncGroups' => true,
                        'message' => 'Processes performed correctly',
                        'description' => [],
                        'insert' => $insert,
                        'failed' => $failed,
                    ], 200);
                }
            } else {
                $error[] = 'deleted integration';
            }
        } else {
            $error[] = 'Integration not found';
        }

        return response()->json([
            'syncGroups' => false,
            'message' => 'the integration is not registered in the system or an error occurred in the process',
            'description' => $error,
            'insert' => $insert,
            'failed' => $failed,
        ], 400);
    }

    private function saveGroups($dept, $hotel_id, $staff_id)
    {
        $__dept_id = \App\Models\Departament::create($dept)->dept_id;
        $tag = [];
        $tag['hotel_id'] = $hotel_id;
        $tag['tag_name'] = 'BeHive Integration';
        $tag['tag_default'] = 0;
        $tag['tag_image'] = '';
        $tag['tag_price'] = '';
        $tag['created_on'] = date('Y-m-d H:i:s');
        $tag['created_by'] = $staff_id;
        $tag['updated_by'] = 0;
        $tag['updated_on'] = '1999-01-01 00:00:00';
        $tag['tag_status'] = '';

        /*$__tag_id = \App\Models\Tag::firstOrCreate([
            'hotel_id' => $hotel_id,
            'tag_name' => 'BeHive Integration'
        ],$tag)->tag_id;*/

        $__tag_id = \App\Models\Tag::create($tag)->tag_id;

        $dep_tag = [];
        $dep_tag['hotel_id'] = $hotel_id;
        $dep_tag['dept_id'] = $__dept_id;
        $dep_tag['tag_id'] = $__tag_id;
        $dep_tag['first_email_notification'] = '';
        $dep_tag['second_email_notification'] = '';
        $dep_tag['third_email_notification'] = '';
        $dep_tag['dept_tag_id_32'] = 0;
        $dep_tag['sms_first_notification'] = null;
        $dep_tag['first_indicative'] = null;
        $dep_tag['second_indicative'] = null;
        $dep_tag['third_indicative'] = null;
        $dep_tag['first_number_phone'] = null;
        $dep_tag['second_number_phone'] = null;
        $dep_tag['third_number_phone'] = null;
        $dep_tag['first_push'] = null;
        $dep_tag['second_push'] = null;
        $dep_tag['third_push'] = null;
        $dep_tag['third_user'] = null;
        $dep_tag['second_user'] = null;
        $dep_tag['first_user'] = null;
        $dep_tag['category'] = null;
        $dep_tag['time_ini'] = null;
        $dep_tag['time_fin'] = null;
        $dep_tag['code'] = '';
        $dep_tag['id_staff_autoassign'] = 0;
        $dep_tag['name_staff_autoassign'] = '';

        $dept_tag_id = \App\Models\DeptTag::create($dep_tag)->dept_tag_id;
        $rs = \App\Models\DeptTag::where('dept_tag_id', $dept_tag_id)->select(['dept_tag_id', 'dept_id', 'tag_id'])->with('departament', 'tag')->first();

        return $rs;
    }

    public function syncTasks(Request $request)
    {
        $this->file_log = new File();
        $this->file_log->put(public_path().'/log.log', '');

        $this->insetInLog('Start syncTasks', '');

        $error = [];
        $integrationId = $request->integrationId;
        $this->insetInLog('integrationId', $integrationId);
        $integration = \App\Models\Integration::find($integrationId);
        $insert = [];
        $failed = [];
        if ($integration) {
            $this->configTimeZone($integration->nuvola_property_id);
            if ($integration->active == 1) {
                if ($integration->task_sync_enabled == 1) {
                    DB::beginTransaction();
                    try {
                        $tasks = $request->tasks;
                        if (! empty($tasks['delete'])) {
                            foreach ($tasks['delete'] as $c) {
                                $event = \App\Models\Event::find($c['event_id']);
                                $event->active = 2;
                                $event->save();
                            }
                        }
                        if (! empty($tasks['update'])) {
                            foreach ($tasks['update'] as $c) {
                                $event = \App\Models\Event::find($c['event_id']);
                                if ($event) {
                                    if ($event->active == 1) {
                                        $event->guest_id = isset($c['guest_id']) ? $c['guest_id'] : $event->guest_id;
                                        $event->issue = isset($c['issue']) ? $c['issue'] : $event->issue;
                                        $event->room_id = isset($c['room_id']) ? $c['room_id'] : $event->room_id;
                                        $event->dept_tag_id = isset($c['dept_tag_id']) ? $c['dept_tag_id'] : $event->dept_tag_id;
                                        $event->save();
                                    }
                                }
                            }
                        }
                        if (! empty($tasks['insert'])) {
                            $this->insetInLog('Start insert', '');
                            foreach ($tasks['insert'] as $c) {
                                $this->insetInLog('data', json_encode($c));

                                $room_id = $this->loadRoomId($integration->nuvola_property_id, $c['location']);

                                $event = [
                                    'hotel_id' => $integration->nuvola_property_id,
                                    'guest_id' => isset($c['guest_id']) ? $c['guest_id'] : 0,
                                    'issue' => isset($c['issue']) ? $c['issue'] : '',
                                    'room_id' => $room_id,
                                    'dept_tag_id' => isset($c['dept_tag_id']) ? $c['dept_tag_id'] : 0,
                                    'date' => date('Y-m-d'),
                                    'time' => date('H:i:s'),
                                    'created_by' => $request->user()->staff_id,
                                    'created_on' => date('Y-m-d H:i:s'),
                                    'closed_by' => 0,
                                    'closed_on' => null,
                                    'update_by' => 0,
                                    'update_on' => null,
                                    'delete_by' => 0,
                                    'delete_on' => null,
                                    'owner' => $request->user()->staff_id,
                                    'pending_by' => $request->user()->staff_id,
                                    'pending_on' => date('Y-m-d H:i:s'),
                                    'completed_by' => $request->user()->staff_id,
                                    'completed_on' => date('Y-m-d H:i:s'),
                                    'recurring_from' => null,
                                    'recurring_to' => null,
                                    'recurring_no_of_days' => null,
                                    'recurring_time' => null,
                                    'recurring_months' => null,
                                    'recurring_weeks' => null,
                                    'recurring_dates' => null,
                                    'recurring_status' => null,
                                    'second_notification_start' => null,
                                    'third_notification_start' => null,
                                    'child_recurr' => null,
                                    'count_by_hotel_id' => 0,
                                ];

                                $validation = Validator::make($event, [
                                    'hotel_id' => 'numeric|required|exists:hotels',
                                    'issue' => 'string|required',
                                    'location' => 'string|exists:hotel_rooms,location',
                                    'dept_tag_id' => 'numeric|exists:dept_tag,dept_tag_id',
                                ]);

                                if ($validation->fails()) {
                                    $failed[] = [
                                        'data' => $c,
                                        'error' => $validation->errors(),
                                    ];
                                    $this->insetInLog('fails', json_encode($failed));
                                } else {
                                    $__event = \App\Models\Event::create($event);
                                    $__event['behiveId'] = $c['behiveId'];
                                    $insert[] = $__event;
                                    $this->insetInLog('ssuccess', json_encode($insert));
                                }
                            }
                        }
                        DB::commit();
                        $success = true;
                    } catch (\Exception $e) {
                        $this->insetInLog('ssuccess', $e);
                        $error = $e;
                        $success = false;
                        DB::rollback();
                    }
                    if ($success) {
                        return response()->json([
                            'SyncTasks' => true,
                            'message' => 'Processes performed correctly',
                            'description' => [],
                            'insert' => $insert,
                            'failed' => $failed,
                        ], 200);
                    }
                } else {
                    $error[] = 'task_sync_enabled is disabled';
                }
            } else {
                $error[] = 'the integration is eliminated';
            }
        } else {
            $error[] = 'integration not found';
        }

        return response()->json([
            'SyncTasks' => false,
            'message' => 'the integration is not registered in the system or an error occurred in the process',
            'description' => $error,
            'insert' => $insert,
            'failed' => $failed,
        ], 400);
    }

    public function UserGroups(Request $request)
    {
        $error = [];
        $integrationId = $request->integrationId;
        $integration = \App\Models\Integration::find($integrationId);
        $insert = [];
        $failed = [];
        if ($integration) {
            $this->configTimeZone($integration->nuvola_property_id);
            if ($integration->active == 1) {
                DB::beginTransaction();
                try {
                    $userGroups = $request->userGroups;
                    if (! empty($userGroups['delete'])) {
                        foreach ($userGroups['delete'] as $c) {
                            $delete = \App\Models\StaffHotel::where('staff_id', $c['staff_id'])->first();
                            if ($delete) {
                                $delete->department_id = -1;
                                $delete->save();
                            }
                        }
                    }
                    if (! empty($userGroups['insert'])) {
                        foreach ($userGroups['insert'] as $c) {
                            $validation = Validator::make($c, [
                                'staff_id' => 'numeric|required|exists:staff,staff_id',
                                'dept_id' => 'numeric|required|exists:departments,dept_id',
                            ]);
                            if ($validation->fails()) {
                                $failed[] = [
                                    'data' => $c,
                                    'error' => $validation->errors(),
                                ];
                            } else {
                                $update = \App\Models\StaffHotel::where('staff_id', $c['staff_id'])->first();
                                if ($update) {
                                    $update->department_id = $c['dept_id'];
                                    $update->save();
                                    $insert[] = $update;
                                }
                            }
                        }
                    }
                    DB::commit();
                    $success = true;
                } catch (\Exception $e) {
                    echo $e;
                    exit();
                    $error = $e;
                    $success = false;
                    DB::rollback();
                }
                if ($success) {
                    return response()->json([
                        'UserGroups' => true,
                        'message' => 'Processes performed correctly',
                        'description' => [],
                        'insert' => $insert,
                        'failed' => $failed,
                    ], 200);
                }
            } else {
                $error[] = 'Integration was eliminated';
            }
        } else {
            $error[] = 'Integration not found';
        }

        return response()->json([
            'UserGroups' => false,
            'message' => 'the integration is not registered in the system or an error occurred in the process',
            'description' => $error,
            'insert' => $insert,
            'failed' => $failed,
        ], 400);
    }

    public function UserGroupsAll(Request $request)
    {
        $integrationId = $request->integrationId;
        $paginate = isset($request->paginate) ? $request->paginate : 50;
        $integration = \App\Models\Integration::find($integrationId);
        $staff_id = $request->user()->staff_id;
        $hotel_id = $integration->nuvola_property_id;
        if ($this->validateHotelId($hotel_id, $staff_id)) {
            $deptTag = \App\Models\DeptStaff::with('staff')->with('departament')->paginate($paginate);

            return response()->json($deptTag, 200);
        } else {
            return response()->json([], 400);
        }
    }

    public function UserGroupsById(Request $request, $dept_staff_id)
    {
        $integrationId = $request->integrationId;
        $integration = \App\Models\Integration::find($integrationId);
        if ($integration) {
            $dept_staff = \App\Models\DeptStaff::with('staff')->with('departament')->find($dept_staff_id);

            return response()->json($dept_staff, 200);
        } else {
            return response()->json([], 400);
        }
    }

    public function SendContacts(Request $request)
    {
        $hotel_id = $request->hotel_id;
        $staff_id = $request->staff_id;
        $job = (new SendDataToSync('contacts', $hotel_id, $staff_id, null, 'insert'));
        dispatch($job);

        return response()->json(['result' => true], 200);
    }

    public function SendGroups(Request $request)
    {
        $hotel_id = $request->hotel_id;
        $dept_id = isset($request->dept_id) ? $request->dept_id : null;
        $job = (new SendDataToSync('groups', $hotel_id, $dept_id, null, 'insert'));
        dispatch($job);

        return response()->json(['result' => true], 200);
    }

    public function SendTasks(Request $request)
    {
        $hotel_id = $request->hotel_id;
        $event_id = $request->event_id;
        $job = (new SendDataToSync('tasks', $hotel_id, $event_id, null, 'insert'));
        dispatch($job);

        return response()->json(['result' => true], 200);
    }

    public function SendUpdateContacts(Request $request)
    {
        $hotel_id = $request->hotel_id;
        $staff_id = $request->staff_id;
        $job = (new SendDataToSync('contacts', $hotel_id, $staff_id, null, 'update'));
        dispatch($job);

        return response()->json(['result' => true], 200);
    }

    public function SendUpdateGroups(Request $request)
    {
        $hotel_id = $request->hotel_id;
        $dept_id = $request->dept_id;
        $job = (new SendDataToSync('groups', $hotel_id, $dept_id, null, 'update'));
        dispatch($job);

        return response()->json(['result' => true], 200);
    }

    public function SendUpdateTasks(Request $request)
    {
        $hotel_id = $request->hotel_id;
        $event_id = $request->event_id;
        $job = (new SendDataToSync('tasks', $hotel_id, $dept_id, null, 'update'));
        dispatch($job);

        return response()->json(['result' => true], 200);
    }

    public function SendDeleteContacts(Request $request)
    {
        $hotel_id = $request->hotel_id;
        $staff_id = $request->staff_id;
        $job = (new SendDataToSync('contacts', $hotel_id, $staff_id, null, 'delete'));
        dispatch($job);

        return response()->json(['result' => true], 200);
    }

    public function SendDeleteeGroups(Request $request)
    {
        $hotel_id = $request->hotel_id;
        $dept_id = $request->dept_id;
        $job = (new SendDataToSync('groups', $hotel_id, $dept_id, null, 'delete'));
        dispatch($job);

        return response()->json(['result' => true], 200);
    }

    public function SendDeleteTasks(Request $request)
    {
        $hotel_id = $request->hotel_id;
        $event_id = $request->event_id;
        $job = (new SendDataToSync('tasks', $hotel_id, $event_id, null, 'delete'));
        dispatch($job);

        return response()->json(['result' => true], 200);
    }

    public function loadRoomId($hotel_id, $location)
    {
        $hotel = \App\Models\HotelRoom::where('hotel_id', $hotel_id)->where('location', $location)->orWhere('room_id', $location)->first();
        if ($hotel) {
            return $hotel->room_id;
        } else {
            return 0;
        }
    }

    private function insetInLog($title, $data)
    {
        $separator = "_________________________________________________________________________________\n";
        $this->file_log->append(public_path().'/log.log', $separator);
        $this->file_log->append(public_path().'/log.log', date('Y-m-d H:i:s')."\n");
        $this->file_log->append(public_path().'/log.log', $separator);
        $this->file_log->append(public_path().'/log.log', "$title:\n");
        $this->file_log->append(public_path().'/log.log', "$data\n\n\n\n");
    }
}
