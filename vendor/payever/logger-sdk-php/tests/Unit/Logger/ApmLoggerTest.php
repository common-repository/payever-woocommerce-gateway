<?php

namespace Payever\Tests\Unit\Logger;

use Payever\Sdk\Core\Apm\ApmApiClient;
use Payever\Sdk\Core\ClientConfiguration;
use Payever\Sdk\Logger\ApmLogger;
use Payever\Sdk\Logger\NullLogger;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Log\LogLevel;

class ApmLoggerTest extends TestCase
{
    /**
     * @var (ApmApiClient&MockObject)|PHPUnit_Framework_MockObject_MockObject
     */
    private $apmApiClient;

    /**
     * @var (NullLogger&MockObject)|MockObject
     */
    private $logger;

    /** @var ApmLogger */
    private $apmLogger;

    public function setUp(): void
    {
        $clientConfigurationMock = $this->createMock(ClientConfiguration::class);

        $this->apmApiClient = $this->getMockBuilder(ApmApiClient::class)
            ->disableOriginalConstructor()
            ->setMethods(['sendLog'])
            ->getMock();

        $this->logger = $this->getMockBuilder(NullLogger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->apmLogger = new ApmLogger($this->logger, $clientConfigurationMock);

        $apmApiClientReflection = new \ReflectionClass($this->apmLogger);
        $apmApiClientProperty = $apmApiClientReflection->getProperty('apmApiClient');
        $apmApiClientProperty->setAccessible(true);
        $apmApiClientProperty->setValue($this->apmLogger, $this->apmApiClient);
    }

    /**
     * @dataProvider provideLevels
     */
    public function testLog($level, $shouldCall)
    {
        $message = 'Test message';

        $this->logger->expects($this->once())
            ->method('log')
            ->with($level, $message, []);

        $this->apmApiClient->expects($shouldCall ? $this->once() : $this->never())
            ->method('sendLog')
            ->with($message, $level);

        $this->apmLogger->log($level, $message);
    }

    public function provideLevels()
    {
        return [
            [LogLevel::DEBUG, false],
            [LogLevel::INFO, false],
            [LogLevel::NOTICE, false],
            [LogLevel::WARNING, false],
            [LogLevel::ERROR, true],
            [LogLevel::CRITICAL, true],
            [LogLevel::ALERT, false],
            [LogLevel::EMERGENCY, false]
        ];
    }
}
