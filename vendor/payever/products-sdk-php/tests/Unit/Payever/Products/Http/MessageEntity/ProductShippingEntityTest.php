<?php

namespace Payever\Tests\Unit\Payever\Products\Http\MessageEntity;

use Payever\Sdk\Products\Http\MessageEntity\ProductShippingEntity;
use Payever\Tests\Unit\Payever\Core\Http\AbstractMessageEntityTest;

class ProductShippingEntityTest extends AbstractMessageEntityTest
{
    protected static $scheme = array(
        'measureMass' => 'kg',
        'measureSize' => 'cm',
        'general' => true,
        'free' => true,
        'weight' => 1.1,
        'width' => 1.2,
        'height' => 1.3,
        'length' =>  1.4,
    );

    public function getEntity()
    {
        return new ProductShippingEntity();
    }
}
