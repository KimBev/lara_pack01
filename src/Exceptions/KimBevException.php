<?php

namespace KimBev\Exceptions;

use Exception;

class KimBevException extends Exception {

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return ExceptionResource
     */
//    public function render($request)
//    {
//        //Pass the code and message back if set in the PassbackInApi constant array
//        if (in_array($this->getCode(), Config::get('constants.errorsPassbackInApi'))) {
//            $this->message = $this->getMessage();
//            $exceptionResource = new ExceptionResource($this);
//            $exceptionResource->response()->setStatusCode(200);
//            return $exceptionResource;
//
//        } elseif ($this->getCode() == Config::get('constants.error.VALIDATION_ERROR.code')) {
//            $v = $this->getPrevious();
//            if (isset($v) && $v instanceof ValidationException) {
//                foreach ($v->validator->errors()->all() as $message) {
//                    $this->message = $message;
//                }
//            } else {
//                $this->message = Config::get('constants.error.SYSTEMERROR.message');
//            }
//            $exceptionResource = new ExceptionResource($this);
//            //This probly shoul be a http 400, but to keep backwards compatibility with Coinoft legacy API, leave it as 200
//            $exceptionResource->response()->setStatusCode(200);
//            return $exceptionResource;
//        }
//
//        //Otherwise pass back generic 999 error code and 500 HTTP error
//        else {
//            $this->code = Config::get('constants.error.SYSTEMERROR.code');
//            $this->message = Config::get('constants.error.SYSTEMERROR.message');
//            $exceptionResource = new ExceptionResource($this);
//            $exceptionResource->response()->setStatusCode(500);
//            return $exceptionResource;
//        }
//    }

//    /**
//     * Report or log an exception.
//     *
//     * @return void
//     */
//    public function report()
//    {
//        Log::error(Session::getId() . " CoinLoftExchangeException:render exception {$this->getCode()} {$this->getMessage()}");
//
//        //If this is an unexpected error
//        if (!in_array($this->getCode(), Config::get('constants.errorsPassbackInApi'))) {
//            //If validation error, print the errors
//            if ($this->getCode() == Config::get('constants.error.VALIDATION_ERROR.code')) {
//                $v = $this->getPrevious();
//                if (isset($v) && $v instanceof ValidationException) {
//                    Log::error(Session::getId() . ' ' . $v->validator->errors());
//                }
//            }
//
//            //print the stack trace
//            else {
//                Log::debug(Session::getId() . " CoinLoftExchangeException:render exception {$this->getTraceAsString()}");
//            }
//        }
//    }

}
