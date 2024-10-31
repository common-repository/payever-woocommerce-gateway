<?php

namespace Payever\Tests\Unit\Payever\Products\Http\MessageEntity;

use Payever\Sdk\Products\Http\MessageEntity\ProductCategoryEntity;
use Payever\Tests\Unit\Payever\Core\Http\AbstractMessageEntityTest;

class ProductCategoryEntityTest extends AbstractMessageEntityTest
{
    protected static $scheme = array(
        'id' => 'b51e4bf9-ca97-4abf-a546-cce508fbff00',
        'businessUuid' => 'b51e4bf9-ca97-4abf-a546-cce508fbff00',
        'title' => 'stub_title',
        'slug' => 'stub_slug',
    );

    public function getEntity()
    {
        return new ProductCategoryEntity();
    }
}
