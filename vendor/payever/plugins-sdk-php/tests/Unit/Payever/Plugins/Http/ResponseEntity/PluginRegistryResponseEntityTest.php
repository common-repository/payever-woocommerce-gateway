<?php

namespace Payever\Tests\Unit\Payever\Plugins\Http\ResponseEntity;

use Payever\Sdk\Plugins\Http\ResponseEntity\PluginRegistryResponseEntity;
use Payever\Tests\Unit\Payever\Core\Http\AbstractMessageEntityTest;
use Payever\Tests\Unit\Payever\Plugins\Http\RequestEntity\PluginRegistryRequestEntityTest;

class PluginRegistryResponseEntityTest extends AbstractMessageEntityTest
{
    public static function getScheme()
    {
        static::$scheme = PluginRegistryRequestEntityTest::getScheme();
        static::$scheme['_id'] = 'stub-id';

        return parent::getScheme();
    }

    public function getEntity()
    {
        return new PluginRegistryResponseEntity();
    }
}
