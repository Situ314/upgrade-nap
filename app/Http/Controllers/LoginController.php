<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LoginController extends Controller
{
    public $redirect_to = '';

    public $client_id = '';

    public $client_secret = '';

    public function showLoginForm(Request $request)
    {
        $this->redirect_to = $request->redirect_to;
        $this->client_id = $request->client_id;
        $this->client_secret = $request->client_secret;

        return view('auth.api.login', [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_to' => $this->redirect_to,
        ]);
    }
}
