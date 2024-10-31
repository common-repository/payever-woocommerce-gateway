<?php

/**
 * PHP version 5.4 and 8
 *
 * @category  MessageEntity
 * @package   Payever\Payments
 * @author    payever GmbH <service@payever.de>
 * @copyright 2017-2023 payever GmbH
 * @license   MIT <https://opensource.org/licenses/MIT>
 * @link      https://docs.payever.org/shopsystems/api/getting-started
 */

namespace Payever\Sdk\Payments\Http\MessageEntity;

use DateTime;
use Payever\Sdk\Core\Helper\StringHelper;
use Payever\Sdk\Core\Http\MessageEntity\ResultEntity;

/**
 * This class represents Settle Payment Result Entity
 * @todo Make common class for `ClaimPaymentResultEntity`
 *
 * @method AddressEntity        getAddress()
 * @method AddressEntity        getShippingAddress()
 * @method float                getAmount()
 * @method string               getChannel()
 * @method string               getChannelType()
 * @method string               getChannelSource()
 * @method DateTime|null        getCreatedAt()
 * @method string               getCurrency()
 * @method string               getCustomerName()
 * @method string               getId()
 * @method CartItemEntity[]     getItems()
 * @method string               getMerchantName()
 * @method string               getPaymentType()
 * @method string               getReference()
 * @method string               getStatus()
 * @method string               getSpecificStatus()
 * @method float                getTotal()
 * @method float                getDeliveryFee()
 * @method float                getPaymentFee()
 * @method float                getDownPayment()
 * @method DateTime|null        getUpdatedAt()
 * @method PaymentDetailsEntity getPaymentDetails()
 * @method ShippingOptionEntity getShippingOption()
 * @method self                 setAmount(float $amount)
 * @method self                 setChannel(string $channel)
 * @method self                 setChannelType(string $type)
 * @method self                 setChannelSource(string $source)
 * @method self                 setCurrency(string $currency)
 * @method self                 setCustomerName(string $customerName)
 * @method self                 setId(string $id)
 * @method self                 setMerchantName(string $merchantName)
 * @method self                 setPaymentType(string $paymentType)
 * @method self                 setReference(string $reference)
 * @method self                 setStatus(string $status)
 * @method self                 setSpecificStatus(string $status)
 * @method self                 setTotal(float $total)
 * @method self                 setDeliveryFee(float $value)
 * @method self                 setPaymentFee(float $value)
 * @method self                 setDownPayment(float $value)
 *
 * @SuppressWarnings(PHPMD.ShortVariable)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class SettlePaymentResultEntity extends ResultEntity
{
    /**
     * @var AddressEntity
     */
    protected $address;

    /**
     * @var AddressEntity
     */
    protected $shippingAddress;

    /**
     * @var float
     */
    protected $amount;

    /**
     * @var string
     */
    protected $channel;

    /**
     * @var string
     */
    protected $channelType;

    /**
     * @var string
     */
    protected $channelSource;

    /**
     * @var DateTime|null
     */
    protected $createdAt;

    /**
     * @var string
     */
    protected $currency;

    /**
     * @var string
     */
    protected $customerName;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var array
     */
    protected $items;

    /**
     * @var string
     */
    protected $merchantName;

    /**
     * @var string
     */
    protected $paymentType;

    /**
     * @var string
     */
    protected $reference;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var string
     */
    protected $specificStatus;

    /**
     * @var float
     */
    protected $total;

    /**
     * @var float
     */
    protected $deliveryFee;

    /**
     * @var float
     */
    protected $downPayment;

    /**
     * @var float
     */
    protected $paymentFee;

    /**
     * @var DateTime|null $updatedAt
     */
    protected $updatedAt;

    /**
     * @var PaymentDetailsEntity
     */
    protected $paymentDetails;

    /**
     * @var ShippingOptionEntity
     */
    protected $shippingOption;

    /**
     * Sets Address
     *
     * @param array $address
     * @return self
     */
    public function setAddress($address)
    {
        $this->address = new AddressEntity($address);

        return $this;
    }

    /**
     * Sets Shipping Address
     *
     * @param array $address
     * @return self
     */
    public function setShippingAddress($address)
    {
        $this->shippingAddress = new AddressEntity($address);

        return $this;
    }

    /**
     * Sets Created At
     *
     * @param string $createdAt
     * @return self
     */
    public function setCreatedAt($createdAt)
    {
        if (!$createdAt) {
            return $this;
        }

        if ($createdAt instanceof DateTime) {
            $this->createdAt = $createdAt;

            return $this;
        }

        if (is_string($createdAt)) {
            $this->createdAt = date_create($createdAt);
        }

        return $this;
    }

    /**
     * Sets Updated At
     *
     * @param string|DateTime $updatedAt
     * @return self
     */
    public function setUpdatedAt($updatedAt)
    {
        if (!$updatedAt) {
            return $this;
        }

        if ($updatedAt instanceof DateTime) {
            $this->updatedAt = $updatedAt;

            return $this;
        }

        if (is_string($updatedAt)) {
            $this->updatedAt = date_create($updatedAt);
        }

        return $this;
    }

    /**
     * Sets Payment Details
     *
     * @param array $paymentDetails
     * @return self
     */
    public function setPaymentDetails($paymentDetails)
    {
        $this->paymentDetails = new PaymentDetailsEntity($paymentDetails);

        return $this;
    }

    /**
     * Set the shipping option for this entity.
     *
     * @param ShippingOptionEntity $shippingOption The shipping option to be set.
     *
     * @return self The updated entity.
     */
    public function setShippingOption($shippingOption)
    {
        $this->shippingOption = new ShippingOptionEntity($shippingOption);

        return $this;
    }

    /**
     * Sets Items
     *
     * @param array|string $items
     * @return self
     */
    public function setItems($items)
    {
        if (is_string($items)) {
            $items = StringHelper::jsonDecode($items);
        }

        if ($items && is_array($items)) {
            foreach ($items as $item) {
                $this->items[] = new CartItemEntity($item);
            }
        }

        return $this;
    }
}
