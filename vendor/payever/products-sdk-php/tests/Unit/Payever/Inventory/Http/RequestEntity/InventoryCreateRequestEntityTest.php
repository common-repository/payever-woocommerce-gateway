<?php

namespace Payever\Tests\Unit\Payever\Inventory\Http\RequestEntity;

use Payever\Sdk\Inventory\Http\RequestEntity\InventoryCreateRequestEntity;
use Payever\Tests\Unit\Payever\Core\Http\AbstractMessageEntityTest;

class InventoryCreateRequestEntityTest extends AbstractMessageEntityTest
{
    protected static $scheme = array(
        'sku' => 'stub_sku',
        'stock' => 12,
        'externalId' => 'stub_id',
    );

    public function getEntity()
    {
        return new InventoryCreateRequestEntity();
    }
}
