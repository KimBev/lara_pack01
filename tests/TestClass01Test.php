<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use CoinLoft\Payments\AssemblyPayments;
use Carbon\Carbon;

final class AssemblyPaymentsTest extends TestCase
{
    private $asbly;

    protected function setUp()
    {
        parent::setUp();

        $params = array(
        'env'=>'prelive',
        'login'=>'kim@bitcoin.com.au',
        'token'=>'MjE4NGYzYTc1Y2FmZDMzNDNjMDg3ODU0ZjFkZjZlN2M=',
        'callback_transactions'=>'https://devclapi.coinloft.com.au/assembly',
        'asm_user_id'=> '0be5032b25c1ddceb31fb25edbed384a',
        'email_domain'=>'coinloft.com.au');

//        $params = array(
//            'env'=>'production',
//            'login'=>'kim@bitcoin.com.au',
//            'token'=>'xxxxxxx=',
//            'callback_transactions'=>'https://clau-api.coinloft.com:8452/v1/order/payment/notify/assembly/bpay',
//            'asm_user_id'=> 'd37b4d08aff3dbc7ab2b5cabf5a040cf',
//            'email_domain'=>'coinloft.com.au');

        $this->asbly = new AssemblyPayments($params);

        $this->asbly->registerCallback();
    }


    /** @test_off */
    public function raw_messages(): void
    {

//        print_r($asbly->getMarketPlace());

//        print_r($asbly->getOurAccountDetails());


        //RAW User Messages
        //=================
//        print_r($asbly->createUser(123,'Kim'));

//        print_r($this->asbly->getUser(10001));

//        print_r($asbly->getDigitalWalletAccounts(123));

//        print_r($this->asbly->getBpayTransactions(10010));


        //Consolidated User Messages
        //=================

//        $userDetails = $asbly->getUserDetails(10001, 'Fred','Jones', '61412995222');

//        print_r($asbly->getTransactions('0be5032b25c1ddceb31fb25edbed384a'));

//        print_r($asbly->getBpayTransactionsSince(10001, Carbon::now()->addMinute(-30)));

//        print_r($this->asbly->getDispersementTransactions('0be5032b25c1ddceb31fb25edbed384a'));
//        print_r($this->asbly->getItemTransactions(121218));
//        print_r($this->asbly->getItemFees(121218));
//        print_r($this->asbly->getTransaction('34cbac85-c560-4851-b43b-0bc510ce4954'));

        //Do the BPAY deposit
        //=================
//        $asbly->testProcessBpay(array('crn'=>'9140623006','amount'=>100));


//        print_r($asbly->getTransactions(10001));
//        print_r($asbly->getTransactions('0be5032b25c1ddceb31fb25edbed384a'));


        //Item messages
        //=================

//        print_r($asbly->createItem(121212,10001,300,'sweep for order'));

//        print_r($asbly->itemMakePayment(121212,'fa1e1937-28b5-4d8f-9758-48966d76b153'));

//        print_r($asbly->getItemFees(121212));

//        print_r($asbly->sweepCustomerWallet(121213,10001, 100, 'sweep for bla'));

//        print_r($this->asbly->getUserDetails(10001, 'Fred','Jones', '61412995222'));

//        print_r($this->asbly->getOurDetails());

        $this->assertEquals(1,1);

    }


    /** @test */
    public function bpay_flow(): void
    {
        //These need to be incremented for each test
        $customerId = 10019;  //Unique Customer ID from calling platform
        $orderId = 121231;  //Unique Order ID from calling platform
        $mobile = "61412995223";

        //Take note of OUR wallet balance, so we can compare it at the end
        $ourDetails_at_start = $this->asbly->getOurDetails();
        print_r("\nOur details at start :" . print_r($ourDetails_at_start,true));

        //Create User
        $userDetails = $this->asbly->getUserDetails($customerId,'Anthony', 'Jones', $mobile);

        print_r("\nCreated User :" .print_r($userDetails,true));
        $this->assertEquals(0,$userDetails['wallets'][0]['balance']);

        //Process a TEST bpay deposit by the customer
        $this->asbly->testProcessBpay(array('crn'=>$userDetails['wallets'][0]['bpay_reference'],'amount'=>100));

        //Get list of BPay transactions
        $transactions = $this->asbly->getBpayTransactions($customerId, Carbon::now()->addMinute(-1));
        print_r("\nList of BPay transactions :" . print_r($transactions,true));
        $this->assertEquals(1,count($transactions));

        //Check user details again to see his wallet is 100
        $userDetails = $this->asbly->getUserDetails($customerId);
        print_r("\nUser after bpayment :" . print_r($userDetails,true));
        $this->assertEquals(100,$userDetails['wallets'][0]['balance']);

        //Sweep the balance from customer wallet to our wallet
        $payment = $this->asbly->sweepCustomerWallet($orderId,$customerId, 100, 'sweep for bla');
        print_r("\nSweep balance payment :" . print_r($payment,true));
        $this->assertNotNull($payment);

        //Check user details again to see his wallet is 0
        $userDetails = $this->asbly->getUserDetails($customerId);
        print_r("\nUser after sweep :" . print_r($userDetails,true));
        $this->assertEquals(0,$userDetails['wallets'][0]['balance']);

        //Check OUR wallet to see we now have the funds
        $ourDetails_at_end = $this->asbly->getOurDetails();
        print_r("\nOur details at end :" . print_r($ourDetails_at_end,true));
        $increasedBalance = $ourDetails_at_end['wallets'][0]['balance'] - $ourDetails_at_start['wallets'][0]['balance'];
        $expectedBalance = 100 - $payment['disbursement']['amount'];
        $this->assertEquals($expectedBalance, $increasedBalance);
    }


}
