<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FaceTrackController extends Controller
{
    public $APP_ID = 'bf96e2bf';

    public $APP_KEY = 'fd0f5a32601e1ce9cba8c757d56fafb6';

    public $app_id = 'nuvola_gallery';

    public function enroll(Request $rq)
    {
        $staff_id = $rq->staff_id;
        $staff = \App\User::find($staff_id);

        $queryUrl = 'http://api.kairos.com/enroll';
        //return $staff->staff_img_name;
        $imageObject = '{"image":"https://hackathon1.mynuvola.com'.$staff->staff_img_name.'","subject_id":"'.$rq->staff_id.'", "gallery_name":"nuvola_gallery"}';
        //return $imageObject;
        $request = curl_init($queryUrl);
        // set curl options
        curl_setopt($request, CURLOPT_POST, true);
        curl_setopt($request, CURLOPT_POSTFIELDS, $imageObject);
        curl_setopt($request, CURLOPT_HTTPHEADER, [
            'Content-type: application/json',
            'app_id:'.$this->APP_ID,
            'app_key:'.$this->APP_KEY,
        ]
        );
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($request);
        // show the API response
        $json = json_decode($response, true);

        //return $json;
        // close the session
        curl_close($request);
        $staff->face_id = $json['face_id'];
        $staff->save();

        return response()->json(['face_id' => $json['face_id']], 200);
    }

    public function recognize(Request $rq)
    {
        $queryUrl = 'http://api.kairos.com/recognize';
        $imageObject = '{"image":"'.$rq->img.'", "gallery_name":"nuvola_gallery"}';
        $request = curl_init($queryUrl);
        // set curl options
        curl_setopt($request, CURLOPT_POST, true);
        curl_setopt($request, CURLOPT_POSTFIELDS, $imageObject);
        curl_setopt($request, CURLOPT_HTTPHEADER, [
            'Content-type: application/json',
            'app_id:'.$this->APP_ID,
            'app_key:'.$this->APP_KEY,
        ]
        );
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($request);
        // show the API response
        $json = json_decode($response, true);
        // close the session
        curl_close($request);

        return response()->json(['face_id' => $json['images'][0]['candidates'][0]['face_id']], 200);
    }
}
