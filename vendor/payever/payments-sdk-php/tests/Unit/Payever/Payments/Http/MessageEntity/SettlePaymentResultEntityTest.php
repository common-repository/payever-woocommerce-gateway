<?php

namespace Payever\Tests\Payever\Sdk\Payments\Http\MessageEntity;

use DateTime;
use Payever\Sdk\Payments\Http\MessageEntity\SettlePaymentResultEntity;
use Payever\Sdk\Payments\Http\MessageEntity\PaymentDetailsEntity;
use Payever\Sdk\Payments\Http\MessageEntity\AddressEntity;
use PHPUnit\Framework\TestCase;

class SettlePaymentResultEntityTest extends TestCase
{
    private $settlePaymentResultEntity;

    protected function setUp(): void
    {
        $this->settlePaymentResultEntity = new SettlePaymentResultEntity();
    }

    /**
     * This test case is for the `setPaymentDetails` method in the `SettlePaymentResultEntity` class.
     */
    public function testSetPaymentDetails()
    {
        $paymentDetailsArray = array(
            'type' => 'credit_card',
            'cardNumber' => '4111111111111111',
            'expiryDate' => '2023-12',
            'ccv' => '123',
        );

        $this->settlePaymentResultEntity->setPaymentDetails($paymentDetailsArray);

        $this->assertInstanceOf(
            PaymentDetailsEntity::class,
            $this->settlePaymentResultEntity->getPaymentDetails()
        );
    }

    /**
     * This test case is for the `setAddress` method in the `SettlePaymentResultEntity` class.
     */
    public function testSetAddress()
    {
        $address = array(
            'country' => 'USA',
            'state' => 'California',
            'city' => 'Los Angeles',
            'street' => 'Some St, 20',
        );

        $this->settlePaymentResultEntity->setAddress($address);

        $this->assertInstanceOf(
            AddressEntity::class,
            $this->settlePaymentResultEntity->getAddress()
        );
    }

    /**
     * This test case is for the `setShippingAddress` method in the `SettlePaymentResultEntity` class.
     */
    public function testSetShippingAddress()
    {
        $shippingAddress = array(
            'country' => 'USA',
            'state' => 'California',
            'city' => 'Los Angeles',
            'street' => 'Some St, 20',
        );

        $this->settlePaymentResultEntity->setShippingAddress($shippingAddress);

        $this->assertInstanceOf(
            AddressEntity::class,
            $this->settlePaymentResultEntity->getShippingAddress()
        );
    }

    /**
     * This test case is for the `setCreatedAt` method in the `SettlePaymentResultEntity` class.
     */
    public function testSetCreatedAt()
    {
        $createdAt = '2000-01-01';

        $this->settlePaymentResultEntity->setCreatedAt($createdAt);

        $this->assertInstanceOf(
            DateTime::class,
            $this->settlePaymentResultEntity->getCreatedAt()
        );
    }

    /**
     * This test case is for the `setUpdatedAt` method in the `SettlePaymentResultEntity` class.
     */
    public function testSetUpdatedAt()
    {
        $updatedAt = '2000-01-01';

        $this->settlePaymentResultEntity->setUpdatedAt($updatedAt);

        $this->assertInstanceOf(
            DateTime::class,
            $this->settlePaymentResultEntity->getUpdatedAt()
        );
    }
}
