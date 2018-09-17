<?php

namespace CoinLoft\Payments;

use Carbon\Carbon;
use CoinLoft\Contracts\Payments\PaymentContract;
use CoinLoft\Exceptions\CoinLoftPaymentException;
use PromisePay\Callbacks;
use PromisePay\Exception\Api;
use PromisePay\Item;
use PromisePay\Marketplaces;
use PromisePay\PromisePay;
use PromisePay\Transaction;
use PromisePay\User;
use PromisePay\WalletAccounts;

class AssemblyPayments implements PaymentContract
{
    private $params;

    private const CALLBACK_TYPE_TRANSACTIONS = 'transactions';

    /**
     * Class constructor.
     *
     * @param array $params Array containing the necessary params.
     *    $params = [
     *      'env'                   => (string) prelive or production
     *      'login'                 => (string) Login for the account
     *      'token'                 => (string) API token
     *      'callback_transactions' => (string) Callback url for transactions
     *      'customer_id'           => (string) Our Customer ID
     *      'asm_user_id'           => (string) Our User ID
     *      'asm_bank_account_id'   => (string) Our Bank Account ID
     *      'asm_digital_wallet_id' => (string) Our Digital Wallet ID
     *      'asm_user_bpay_crn'     => (string) Our BPAY CRN
     *      'email_domain'          => (string) Email domain for our customers
     *    ]
     *
     * @author Kim Beveridge <kim@rhinoloft.com>
     * @return void
     */
    public function __construct($params)
    {
        $this->params = $params;
        try {
            PromisePay::Configuration()->environment($this->params['env']);
            PromisePay::Configuration()->login($this->params['login']);
            PromisePay::Configuration()->password($this->params['token']);
        } catch (\Exception $e) {
        }
    }

    /*
     * Private methods to PromisePay API
     * ---------------------------------
     */

    private function getCallbacks()
    {
        $c = new Callbacks();
        return $c->getList();
    }

    private function createCallback($type, $description, $url)
    {
        $c = new Callbacks();
        return $c->create(array(
            'description' => $description,
            'url' => $url,
            'object_type' => $type,
            'enabled' => true
        ));
    }

    private function updateCallback($id, $url)
    {
        $c = new Callbacks();
        return $c->update($id, array(
            'url' => $url
        ));
    }

    private function createUser($id, $firstName, $lastName, $msisdn, $country = 'AUS')
    {
        $u = new User();
        return $u->create(array(
            'id' => $id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $id . '@' . $this->params['email_domain'],
            'mobile' => $msisdn,
            'country' => $country
        ));
    }

    private function getUser($userId)
    {
        $u = new User();
        return $u->get($userId);
    }

    private function getDigitalWalletAccounts($userId)
    {
        $u = new User();
        return $u->getListOfWalletAccounts($userId);
    }

    private function getBpayDetailsForWallet($walletId)
    {
        //BPay details functions seems to be missing in the Promise package, so calling it manyally from here..
        PromisePay::RestClient('get', 'wallet_accounts/' . $walletId . '/bpay_details');
        return PromisePay::getDecodedResponse('wallet_accounts');
    }

    public function testProcessBpay($params)
    {
        //Function seems to be missing in the Promise package, so calling it manyally from here..
        PromisePay::RestClient('patch', '/testing/wallet_accounts/process_bpay', $params);
        return PromisePay::getDecodedResponse();
    }

    private function createItem($id, $customerId, $amount, $name)
    {

        if (!isset($this->params['asm_user_id'])) {
            throw new CoinLoftPaymentException('asm_user_id needs to be set');
        }

        $i = new Item();
        return $i->create(array(
            'id' => $id,
            'name' => $name,
            'amount' => $amount,
            'currency' => 'AUD',
            'payment_type' => 2,
            'buyer_id' => $customerId,
            'seller_id' => $this->params['asm_user_id']
        ));
    }

    private function itemMakePayment($itemId, $customerWalletId, $ipAddress = '127.0.0.1', $deviceId = 'CoinLoft')
    {
        $i = new Item();
        return $i->makePayment($itemId, array(
                'account_id' => $customerWalletId,  //Customer 123 wallet id
                'ip_address' => $ipAddress,
                'device_id' => $deviceId
            )
        );
    }

    private function getItemTransactions($id)
    {
        $i = new Item();
        return $i->getListOfTransactions($id);
    }

    private function getItemFees($id)
    {
        $i = new Item();
        return $i->getListOfFees($id);
    }

    private function getMarketPlace()
    {
        $m = new Marketplaces();
        return $m->get();
    }

    private function getTransaction($id)
    {
        $t = new Transaction();
        return $t->get($id);
    }

    public function getTransactions($customerId, $limit = 10)
    {
        $wallet = $this->getDigitalWalletAccounts($customerId);
        $t = new Transaction();
        return $t->getList(array(
            'account_id' => $wallet['id'],
            'limit' => $limit
        ));
    }

    private function isError($e, $responseCode, $errorMessage)
    {
        if (strpos($e->getMessage(), "Response Code: {$responseCode}") !== false) {
            if (strpos($e->getMessage(), "Error Message: {$errorMessage}") !== false) {
                return true;
            }
        }
        return false;
    }

    private function getWalletDetails($customerId)
    {
        try {

            //Get Wallet account and BPAY details for this user
            $wallet = $this->getDigitalWalletAccounts($customerId);
            $bpayDetails = $this->getBpayDetailsForWallet($wallet['id']);

            //Get relevant data from $wallet and $bpay response
            $walletDetails = array(
                array(
                    'id' => $wallet['id'],
                    'currency' => $wallet['currency'],
                    'balance' => $wallet['balance'],
                    'bpay_biller_code' => $bpayDetails['bpay_details']['biller_code'],
                    'bpay_reference' => $bpayDetails['bpay_details']['reference']
                )
            );

        } catch (\Exception $e) {
            //Log::debug(Session::getId() . " AssemblyPayments:getUserDetails caught exception {$e->getMessage()}");
            throw $e;
        }

        return $walletDetails;
    }

    public function getDisbursementTransactions($customerId, $timestamp = null, $limit = 10)
    {
        $wallet = $this->getDigitalWalletAccounts($customerId);

        $t = new Transaction();
        $transactions = $t->getList(array(
            'account_id' => $wallet['id'],
            'limit' => $limit,
            'transaction_type' => 'disbursement'
        ));

        //Filter out based on timestamp
        if (isset($timestamp)) {
            foreach ($transactions as $key => $transaction) {
                if (Carbon::createFromTimeString($transaction['created_at'])->lessThan($timestamp)) {
                    unset($transactions[$key]);
                }
            }
        }

        return $transactions;
    }

    /*
     * Public methods which wrap PromisePay api into easy functional processes
     * ---------------------------------
     */


    /**
     * Registers callbacks with Promise Pay.
     *
     * Only needs to be called once to configure the callbacks for our account.
     *
     * Will check if already registered and only register if required.
     * Will update the registered callback if the existing registered callback is different to the configured callback
     *
     * Callback end points are configured in the $params
     *
     * @throws CoinLoftPaymentException
     * @author Kim Beveridge <kim@rhinoloft.com>
     * @return void
     */
    public function registerCallback()
    {
        //Log::debug(Session::getId() . " AssemblyPayments:registerCallback start");
        try {
            //'transactions' callback
            if (isset($this->params['callback_' . $this::CALLBACK_TYPE_TRANSACTIONS])) {
                $isRegistered = false;
                //Check of the callback is already registered
                $callbacks = $this->getCallbacks();
                foreach (! $callbacks ? [] : $callbacks as $callback) {
                    if ($callback['object_type'] == $this::CALLBACK_TYPE_TRANSACTIONS) {
                        //Compare the registered URL to the URL we have configured, if not then we can update it
                        if ($callback['url'] != $this->params['callback_' . $this::CALLBACK_TYPE_TRANSACTIONS]) {
                            $this->updateCallback($callback['id'],$this->params['callback_' . $this::CALLBACK_TYPE_TRANSACTIONS]);
                        }
                        $isRegistered = true;
                    }
                }
                //If not registered at all, then register it now
                if (!$isRegistered) {
                    //Log::debug(Session::getId() . " AssemblyPayments:registerCallback {$this::CALLBACK_TYPE_TRANSACTIONS} registering");
                    $this->createCallback(
                        $this::CALLBACK_TYPE_TRANSACTIONS,
                        'Transaction callback',
                        $this->params['callback_' . $this::CALLBACK_TYPE_TRANSACTIONS]
                    );
                }
            }
        } catch (\Exception $e) {
            //Log::debug(Session::getId() . " AssemblyPayments:registerCallback caught exception");
            //dump($e->getMessage());
            throw new CoinLoftPaymentException('Register callbacks failed', 999, $e);
        }
    }

    /**
     * Get OUR (merchant) account details and balance
     *
     * @throws CoinLoftPaymentException
     * @author Kim Beveridge <kim@rhinoloft.com>

     * @return array $arr (See below)
     *
     * array['id'                       => (string) id of user
     *       'wallets'                  => [
     *          'id'                    => (string) id of wallet
     *          'currency'              => (string) currency of wallet
     *          'balance'               => (integer) balance of wallet in cents
     *          'bpay_biller_code'      => (string) BPAY biller code
     *          'balabpay_reference'    => (string) BPAY reference
     *          ]
     *      ]
     */
    public function getOurDetails()
    {
        //Log::debug(Session::getId() . " AssemblyPayments:getOurDetails start");
        try {
            //Check if we have our user_id set in $params, if not we can get it with MarketPlace message
            if (!isset($this->params['asm_user_id'])) {
                $m = $this->getMarketPlace();
                $this->params['asm_user_id'] = $m['related']['users'];
            }

            //Get wallet details
            $userDetails = array(
                'id' => $this->params['asm_user_id'],
                'wallets' => $this->getWalletDetails($this->params['asm_user_id'])
            );
        } catch (\Exception $e) {
            //Log::debug(Session::getId() . " AssemblyPayments:registerCallback caught exception");
            throw new CoinLoftPaymentException('getOurDetails failed', 999, $e);
        }

        return $userDetails;
    }

    /**
     * Get User details and balance, user will be created on PromisePay if it does not exist
     *
     * @param int $customerId Unique Customer ID from the consuming system
     * @param string $firstName First name of Customer
     * @param string $lastName Last name of Customer
     * @param string $msisdn Mobile number of Customer in international format (with country code)
     *
     * @throws CoinLoftPaymentException
     * @author Kim Beveridge <kim@rhinoloft.com>

     * @return array $arr (See below)
     *
     * array['id'                       => (string) id of user
     *       'verification_state'       => (string) verification state of user
     *       'held_state'               => (string) held state of user
     *       'wallets'                  => [
     *          'id'                    => (string) id of wallet
     *          'currency'              => (string) currency of wallet
     *          'balance'               => (integer) balance of wallet in cents
     *          'bpay_biller_code'      => (string) BPAY biller code
     *          'balabpay_reference'    => (string) BPAY reference
     *          ]
     *      ]
     */
    public function getUserDetails($customerId, $firstName=null, $lastName=null, $msisdn=null)
    {
        //Log::debug(Session::getId() . " AssemblyPayments:getUserDetails start {$customerId}");
        try {
            //Add '+' to msisdn
            $msisdn = '+' . preg_replace('/^\++(?=\d)/', '', $msisdn);

            //Check if user already exists on assembly payments
            try {
                $user = $this->getUser($customerId);
            } catch (\Exception $e) {
                //if user does not exist, then we will create it now
                if ($e instanceof Api && $this->isError($e, 422, 'id: invalid')) {
                    //Log::debug(Session::getId() . " AssemblyPayments:getUserDetails user not found, creating..");
                    if (isset($firstName) && isset($lastName) && isset($msisdn)) {
                        //Log::debug(Session::getId() . " AssemblyPayments:getUserDetails creating new user {$customerId} {$firstName} {$lastName}");
                        $user = $this->createUser($customerId, $firstName, $lastName, $msisdn);
                    } else {
                        throw $e;
                    }
                } else {
                    throw $e;
                }
            }

            //Get relevant data from $user response
            $userDetails = array(
                'id' =>$customerId,
                'verification_state' => $user['verification_state'],
                'held_state' => $user['held_state'],
                'wallets' => $this->getWalletDetails($customerId)
            );

        } catch (\Exception $e) {
            //Log::debug(Session::getId() . " AssemblyPayments:registerCallback caught exception");
            throw new CoinLoftPaymentException('getUserDetails failed', 999, $e);
        }

        return $userDetails;
    }

    /**
     * Sweep the balance from a customer's wallet to OUR merchant wallet
     *
     * @param int $id Unique Order or Payment ID from the consuming system
     * @param int $customerId Unique Customer ID from the consuming system
     * @param int $amount Amount to sweep from the wallet in cents
     * @param string $name Name of the payent (eg: Payment for Order 123)
     *
     * @throws CoinLoftPaymentException
     * @author Kim Beveridge <kim@rhinoloft.com>

     * @return array $arr (See below)
     *
     * array['id'                       => (string) id of payment
     *       'amount'                   => (int) amount in cents
     *       'state'                    => (string) state of payment
     *       'disbursement'             => [   disbursement is the fee taken for the payment
     *          'id'                    => (string) id of disbursement
     *          'amount'                => (string) amount in cents
     *          'state'                 => (string) state of disbursement
     *          ]
     *      ]
     */
    public function sweepCustomerWallet($id, $customerId, $amount, $name)
    {
        //Log::debug(Session::getId() . " AssemblyPayments:sweepCustomerWallet start {$id}");
        try {
            $wallet = $this->getDigitalWalletAccounts($customerId);
            $item = $this->createItem($id, $customerId, $amount, $name);
            $payment = $this->itemMakePayment($id, $wallet['id']);
            $disbursement = $this->getDisbursementTransactions($this->params['asm_user_id'],
                Carbon::createFromTimeString($payment['created_at']));

            $arr = array(
                'id' => $payment['id'],
                'amount' => $payment['released_amount'],
                'state' => $payment['state'],
                'disbursement' => array()
            );

            if (count($disbursement) == 1) {
                $arr['disbursement'] = array(
                    'id' => $disbursement[0]['id'],
                    'amount' => $disbursement[0]['amount'],
                    'state' => $disbursement[0]['state']
                );
            }
        } catch (\Exception $e) {
            //Log::debug(Session::getId() . " AssemblyPayments:registerCallback caught exception");
            throw new CoinLoftPaymentException('sweepCustomerWallet failed', 999, $e);
        }

        return $arr;
    }

    /**
     * Get list of BPay transations for a customer since a specific time
     *
     * @param int $customerId Unique Customer ID from the consuming system
     * @param Carbon $timestamp Carbon timestamp object - Filter transactions to after this timestamp
     * @param int $limit Limit number of transactions returned of the payent (eg: Payment for Order 123)
     *
     * @throws CoinLoftPaymentException
     * @author Kim Beveridge <kim@rhinoloft.com>

     * @return array $arr (See below)
     *
     * array['id'                       => (string) id of transaction
     *       'state'                    => (string) state of transaction
     *       'amount'                   => (int) amount in cents
     *      ]
     */
    public function getBpayTransactions($customerId, $timestamp = null, $limit = 10)
    {
        //Log::debug(Session::getId() . " AssemblyPayments:getBpayTransactions start {$id}");
        try {
            $wallet = $this->getDigitalWalletAccounts($customerId);

            $t = new Transaction();
            $transactions = $t->getList(array(
                'account_id' => $wallet['id'],
                'limit' => $limit,
                'transaction_type' => 'deposit',
                'type_method' => 'bpay',
                'direction' => 'credit'
            ));

            //Filter out based on timestamp
            if (isset($timestamp)) {
                foreach ($transactions as $key => $transaction) {
                    if (Carbon::createFromTimeString($transaction['created_at'])->lessThan($timestamp)) {
                        unset($transactions[$key]);
                    }
                }
            }
        } catch (\Exception $e) {
            //Log::debug(Session::getId() . " AssemblyPayments:registerCallback caught exception");
            throw new CoinLoftPaymentException('getBpayTransactions failed', 999, $e);
        }

        return $transactions;
    }

}
