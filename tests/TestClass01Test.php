<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use KimBev\Payments\TestClass01;
use Carbon\Carbon;

final class TestClass01Test extends TestCase
{
    private $tstClass01;

    protected function setUp()
    {
        parent::setUp();

        $params = array(
        'env'=>'prelive');

        $this->tstClass01 = new TestClass01($params);
    }

    /** @test */
    public function test01(): void
    {
        $value = $this->tstClass01->getCallbacks();

        $this->assertEquals("hello", $value);
    }

}
