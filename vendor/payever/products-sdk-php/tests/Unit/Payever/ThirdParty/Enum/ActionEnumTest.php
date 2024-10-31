<?php

namespace Payever\Tests\Unit\Payever\ThirdParty\Enum;

use Payever\Sdk\ThirdParty\Enum\ActionEnum;
use PHPUnit\Framework\TestCase;

class ActionEnumTest extends TestCase
{
    public function testGetList()
    {
        $this->assertEquals(
            $this->collectConstants('Payever\Sdk\ThirdParty\Enum\ActionEnum'),
            ActionEnum::enum()
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
