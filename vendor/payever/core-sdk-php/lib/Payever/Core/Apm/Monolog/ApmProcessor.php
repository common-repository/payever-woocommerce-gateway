<?php

namespace Payever\Sdk\Core\Apm\Logger\Monolog;

use Monolog\Processor\ProcessorInterface;
use Monolog\LogRecord;
use Payever\Sdk\Core\Apm\ApmApiClient;
use Payever\Sdk\Core\Base\ClientConfigurationInterface;
use Payever\Sdk\Core\ClientConfiguration;
use Psr\Log\LogLevel;

class ApmProcessor implements ProcessorInterface
{
    /**
     * @var ApmApiClient
     */
    private $apmApiClient;

    /**
     * @var ClientConfiguration
     */
    private $clientConfiguration;

    public function __construct(ClientConfigurationInterface $clientConfiguration)
    {
        $this->clientConfiguration = $clientConfiguration;
    }

    /**
     * Get APM Api Client.
     *
     * @return ApmApiClient
     * @throws \Exception
     */
    private function getApmApiClient()
    {
        if (!$this->apmApiClient) {
            $this->apmApiClient = new ApmApiClient($this->clientConfiguration);
        }

        return $this->apmApiClient;
    }

    /**
     * Apm processor callback.
     *
     * @param array|LogRecord $record
     * @return array The processed record
     */
    public function __invoke(array $record)
    {
        $message = $record['message'];
        $logLevel = strtolower($record['level_name']);
        if ($logLevel !== LogLevel::CRITICAL && $logLevel !== LogLevel::ERROR) {
            return $record;
        }

        if ($record['context']) {
            $message .= ' ' . json_encode($record['context']);
        }

        try {
            $this->getApmApiClient()->sendLog($message, $logLevel);
        } catch (\Exception $exception) {
            // Silence is golden
        }

        return $record;
    }
}
