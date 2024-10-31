<?php

namespace Payever\Tests\Unit\Core\Enum;

use Payever\Sdk\Core\Enum\ChannelSet;
use PHPUnit\Framework\TestCase;

/**
 * Class ChannelSetTest
 *
 * @see \Payever\Sdk\Core\Enum\ChannelSet
 *
 * @package Payever\Sdk\Core
 */
class ChannelSetTest extends TestCase
{
    /**
     * @see \Payever\Sdk\Core\Enum\ChannelSet::getList()
     *
     * @throws \ReflectionException
     */
    public function testGetList()
    {
        $this->assertEquals($this->collectConstants('Payever\Sdk\Core\Enum\ChannelSet'), ChannelSet::enum());
    }

    public function testConstantNameByValue()
    {
        $this->assertNotEmpty(ChannelSet::constantNameByValue('other_shopsystem'));
    }

    public function testConstantNameByValueCaseNotFound()
    {
        $this->assertEmpty(ChannelSet::constantNameByValue('unknown-value'));
    }

    public function testValueOf()
    {
        $this->assertNotEmpty(ChannelSet::valueOf('CHANNEL_OTHER_SHOPSYSTEM'));
    }

    /**
     * @return array
     *
     * @throws \ReflectionException
     */
    private function collectConstants($className)
    {
        $reflection = new \ReflectionClass($className);

        return $reflection->getConstants();
    }
}
