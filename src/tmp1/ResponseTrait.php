<?php 

namespace Aha\LumenMicroCore\Traits;
use Illuminate\Http\Response;


Trait ResponseTrait {
    protected $errorStruct = [
        'code' => 'e_',
        'errors' => [],
        'message' => '',
    ];

    public function httpOk($data = null, $message = '')
    {
        return response()->json($data, Response::HTTP_OK);
    }

    public function httpCreated($data = null)
    {
        return response()->json($data, Response::HTTP_CREATED);
    }

    /** ERROR RESPONSE */
    //400
    public function httpBadRequest($error = [])
    {
        $error['code'] = !empty($error['code']) ? $error['code'] : 'e_bad_request';
        return response()->json($this->generateErrorData($error), Response::HTTP_BAD_REQUEST);
    }

    // 422
    public function httpValidated($error)
    {
        $arValidates = [];
        /** VALIDATION */          
        foreach ($error as $field => $msg) {
            $arValidates[] = [
                'field' => $field,
                'message' => $msg,
            ];
        }

        return response()->json([
            'code' => 'e_missing_or_invalid_params',
            'errors' => $arValidates,
            'message' => ''
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    //500
    public function httpInternalError($error = [])
    {
        $error['code'] = !empty($error['code']) ? $error['code'] : 'e_interal_error';
        $error['message'] = !empty($error['message']) ? $error['message'] : __('messages.SystemError');
        return response()->json($this->generateErrorData($error), Response::HTTP_INTERNAL_SERVER_ERROR);
    }


    private function generateErrorData($error = []) 
    {
        $res = $this->errorStruct;
        $res['code'] = !empty($error['code']) ? $error['code'] : $res['code'];
        $res['errors'] = !empty($error['errors']) ? $error['errors'] : $res['errors'];
        $res['message'] = !empty($error['message']) ? $error['message'] : $res['message'];
        return $res;
    }
}