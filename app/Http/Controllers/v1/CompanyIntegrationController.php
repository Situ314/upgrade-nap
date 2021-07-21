<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use \App\Models\CompanyIntegration;

class CompanyIntegrationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if(!isset($request->hotel_id)) {
            $hotel_id = $request->hotel_id;
            $Integrations = CompanyIntegration::where(function( $query ) use ( $hotel_id ){
                $query
                    ->where('hotel_id', $request->hotel_id)
                    ->where('state', true);
            })
            ->get();

            return response()->json( $Integrations, 200 );

        } else {
            return response()->json( 'The hotel id not provided', 400 );
        }
        
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
        try 
        {
            if(!isset($request->integration))
            {
                return response()->json([ 
                    'create' => false, 
                    "description" => [ 'Integration, data not provided' ]
                ], 400);
            }
            
            $integration = $request->integration;
            $hotel_id = $integration["hotel_id"];
            $staff_id = $request->user()->staff_id;
            $this->configTimeZone($hotel_id);
            
            if(!$this->validateHotelId($hotel_id, $staff_id)){
                return response()->json([
                    'create' => false,
                    "description" => ['the nuvola_property_id does not belong to the current user']
                ], 400);
            }

            $validation = Validator::make($integration,[
                'hotel_id' => 'integer|required',
                'company' => 'string|required',
                'sync' => 'json|required'    
            ]);

            if($validation->fails()){
                $err = $validation->errors();
                $err[] = 'Integration object, failed validation';

                return response()->json([ 
                    'create' => false, 
                    "description" => $err
                 ], 400);
            }

            $integration["state"] = true;
            $integration["created_by"] = $staff_id;
            $integration["created_on"] = date('Y-m-d H:i:s');
            $integration["updated_on"] = null;
            $integration["updated_by"] = null;

            $__integration = \App\Models\CompanyIntegration::where('hotel_id', $hotel_id)->first();
            if($__integration)
            {
                $__integration->state = true;
                $__integration->hotel_id = $integration["hotel_id"];
                $__integration->updated_by = $staff_id;
                $__integration->updated_by = date('Y-m-d H:i:s');
                $__integration->save();
                $company_integration_id = $__integration->company_integration_id;
            }
            else
            {
                $company_integration_id = \App\Models\CompanyIntegration::create($integration)->company_integration_id;              
            }

        } 
        catch (\Exception $e)
        {
            $error = $e;
            $success = false;
            DB::rollback();
        }
        if($success)
        {
            return response()->json([
                'create' => true,
                'company_integration_id' => $company_integration_id
            ],201);
        }
        else
        {
            return response()->json([
                'create' => false,
                'description' => $error
            ],201);
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
            $integration = \App\Models\CompanyIntegration::find($id);
            if($integration) {
                if(!isset($request->integration)){
                    return response()->json([ 
                        'update' => false,
                        "description" => ['Integration, data not provided']
                    ], 400);
                }
                $integration_old = $request->integration;
                
                $validation = Validator::make($integration_old,[
                    'hotel_id' => 'numeric|required',
                    'sync' => 'json|required'
                ]);
                if($validation->fails()){
                    $err = $validation->errors();
                    $err[] = 'Integration object, failed validation';

                    return response()->json([ 
                        'update' => false, 
                        "description" => $err
                     ], 400);
                }
                $this->configTimeZone($integration_old["hotel_id"]);
                
                $integration->sync = $integration_old["sync"];
                $integration->updated_by = $request->user()->staff_id;
                $integration->updated_on = date('Y-m-d H:i:s');
                $integration->save();

                /*if($integration->contact_sync_enabled){
                    $job = (new SendDataToSync('groups_and_contacts',$integration->nuvola_property_id, null, $id, 'insert'));
                    dispatch($job);
                }

                if($integration->task_sync_enabled){
                    $job = (new SendDataToSync('tasks', $integration->nuvola_property_id, null, $id, 'insert'));
                    dispatch($job);
                }*/
                
                DB::commit();
                $success = true; 
            } else {
                return response()->json([ 
                    'update' => false,
                    "description" => ['record not found']
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
                'update' => true
            ],201);
        }else{
            $error[] = 'Bad request';
            return response()->json([ 
                'update' => false,
                'description' =>  $error
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
        $integration = \App\Models\CompanyIntegration::where('company_integration_id',$id)->where('state',true)->first();
        if($integration) {

            $integration->state = 0;
            $integration->save();

            return response()->json([ 
                'delete' => true
            ], 200);
        } else {
            return response()->json([
                'delete' => false,
                'description' =>  ['record not found'] 
            ], 400);
        }
    }
}
