<?php

namespace Payever\Tests\Unit\Payever\Plugins\Http\ResponseEntity;

use Payever\Sdk\Core\Base\MessageEntity;
use Payever\Sdk\Plugins\Http\ResponseEntity\CommandsResponseEntity;
use Payever\Tests\Unit\Payever\Core\Http\AbstractMessageEntityTest;
use Payever\Tests\Unit\Payever\Plugins\Http\MessageEntity\PluginCommandEntityTest;

class CommandsResponseEntityTest extends AbstractMessageEntityTest
{
    public static function getScheme()
    {
        static::$scheme = [
            PluginCommandEntityTest::getScheme()
        ];

        return parent::getScheme();
    }

    public function getEntity()
    {
        return new CommandsResponseEntity();
    }

    protected function compareFields(MessageEntity $entity, array $fields)
    {
        if ($entity instanceof CommandsResponseEntity) {
            $fields = [
                'commands' => static::$scheme,
            ];
        }

        return parent::compareFields($entity, $fields);
    }
}
