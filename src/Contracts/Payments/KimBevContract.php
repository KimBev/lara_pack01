<?php

namespace KimBev\Contracts\Payments;


interface KimBevContract
{

    /**
     * Class constructor.
     *
     * @param array $params Array containing the necessary paramaters for the implementation
     *
     * @author Kim Beveridge <kim@rhinoloft.com>
     * @return void
     */
    public function __construct($params);

}