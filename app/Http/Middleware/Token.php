<?php

namespace App\Http\Middleware;

use Closure;
use DB;

class Token
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
        $token = $request->header('X-Token');

        if (is_null($token)) {
            return response('Token is missing', 403);
        }
        
        $isTokenAvailable = DB::table('tokens')->where('token', $token)->first();

        if(is_null($isTokenAvailable)) {
            return response('Wrong token', 401);
        }

        return $next($request);
    }
}
