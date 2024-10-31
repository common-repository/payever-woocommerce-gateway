<?php

namespace Payever\Tests\Unit\Core\Http\MessageEntity;

use Payever\Sdk\Core\Http\MessageEntity\CallEntity;
use Payever\Tests\Unit\Core\Http\AbstractMessageEntityTest;

/**
 * Class CallEntityTest
 *
 * @see \Payever\Sdk\Core\Base\MessageEntity
 *
 * @package Payever\Tests\Unit\Core\Http\MessageEntity
 */
class CallEntityTest extends AbstractMessageEntityTest
{
    protected static $scheme = array(
        'id' => 'stub',
        'status' => 'failed',
        'business_id' => 'stub_business',
        'created_at' => self::DEFAULT_STUB_DATE,
    );

    public function getEntity()
    {
        return new CallEntity();
    }
}
