<?php

namespace Payever\Tests\Unit\Core\Http\RequestEntity;

use Payever\Sdk\Core\Authorization\OauthToken;
use Payever\Sdk\Core\Base\MessageEntity;
use Payever\Sdk\Core\Http\RequestEntity\AuthenticationRequest;
use Payever\Tests\Unit\Core\Http\AbstractRequestEntityTest;

/**
 * Class AuthenticationRequestTest
 *
 * @see \Payever\Sdk\Core\Http\RequestEntity\AuthenticationRequest
 *
 * @package Payever\Tests\Unit\Core\Http\RequestEntity
 */
class AuthenticationRequestTest extends AbstractRequestEntityTest
{
    protected static $scheme = array(
        'scope' => OauthToken::SCOPE_CREATE_PAYMENT,
        'client_id' => 'stub_id',
        'client_secret' => 'stub_secret',
        'grant_type' => OauthToken::GRAND_TYPE_OBTAIN_TOKEN
    );

    public function getEntity()
    {
        return new AuthenticationRequest();
    }

    protected function assertRequestEntityInvalid(MessageEntity $entity)
    {
        /** @var AuthenticationRequest $entity */
        $innerEntity = clone $entity;
        $innerEntity->setGrantType(OauthToken::GRAND_TYPE_REFRESH_TOKEN);
        $this->assertFalse($innerEntity->isValid());

        $innerEntity = clone $entity;
        $innerEntity->setScope('broken_scope');
        $this->assertFalse($innerEntity->isValid());

        $innerEntity = clone $entity;
        $innerEntity->setGrantType('broken_grant_type');
        $this->assertFalse($innerEntity->isValid());

        parent::assertRequestEntityInvalid($entity);
    }
}
