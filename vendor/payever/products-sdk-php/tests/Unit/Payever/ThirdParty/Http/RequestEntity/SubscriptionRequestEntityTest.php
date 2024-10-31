<?php

namespace Payever\Tests\Unit\Payever\ThirdParty\Http\RequestEntity;

use Payever\Sdk\ThirdParty\Http\RequestEntity\SubscriptionRequestEntity;
use Payever\Tests\Unit\Payever\Core\Http\AbstractMessageEntityTest;

class SubscriptionRequestEntityTest extends AbstractMessageEntityTest
{
    protected static $scheme = array(
        'businessUuid' => 'stub_business',
        'externalId' => 'abcd',
        'thirdPartyName' => 'magento',
    );

    public function getEntity()
    {
        return new SubscriptionRequestEntity();
    }
}
