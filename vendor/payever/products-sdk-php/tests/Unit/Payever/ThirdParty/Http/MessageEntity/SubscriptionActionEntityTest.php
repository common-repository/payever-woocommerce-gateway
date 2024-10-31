<?php

namespace Payever\Tests\Unit\Payever\ThirdParty\Http\MessageEntity;

use Payever\Sdk\Core\Base\MessageEntity;
use Payever\Sdk\ThirdParty\Http\MessageEntity\SubscriptionActionEntity;
use Payever\Tests\Unit\Payever\Core\Http\AbstractMessageEntityTest;

class SubscriptionActionEntityTest extends AbstractMessageEntityTest
{
    /** @var array */
    protected static $scheme = array(
        'name' => 'create-product',
        'url' => 'https://some.domain/action/create-produce',
        'method' => 'POST',
    );

    /**
     * @return MessageEntity|SubscriptionActionEntity
     */
    public function getEntity()
    {
        return new SubscriptionActionEntity();
    }
}
