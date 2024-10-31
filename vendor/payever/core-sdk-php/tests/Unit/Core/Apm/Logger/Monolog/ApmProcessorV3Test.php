<?php

namespace Payever\Tests\Unit\Core\Apm\Logger\Monolog;

use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Payever\Sdk\Core\Apm\ApmApiClient;
use Payever\Sdk\Core\Apm\Logger\Monolog\ApmProcessorV3;
use Payever\Sdk\Core\Base\ClientConfigurationInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ApmProcessorV3Test extends TestCase
{
    /** @var ApmProcessorV3 */
    private $apmProcessor;

    /** @var ClientConfigurationInterface|MockObject */
    private $clientConfiguration;

    /** @var ApmApiClient|MockObject */
    private $apmApiClient;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * This method is called before each test.
     */
    protected function setUp(): void
    {
        if (version_compare(\Monolog\Logger::API, '3', '<')) {
            $this->markTestSkipped('Monolog API v3 is required.');
        }

        $this->clientConfiguration = $this->getMockBuilder(ClientConfigurationInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->apmApiClient = $this->getMockBuilder(ApmApiClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->apmProcessor = new ApmProcessorV3(
            $this->clientConfiguration
        );

        $apmApiClientReflection = new \ReflectionClass($this->apmProcessor);
        $apmApiClientProperty = $apmApiClientReflection->getProperty('apmApiClient');
        $apmApiClientProperty->setAccessible(true);
        $apmApiClientProperty->setValue($this->apmProcessor, $this->apmApiClient);
    }

    /**
     * This method is called after each test.
     */
    protected function tearDown(): void
    {
        unset($this->apmProcessor);
        unset($this->clientConfiguration);
    }

    /**
     * Test __invoke method.
     */
    public function testInvoke()
    {
        $this->logger = new Logger('test');
        $this->logger->pushProcessor($this->apmProcessor);
        $this->logger->pushHandler(new NullHandler());

        $this->apmApiClient->expects($this->once())
            ->method('sendLog');
        $this->logger->critical('Test Monolog API v' . Logger::API);
    }
}
