<?php

namespace Payever\Tests\Unit\Core\Helper;

use Payever\Sdk\Core\Helper\DataHelper;
use Payever\Sdk\Core\Http\RequestEntity;
use PHPUnit\Framework\TestCase;

/**
 * Class DataHelperTest
 *
 * This class is designed to test the DataHelper class and especially the getEntityInstance method.
 *
 * @package Payever\Tests\Payever\Sdk\Core\Helper
 */
class DataHelperTest extends TestCase
{
    public function testItReturnsNullWhenDataIsEmpty()
    {
        $this->assertNull(DataHelper::getEntityInstance(null, RequestEntity::class));
    }

    public function testItReturnsSameInstanceWhenDataIsAnInstanceOfClass()
    {
        $instance = new RequestEntity([]);
        $this->assertSame($instance, DataHelper::getEntityInstance($instance, RequestEntity::class));
    }

    public function testItReturnsNullWhenDataIsNeitherObjectNorArray()
    {
        $this->assertNull(DataHelper::getEntityInstance('not an array or object', RequestEntity::class));
    }

    public function testItCreatesClassInstanceWhenDataIsAnArray()
    {
        $this->assertInstanceOf(RequestEntity::class, DataHelper::getEntityInstance(['some data'], RequestEntity::class));
    }

    public function testItCreatesClassInstanceWhenDataIsAJsonString()
    {
        $this->assertInstanceOf(RequestEntity::class, DataHelper::getEntityInstance('{"some":"data"}', RequestEntity::class));
    }
}
