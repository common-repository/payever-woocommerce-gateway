<?php

namespace Payever\Tests\Unit\Core\Http\ResponseEntity;

use Payever\Sdk\Core\Http\ResponseEntity\ListChannelSetsResponse;
use Payever\Tests\Unit\Core\Http\AbstractResponseEntityTest;
use Payever\Tests\Unit\Core\Http\MessageEntity\CallEntityTest;
use Payever\Tests\Unit\Core\Http\MessageEntity\ListChannelSetsResultEntityTest;

/**
 * Class ListChannelSetsResponseTest
 *
 * @see \Payever\Sdk\Core\Http\ResponseEntity\ListChannelSetsResponse
 *
 * @package Payever\Tests\Unit\Core\Http\ResponseEntity
 */
class ListChannelSetsResponseTest extends AbstractResponseEntityTest
{
    public static function getScheme()
    {
        return array(
            'call' => CallEntityTest::getScheme(),
            'result' => array(
                ListChannelSetsResultEntityTest::getScheme(),
            ),
        );
    }

    public function getEntity()
    {
        return new ListChannelSetsResponse();
    }
}
