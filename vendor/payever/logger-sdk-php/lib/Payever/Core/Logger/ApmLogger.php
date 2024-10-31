<?php

namespace Payever\Sdk\Logger;

use Exception;
use Payever\Sdk\Core\Apm\ApmApiClient;
use Payever\Sdk\Core\ClientConfiguration;
use Psr\Log\LoggerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Class ApmLogger
 */
class ApmLogger extends AbstractLogger
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ApmApiClient
     */
    private $apmApiClient;

    public function __construct(LoggerInterface $logger, ClientConfiguration $clientConfiguration)
    {
        $this->logger = $logger;
        $this->apmApiClient = new ApmApiClient($clientConfiguration);
    }

    /**
     * @param $level
     * @param $message
     * @param array $context
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        $this->logger->log($level, $message, $context);

        if ($level !== LogLevel::CRITICAL && $level !== LogLevel::ERROR) {
            return;
        }

        if (count($context) > 0) {
            $message .= ' ' . json_encode($context);
        }

        try {
            $this->apmApiClient->sendLog($message, $level);
        } catch (Exception $exception) {
            $this->logger->critical($exception->getMessage());
        }
    }
}
