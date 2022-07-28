<?php

namespace App\Http\Middleware;

use Closure;

class BlockIp
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
        // $ipsDeny = [
        //     "189.141.92.180",
        //     "187.214.113.2",
        //     "187.234.109.253",
        //     "189.171.66.46",
        //     "189.177.241.41"
        // ];

        // if (in_array(request()->ip(), $ipsDeny)) {
        //     \Log::error("Unauthorized access, IP address was => " . request()->ip());
        //     return response()->json(['integration locked'], 400);
        // }

        return $next($request);
    }
}
