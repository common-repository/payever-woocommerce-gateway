<?php

namespace Payever\Tests\Unit\Core;

use Monolog\Logger;
use Payever\Sdk\Core\ClientConfiguration;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ClientConfigurationTest extends TestCase {

    /**
     * @var ClientConfiguration
     */
    private $clientConfiguration;

    protected function setUp(): void
    {
        $this->clientConfiguration = new ClientConfiguration();
    }

    /**
     * @covers \Payever\Sdk\Core\ClientConfiguration::setLogger
     */
    public function testSetLogger()
    {
        // Testing with NullLogger.
        $logger = $this->buildMock(NullLogger::class, ['pushProcessor']);
        $logger->expects($this->never())
            ->method('pushProcessor');
        $return = $this->clientConfiguration->setLogger($logger);
        $this->assertInstanceOf(ClientConfiguration::class, $return, 'Asserting that the returned value of calling setLogger() is an instance of ClientConfiguration class for validity');
        $this->assertEquals($logger, $this->clientConfiguration->getLogger(), 'Asserting that the getLogger() method returns the object that we just set using setLogger()');

        // Asserting NullLogger setting when 'logDiagnostic' property is 'false'.
        $this->clientConfiguration->setLogDiagnostic(false);
        $logger = $this->buildMock(NullLogger::class, ['pushProcessor']);
        $logger->expects($this->never())
            ->method('pushProcessor');
        $this->clientConfiguration->setLogger($logger);
        $this->assertEquals($logger, $this->clientConfiguration->getLogger(), 'Asserting NullLogger setting when logDiagnostic is false');

        // Asserting Monolog Logger
        $logger = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->getMock();
        $logger->expects($this->once())
            ->method('pushProcessor');
        $this->clientConfiguration->setLogDiagnostic(true);
        $this->clientConfiguration->setLogger($logger);
        $this->assertEquals($logger, $this->clientConfiguration->getLogger());
    }

    public function testSetClientId()
    {
        $clientId = '1234567890';
        $return = $this->clientConfiguration->setClientId($clientId);
        $this->assertInstanceOf(
            ClientConfiguration::class,
            $return,
            'Asserting that the returned value of calling setClientId() is an instance of ClientConfiguration class for validity'
        );
        $this->assertEquals(
            $clientId,
            $this->clientConfiguration->getClientId(),
            'Asserting that the getClientId() method returns the value that we just set using setClientId()'
        );
    }

    private function buildMock($className, $methods = [])
    {
        $builder = $this->getMockBuilder($className)
            ->disableOriginalConstructor();

        if (count($methods) > 0) {
            if (method_exists($builder, 'setMethods')) {
                $builder->setMethods($methods);
            } elseif (method_exists($builder, 'addMethods')) {
                $builder->addMethods($methods);
            }
        }

        return $builder->getMock();
    }
}
