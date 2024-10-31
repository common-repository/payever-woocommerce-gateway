<?php

namespace Payever\Tests\Unit\Payever\Payments\Http\RequestEntity;

use Payever\Sdk\Payments\Http\RequestEntity\CompanySearchCreditRequest;
use Payever\Tests\Unit\Payever\Core\Http\AbstractRequestEntityTest;
use Payever\Tests\Unit\Payever\Payments\Http\RequestEntity\CompanySearchCredit\CompanyEntityTest;

/**
 * Class CompanySearchCreditRequestTest
 *
 * @see \Payever\Sdk\Payments\Http\RequestEntity\CompanySearchCreditRequest
 *
 * @package Payever\Tests\Unit\Payever\Payments\Http\RequestEntity
 */
class CompanySearchCreditRequestTest extends AbstractRequestEntityTest
{
    public static function getScheme()
    {
        return array(
            'company' => CompanyEntityTest::getScheme(),
        );
    }

    public function getEntity()
    {
        return new CompanySearchCreditRequest();
    }
}
