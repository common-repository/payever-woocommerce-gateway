<?php

namespace Payever\Tests\Unit\Core\Http;

use Payever\Sdk\Core\Http\MessageEntity\CallEntity;
use Payever\Sdk\Core\Http\MessageEntity\ResultEntity;
use Payever\Sdk\Core\Http\ResponseEntity;
use PHPUnit\Framework\TestCase;

/**
 * Class ResponseEntityTest
 *
 * @see \Payever\Sdk\Core\Http\ResponseEntity
 *
 * @package Payever\Sdk\Core\Http
 */
class ResponseEntityTest extends TestCase
{
    /**
     * @see \Payever\Sdk\Core\Http\ResponseEntity::setCall()
     * @see \Payever\Sdk\Core\Http\ResponseEntity::setResult()
     * @see \Payever\Sdk\Core\Http\ResponseEntity::isValid()
     * @see \Payever\Sdk\Core\Http\ResponseEntity::isSuccessful()
     *
     * @throws \Exception
     */
    public function testValidation()
    {
        $call = [
            'created_at' => time(),
            'status' => 'success',
            'id' => bin2hex(random_bytes(16)),
        ];
        $result = [];

        $responseEntity = new ResponseEntity();

        $responseEntity->setCall($call);
        $responseEntity->setResult($result);

        $this->assertInstanceOf(CallEntity::class, $responseEntity->getCall());
        $this->assertInstanceOf(ResultEntity::class, $responseEntity->getResult());
        $this->assertTrue($responseEntity->isSuccessful());
        $this->assertFalse($responseEntity->isFailed());
        $this->assertTrue($responseEntity->isValid());
    }

    /**
     * @see \Payever\Sdk\Core\Http\ResponseEntity::setError()
     * @see \Payever\Sdk\Core\Http\ResponseEntity::setErrorDescription()
     * @see \Payever\Sdk\Core\Http\ResponseEntity::isFailed()
     */
    public function testError()
    {
        $error = 'ERROR';
        $errorDescr = 'ERROR_HAPPENED';

        $responseEntity = new ResponseEntity();
        $responseEntity->setError($error);
        $responseEntity->setErrorDescription($errorDescr);

        $this->assertTrue($responseEntity->isFailed());
        $this->assertFalse($responseEntity->isSuccessful());
        $this->assertEquals($error, $responseEntity->getError());
        $this->assertEquals($errorDescr, $responseEntity->getErrorDescription());

        $errors = ['error1', 'error2'];
        $responseEntity->setError($errors);
        $responseEntity->setErrorDescription($errors);
        $this->assertEquals(json_encode($errors), $responseEntity->getError());
        $this->assertEquals(json_encode($errors), $responseEntity->getErrorDescription());
    }
}
