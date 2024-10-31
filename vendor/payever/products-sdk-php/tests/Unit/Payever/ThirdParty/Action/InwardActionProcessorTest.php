<?php

namespace Payever\Tests\Unit\Payever\ThirdParty\Action;

use Payever\Sdk\ThirdParty\Action\ActionHandlerPool;
use Payever\Sdk\ThirdParty\Action\ActionResult;
use Payever\Sdk\ThirdParty\Action\InwardActionProcessor;
use Payever\Sdk\ThirdParty\Enum\ActionEnum;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;

class InwardActionProcessorTest extends TestCase
{
    /** @var ActionHandlerPool */
    private $handlerPool;

    /** @var InwardActionProcessor */
    private $actionRequestProcessor;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->handlerPool = $this->createTestProxy('Payever\Sdk\ThirdParty\Action\ActionHandlerPool');
        $actionResult = new ActionResult();
        $logger = new NullLogger();

        $this->actionRequestProcessor = new InwardActionProcessor(
            $this->handlerPool,
            $actionResult,
            $logger
        );
    }

    /**
     * @throws \Exception
     */
    public function testNoHandlers()
    {
        $this->expectException(\RuntimeException::class);
        $this->actionRequestProcessor->process('stub');
    }

    /**
     * @dataProvider actionListDataProvider
     *
     * @param string $action
     * @throws \Exception
     */
    public function testSuccess($action)
    {
        $handler = $this->getMockForAbstractClass('Payever\Sdk\ThirdParty\Action\ActionHandlerInterface');
        $handler->expects($this->once())->method('getSupportedAction')->willReturn($action);
        $handler->expects($this->once())->method('handle')->withAnyParameters();

        $this->handlerPool->registerActionHandler($handler);

        $this->actionRequestProcessor->process($action, '{}');
    }

    public function actionListDataProvider()
    {
        return array_map(function ($el) { return [$el]; }, ActionEnum::enum());
    }
}
