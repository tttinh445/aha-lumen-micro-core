<?php

namespace Aha\LumenMicroCore\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;

use Aha\LumenMicroCore\Traits\ResponseTrait;

class ApiController extends BaseController
{
    use ResponseTrait;
      //Add this method to the Controller class
    protected function respondWithToken($token)
    {
        return response()->json([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::factory()->getTTL() * 60
        ], 200);
    }
}
