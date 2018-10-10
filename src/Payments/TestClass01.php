<?php

namespace KimBev\Payments;

use Carbon\Carbon;
use KimBev\Contracts\Payments\KimBevContract;
Use Log;
Use Session;

class TestClass01 implements KimBevContract
{
    private $params;

    private const CALLBACK_TYPE_TRANSACTIONS = 'transactions';

    /**
     * Class constructor.
     *
     * @param array $params Array containing the necessary params.
     *    $params = [
     *      'env'                   => (string) prelive or production
     *
     * @author Kim Beveridge <kim@rhinoloft.com>
     * @return void
     */
    public function __construct($params)
    {
    }

    /*
     * Private methods to PromisePay API
     * ---------------------------------
     */

    public function getCallbacks()
    {
        Log::debug(Session::getId() . " TestClass01:getCallbacks start");
        return "hello";
    }

}
