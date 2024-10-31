<?php

namespace Payever\Tests\Unit\Payever\Payments\Http\RequestEntity\CompanySearchCredit;

use Payever\Sdk\Payments\Http\RequestEntity\CompanySearchCredit\CompanyEntity;
use Payever\Tests\Unit\Payever\Core\Http\AbstractMessageEntityTest;

/**
 * Class CompanyEntityTest
 *
 * @see \Payever\Sdk\Payments\Http\RequestEntity\CompanySearchCredit\CompanyEntity
 */
class CompanyEntityTest extends AbstractMessageEntityTest
{
    protected static $scheme = array(
        'external_id' => '81981372',
    );

    public function getEntity()
    {
        return new CompanyEntity();
    }
}
