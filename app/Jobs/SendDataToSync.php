<?php

namespace App\Jobs;

use DB;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\Filesystem as File;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDataToSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $integration_active_id;

    private $token_type;

    private $access_token;

    private $hotel_id;

    private $specificData;

    private $crud;

    private $sw;

    private $client;

    //
    private $file_log;

    private $path;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($sw, $hotel_id, $specificData, $integration_active_id, $crud)
    {
        $this->sw = $sw;
        $this->hotel_id = $hotel_id;
        $this->specificData = $specificData;
        $this->crud = $crud;

        if (! isset($integration_active_id)) {
            $integration = \App\Models\IntegrationsActive::where('hotel_id', $hotel_id)->orderBy('id', 'desc')->first();
            $this->integration_active_id = $integration->id;
        } else {
            $this->integration_active_id = is_string($integration_active_id) ? intval($integration_active_id) : $integration_active_id;
        }
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //$this->writeLog('Start Send Data to Sync');

        $base_uri = 'http://13.90.101.203:8080';

        $this->client = new \GuzzleHttp\Client(['headers' => null]);

        /*$form_params = [
            'username'      => 'gmartirosyan@bluip.com',
            'password'      => '111111',
            'grant_type'    => 'password',
            'client_id'     => 'nuvola',
            'client_secret' => 'nuvolasec'
        ];*/

        $response = $this->client->request('POST', 'http://13.90.101.203:8080/api/auth/token', [
            'form_params' => $form_params, ]);

        $rs = json_decode($response->getBody()->getContents(), true);
        echo $rs;
        exit;
        try {
            $response = $this->client->request('POST', 'http://13.90.101.203:8080/api/auth/token', [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'form_params' => $form_params, ]);

            $rs = json_decode($response->getBody()->getContents(), true);
            //$this->writeLog('XXX Send Data to Sync : '.$response->getStatusCode());
            $this->token_type = $rs['token_type'];
            $this->access_token = $rs['access_token'];

            switch ($this->sw) {
                case 'groups_and_contacts':
                    $this->sendDept();
                    $this->sendContacts();
                    break;
                case 'groups':
                    $this->sendDept();
                    break;
                case 'contacts':
                    $this->sendContacts();
                    break;
                case 'tasks':
                    $this->sendTasks();
                    break;
            }
        } catch (RequestException $e) {
            //$this->writeLog("Error in handle: ".$e->getMessage()."\n");
            //$this->writeLog("Error in handle #1: ".$e);
        }
    }

    private function sendDept()
    {
        $this->file_log->append(public_path().'/log.log', date('Y-m-d H:i:s').': '.json_encode([
            'groupId' => $this->specificData,
        ])."\n\n\n");

        if (isset($this->specificData)) {
            $dept = \App\Models\Departament::where('dept_id', $this->specificData)->where('is_active', true)->where('is_api', false)->get([
                'dept_id as groupId',
                'dept_name as groupName',
            ]);
        } else {
            $dept = \App\Models\Departament::where('hotel_id', $this->hotel_id)->where('is_active', true)->where('is_api', false)->get([
                'dept_id as groupId',
                'dept_name as groupName',
            ]);
        }
        $this->file_log->append(public_path().'/log.log', date('Y-m-d H:i:s').': '.json_encode([
            'data_groups' => $dept,
        ])."\n\n\n");
        try {
            $response = $this->client->post('/api/groups', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.$this->access_token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'integrationId' => $this->integration_id,
                    'groups' => [
                        $this->crud => $dept,
                    ],
                ],
            ]);
            $rs = json_decode($response->getBody()->getContents(), true);

            return $rs;
        } catch (RequestException $e) {
            $rs = json_decode($e->getResponse()->getBody()->getContents(), true);

            return $rs;
        }
    }

    private function sendContacts()
    {
        try {
            if (isset($this->specificData)) {
                $user_groups = [];
                if ($this->crud == 'delete') {
                    $staff = \App\User::where('staff_id', $this->specificData)->get(['staff.staff_id as _id']);
                } else {
                    $staff = \App\User::join('staff_hotels', 'staff_hotels.staff_id', '=', 'staff.staff_id')
                    ->where('staff.is_api', false)
                    ->where('staff.staff_id', $this->specificData)
                    ->get([
                        'staff.staff_id as _id',
                        'staff.username',
                        'staff.email',
                        'staff.firstname as firstName',
                        'staff.lastname as lastName',
                        'staff_hotels.department_id as groupId',
                    ]);

                    if ($staff[0]['groupId'] > 0) {
                        $user_groups[] = [
                            'userId' => $staff[0]['_id'],
                            'groupId' => $staff[0]['groupId'],
                        ];
                    }
                }
            } else {
                $staff = \App\User::join('staff_hotels', 'staff_hotels.staff_id', '=', 'staff.staff_id')
                    ->where('staff.is_api', false)
                    ->where('staff_hotels.hotel_id', $this->hotel_id)
                    ->get([
                        'staff.staff_id as _id',
                        'staff.username',
                        'staff.email',
                        'staff.firstname as firstName',
                        'staff.lastname as lastName',
                        'staff_hotels.department_id as groupId',
                    ]);

                $user_groups = [];
                foreach ($staff as $s) {
                    if ($s['groupId'] > 0) {
                        $user_groups[] = [
                            'userId' => $s['_id'],
                            'groupId' => $s['groupId'],
                        ];
                    }
                }
            }
            try {
                $response = $this->client->post('/api/contacts', [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer '.$this->access_token,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'integrationId' => $this->integration_id,
                        'contacts' => [
                            $this->crud => $staff,
                        ],
                    ],
                ]);
                $rs = json_decode($response->getBody()->getContents(), true);
                $this->send_userGroups($user_groups);

                return $rs;
            } catch (RequestException $e) {
                $rs = json_decode($e->getResponse()->getBody()->getContents(), true);

                return $rs;
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                echo Psr7\str($e->getResponse());
            } else {
                echo Psr7\str($e->getRequest());
            }
        }
    }

    private function sendTasks()
    {
        try {
            $this->insetInLog('taskId', $this->specificData);

            if (isset($this->specificData)) {
                $event = \App\Models\Event::join('dept_tag', 'dept_tag.dept_tag_id', '=', 'events.dept_tag_id')->where('event_id', $this->specificData)->where('events.status', 1)->get([
                    'events.event_id as taskId',
                    'events.issue as description',
                    'events.created_by as creatorId',
                    'events.created_on as creation_date',
                    'dept_tag.dept_id as groupId',
                    'events.owner as assigneeId',
                    DB::raw('(CASE WHEN events.priority = 1 THEN \'Low\' WHEN events.priority = 2 THEN \'Medium\' WHEN events.priority = 3 THEN \'High\' END) as priority'),
                    DB::raw('(CASE WHEN events.status = 1   THEN \'PENDING\'   WHEN events.status = 2   THEN \'COMPLETED\'    WHEN events.status = 3   THEN \'CLOSED\' WHEN events.status = 5   THEN \'FUTURE\'  END) as status'),
                ]);
            } else {
                $event = \App\Models\Event::join('dept_tag', 'dept_tag.dept_tag_id', '=', 'events.dept_tag_id')->where('dept_tag.hotel_id', $this->hotel_id)->where('events.status', 1)->get([
                    'events.event_id as taskId',
                    'events.issue as description',
                    'events.created_by as creatorId',
                    'events.created_on as creation_date',
                    'dept_tag.dept_id as groupId',
                    'events.owner as assigneeId',
                    DB::raw('(CASE WHEN events.priority = 1 THEN \'Low\' WHEN events.priority = 2 THEN \'Medium\' WHEN events.priority = 3 THEN \'High\' END) as priority'),
                    DB::raw('(CASE WHEN events.status = 1   THEN \'PENDING\'   WHEN events.status = 2   THEN \'COMPLETED\'    WHEN events.status = 3   THEN \'CLOSED\' WHEN events.status = 5   THEN \'FUTURE\'  END) as status'),
                ]);
            }

            $this->insetInLog('task', json_encode($event));

            try {
                $this->insetInLog('info query', json_encode([
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer '.$this->access_token,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'integrationId' => $this->integration_id,
                        'tasks' => [
                            $this->crud => $event,
                        ],
                    ],
                ]));

                $response = $this->client->post('/api/tasks', [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer '.$this->access_token,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'integrationId' => $this->integration_id,
                        'tasks' => [
                            $this->crud => $event,
                        ],
                    ],
                ]);
                $rs = $response->getBody()->getContents();

                $this->insetInLog('result', $rs);

                return $rs;
            } catch (RequestException $e) {
                $this->file_log->append(public_path().'/log.log', date('Y-m-d H:i:s').': '.json_encode([
                    'result_error_task1' => $e,
                ])."\n\n\n");
                $this->file_log->append(public_path().'/log.log', "*****************************************\n");

                $rs = json_decode($e->getResponse()->getBody()->getContents(), true);
                $this->file_log->append(public_path().'/log.log', date('Y-m-d H:i:s').': '.json_encode([
                    'result_error_task1' => $rs,
                ])."\n\n\n");
                $this->file_log->append(public_path().'/log.log', "*****************************************\n");

                return $rs;
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $this->file_log->append(public_path().'/log.log', date('Y-m-d H:i:s').': '.json_encode([
                    'internal_error_response' => $e->getResponse(),
                ])."\n\n\n");
                $this->file_log->append(public_path().'/log.log', "*****************************************\n");
                echo Psr7\str($e->getResponse());
            } else {
                $this->file_log->append(public_path().'/log.log', date('Y-m-d H:i:s').': '.json_encode([
                    'internal_error_request' => $e->getRequest(),
                ])."\n\n\n");
                $this->file_log->append(public_path().'/log.log', "*****************************************\n");
                echo Psr7\str($e->getRequest());
            }
        }
    }

    private function send_userGroups($data)
    {
        try {
            $response = $this->client->post('/api/userGroups', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.$this->access_token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'integrationId' => $this->integration_id,
                    'userGroups' => [
                        'insert' => $data,
                    ],
                ],
            ]);
            $rs = json_decode($response->getBody()->getContents(), true);

            return $rs;
        } catch (RequestException $e) {
            $rs = json_decode($e->getResponse()->getBody()->getContents(), true);

            return $rs;
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

    public function writeLog($text)
    {
        $this->configTimeZone();
        $day = date('Y_m_d');
        $this->path = public_path().'/logs/'.$this->hotel_id."-behive-$day.log";
        if (! ($this->file_log)) {
            $this->file_log = new File();
        }
        if (! file_exists($this->path)) {
            $this->file_log->put($this->path, '');
        }
        $hour = date('H:i:s');
        $text = "[$hour]: $text \n";
        $this->file_log->append($this->path, $text);
    }

    public function configTimeZone()
    {
        $timezone = \App\Models\Hotel::find($this->hotel_id)->time_zone;
        date_default_timezone_set($timezone);
    }
}
