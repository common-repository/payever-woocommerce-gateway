<?php

namespace Payever\Tests\Unit\Payever\Payments\Enum;

use Payever\Sdk\Payments\Enum\Status;
use PHPUnit\Framework\TestCase;

/**
 * Class StatusTest
 *
 * @see \Payever\Sdk\Payments\Enum\Status
 *
 * @package Unit\Payever\Sdk\Payments
 */
class StatusTest extends TestCase
{
    /**
     * @see \Payever\Sdk\Payments\Enum\Status::getList()
     */
    public function testGetList()
    {
        $this->assertEquals(
            $this->collectConstants('Payever\Sdk\Payments\Enum\Status'),
            Status::enum()
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
