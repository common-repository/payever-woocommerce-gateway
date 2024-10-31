<?php

namespace Payever\Tests\Unit\Core\Http\ResponseEntity;

use Payever\Sdk\Core\Http\ResponseEntity\GetCurrenciesResponse;
use Payever\Tests\Unit\Core\Http\AbstractResponseEntityTest;
use Payever\Tests\Unit\Core\Http\MessageEntity\GetCurrenciesResultEntityTest;

/**
 * Class GetCurrenciesResponseTest
 *
 * @see \Payever\Sdk\Core\Http\ResponseEntity\GetCurrenciesResponse
 *
 * @package Payever\Tests\Unit\Core\Http\ResponseEntity
 */
class GetCurrenciesResponseTest extends AbstractResponseEntityTest
{
    public static function getScheme()
    {
        $childScheme = GetCurrenciesResultEntityTest::getScheme();

        return array(
            'result' => array(
                $childScheme['symbol'] => $childScheme,
            ),
        );
    }

    public function getEntity()
    {
        return new GetCurrenciesResponse(static::getScheme());
    }
}
