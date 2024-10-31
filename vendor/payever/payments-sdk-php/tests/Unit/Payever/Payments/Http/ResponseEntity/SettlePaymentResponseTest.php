<?php

namespace Payever\Tests\Payever\Sdk\Payments\Http\ResponseEntity;

use Payever\Sdk\Payments\Http\MessageEntity\PaymentCallEntity;
use Payever\Sdk\Payments\Http\MessageEntity\SettlePaymentResultEntity;
use Payever\Sdk\Payments\Http\ResponseEntity\SettlePaymentResponse;
use PHPUnit\Framework\TestCase;

class SettlePaymentResponseTest extends TestCase
{
    /**
     * @var SettlePaymentResponse
     */
    private $settlePaymentResponse;

    protected function setUp(): void
    {
        $this->settlePaymentResponse = new SettlePaymentResponse();
    }

    /**
     * @dataProvider paymentCallDataProvider
     */
    public function testSetCall($paymentCall)
    {
        $this->settlePaymentResponse->setCall($paymentCall);

        $this->assertInstanceOf(
            PaymentCallEntity::class,
            $this->settlePaymentResponse->getCall(),
            'Call in the SettlePaymentResponse instance is expected to be an instance of PaymentCallEntity'
        );
    }

    public function paymentCallDataProvider()
    {
        return [
            [['payment_id' => 'PID123', 'transaction_id' => 'TID123']],
            [['payment_id' => 'PID456', 'transaction_id' => 'TID456']],
        ];
    }

    /**
     * Testing SettlePaymentResponse::setResult method.
     * Test case: successful setting of the result.
     */
    public function testSetResult(): void
    {
        // Preparing test data
        $testData = ["status" => "accepted"];

        // Executing method
        $this->settlePaymentResponse->setResult($testData);

        // Getting reflection of the class to access private property
        $reflectionClass = new \ReflectionClass($this->settlePaymentResponse);
        $reflectionProperty = $reflectionClass->getProperty('result');
        $reflectionProperty->setAccessible(true);

        // Check that setResult actually sets the result with correct instance
        $this->assertInstanceOf(SettlePaymentResultEntity::class, $reflectionProperty->getValue($this->settlePaymentResponse));
    }
}
