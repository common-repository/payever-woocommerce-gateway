<?php

namespace Payever\Tests\Unit\Payever\ThirdParty\Http\ResponseEntity;

use Payever\Sdk\ThirdParty\Http\ResponseEntity\BusinessResponseEntity;
use Payever\Tests\Unit\Payever\Core\Http\AbstractMessageEntityTest;

class BusinessResponseEntityTest extends AbstractMessageEntityTest
{
    protected static $scheme = array(
        'name' => 'stub',
        'currency' => 'EUR',
        'subscriptions' => array()
    );

    public static function getScheme()
    {
        $scheme = parent::getScheme();
        $scheme['subscriptions'][] = SubscriptionResponseEntityTest::getScheme();

        return $scheme;
    }

    public function getEntity()
    {
        return new BusinessResponseEntity();
    }
}
