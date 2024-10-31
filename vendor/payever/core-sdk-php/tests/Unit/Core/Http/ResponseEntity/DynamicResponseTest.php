<?php

namespace Payever\Tests\Unit\Core\Http\ResponseEntity;

use Payever\Sdk\Core\Http\ResponseEntity\DynamicResponse;
use Payever\Tests\Unit\Core\Http\AbstractResponseEntityTest;
use Payever\Tests\Unit\Core\Http\MessageEntity\DynamicEntityTest;

/**
 * Class DynamicResponseTest
 *
 * @see \Payever\Sdk\Core\Http\ResponseEntity\DynamicResponse
 *
 * @package Payever\Tests\Unit\Core\Http\ResponseEntity
 */
class DynamicResponseTest extends AbstractResponseEntityTest
{
    public static function getScheme()
    {
        return DynamicEntityTest::getScheme();
    }

    public function getEntity()
    {
        return new DynamicResponse();
    }
}
