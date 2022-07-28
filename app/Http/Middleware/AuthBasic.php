<?php

namespace App\Http\Middleware;

use Closure;

class AuthBasic
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // \Log::info("AuthBasic");
        // \Log::info($request->ip());
        // \Log::info($request->getUser());
        // \Log::info($request->getPassword());

        /*$user = \App\User::where([
            'username' => $request->getUser(),
            'password' => md5($request->getPassword())
        ])->first();*/

        $user = \App\User::where(['username' => $request->getUser()])->first();
        if ($user) {
            // validate hash password
            if ($user->password != md5($request->getPassword())) {
                $user = null;
            }
        }
        if ($user) {
            $user->staffHotels;
            $request->merge([
                'staff_id' => $user->staff_id,
                'hotel_id' => $user->staffHotels[0]->hotel_id,
            ]);

            return $next($request);
        }
        \Log::error('AUTH FAILS');

        return response()->json(['message' => 'Auth failed'], 401);
    }
}
