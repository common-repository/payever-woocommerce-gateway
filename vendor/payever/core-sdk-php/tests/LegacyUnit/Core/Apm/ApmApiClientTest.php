<?php

namespace Payever\Tests\LegacyUnit\Core\Apm;

use Payever\Sdk\Core\Apm\ApmApiClient;
use Payever\Sdk\Core\Authorization\ApmSecretService;
use Payever\Sdk\Core\ClientConfiguration;
use Payever\Sdk\Core\Http\Client\CurlClient;
use Payever\Sdk\Core\Http\Request;
use Payever\Sdk\Core\Http\RequestBuilder;
use Payever\Sdk\Core\Http\Response;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit_Framework_MockObject_MockObject;

class ApmApiClientTest extends TestCase
{
    /**
     * @var (ClientConfiguration&MockObject)|PHPUnit_Framework_MockObject_MockObject
     */
    private $clientConfiguration;

    /**
     * @var ApmApiClient
     */
    private $apmApiClient;

    /**
     * @var (CurlClient&MockObject)|PHPUnit_Framework_MockObject_MockObject
     */
    private $httpClientMock;

    /**
     * @var (RequestBuilder&MockObject)|PHPUnit_Framework_MockObject_MockObject
     */
    private $requestBuilderMock;

    /**
     * @var (Request&MockObject)|PHPUnit_Framework_MockObject_MockObject
     */
    private $requestMock;

    protected function setUp()
    {
        $this->clientConfiguration = $this->getMockBuilder(ClientConfiguration::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->httpClientMock = $this->getMockBuilder(CurlClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->requestBuilderMock = $this->getMockBuilder(RequestBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->requestMock = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->apmApiClient = new ApmApiClient($this->clientConfiguration);
        $this->apmApiClient->setHttpClient($this->httpClientMock);
    }

    public function testSendLog()
    {
        $this->clientConfiguration->expects($this->once())
            ->method('getLogDiagnostic')
            ->willReturn(true);

        $this->clientConfiguration->expects($this->once())
            ->method('getApmSecretService')
            ->willReturn(
                $apmSecretService = $this->getMockBuilder(ApmSecretService::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

        $apmSecretService->expects($this->once())
            ->method('get')
            ->willReturn('test');

        $this->httpClientMock->expects($this->once())
            ->method('execute')
            ->willReturn(
                $response = $this->getMockBuilder(Response::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

        $response->expects($this->once())
            ->method('isSuccessful')
            ->willReturn(true);

        $result = $this->apmApiClient->sendLog('log message', 'info');
        $this->assertTrue($result);
    }

    public function testGenerateEventId()
    {
        $eventId = $this->apmApiClient->generateEventId();
        $this->assertTrue(is_string($eventId));
    }

    public function testGetTimestamp()
    {
        $timestamp = $this->apmApiClient->getTimestamp();
        $this->assertTrue(is_int($timestamp));
    }
}
