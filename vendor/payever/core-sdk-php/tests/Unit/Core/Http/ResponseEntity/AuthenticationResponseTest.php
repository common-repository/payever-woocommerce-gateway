<?php

namespace Payever\Tests\Unit\Core\Http\ResponseEntity;

use Payever\Sdk\Core\Authorization\OauthToken;
use Payever\Sdk\Core\Http\ResponseEntity\AuthenticationResponse;
use Payever\Tests\Unit\Core\Http\AbstractResponseEntityTest;

/**
 * Class AuthenticationResponseTest
 *
 * @see \Payever\Sdk\Core\Http\ResponseEntity\AuthenticationResponse
 *
 * @package Payever\Tests\Unit\Core\Http\ResponseEntity
 */
class AuthenticationResponseTest extends AbstractResponseEntityTest
{
    protected static $scheme = array(
        'access_token' => 'stub_access_token',
        'refresh_token' => 'stub_refresh_token',
        'scope' => OauthToken::SCOPE_PAYMENT_ACTIONS,
        'expires_in' => 3600,
    );

    public function getEntity()
    {
        return new AuthenticationResponse();
    }
}
