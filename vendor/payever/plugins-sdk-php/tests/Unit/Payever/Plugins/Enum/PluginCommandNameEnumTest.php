<?php

namespace Payever\Tests\Unit\Payever\Plugins\Enum;

use Payever\Sdk\Plugins\Enum\PluginCommandNameEnum;
use PHPUnit\Framework\TestCase;

class PluginCommandNameEnumTest extends TestCase
{
    public function testCommandNames()
    {
        $this->assertEquals(
            $this->collectConstants('Payever\Sdk\Plugins\Enum\PluginCommandNameEnum'),
            PluginCommandNameEnum::enum()
        );
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
