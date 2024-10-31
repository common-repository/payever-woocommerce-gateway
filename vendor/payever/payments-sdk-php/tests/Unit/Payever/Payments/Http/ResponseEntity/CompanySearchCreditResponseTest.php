<?php

namespace Payever\Tests\Unit\Payever\Payments\Http\ResponseEntity;

use Payever\Sdk\Payments\Http\ResponseEntity\CompanySearchCreditResponse;
use Payever\Tests\Unit\Payever\Core\Http\AbstractResponseEntityTest;

/**
 * Class CompanySearchCreditResponseTest
 *
 * @see \Payever\Sdk\Payments\Http\ResponseEntity\CompanySearchCreditResponse
 *
 * @package Payever\Tests\Unit\Payever\Payments\Http\ResponseEntity
 */
class CompanySearchCreditResponseTest extends AbstractResponseEntityTest
{
    protected static $scheme = array(
        'buyerId' => '81981372',
        'maximum' => 100000,
        'maxInvoiceAmount' => 30000,
        'currency' => 'EUR',
    );

    public static function getScheme()
    {
        return static::$scheme;
    }

    public function getEntity()
    {
        return new CompanySearchCreditResponse();
    }
}
