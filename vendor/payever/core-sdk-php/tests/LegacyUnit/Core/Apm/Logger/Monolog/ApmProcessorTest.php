<?php

namespace Payever\Tests\Unit\Core\Apm\Logger\Monolog;

use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Payever\Sdk\Core\Apm\ApmApiClient;
use Payever\Sdk\Core\Apm\Logger\Monolog\ApmProcessor;
use Payever\Sdk\Core\Base\ClientConfigurationInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ApmProcessorTest extends TestCase
{
    /** @var ApmProcessor */
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
    protected function setUp()
    {
        if (version_compare(\Monolog\Logger::API, '3', '>=')) {
            $this->markTestSkipped('Monolog API v1/v2 is required.');
        }

        $this->clientConfiguration = $this->getMockBuilder(ClientConfigurationInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->apmApiClient = $this->getMockBuilder(ApmApiClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->apmProcessor = new ApmProcessor(
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
    protected function tearDown()
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
        $this->logger->critical('Test Monolog v' . Logger::API);
    }
}
