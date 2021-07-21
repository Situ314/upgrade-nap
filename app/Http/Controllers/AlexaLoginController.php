<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AlexaLoginController extends Controller
{
    public function showLoginForm(Request $request){
        return view('auth.alexa.login');
    }
}
