<?php

namespace  Aha\LumenMicroCore\Middleware;

use Closure;
use Exception;
use Firebase\JWT\ExpiredException;

use Illuminate\Http\Response;

class JwtMiddleware
{
    public function __construct()
    {
        
    }

    public function handle($request, Closure $next, $guard = null)
    {
        // $token = $request->bearerToken();
        $unauthorizedResponse = [
            'code' => 'e_unauthoized',
            'errors' => [],
            'message' => 'unauthoized'
        ];

        return response()->json($unauthorizedResponse, Response::HTTP_UNAUTHORIZED);
        return $next($request);
       
    }
}