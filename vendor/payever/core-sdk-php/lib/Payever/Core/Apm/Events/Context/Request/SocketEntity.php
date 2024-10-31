<?php

/**
 * PHP version 5.4 and 8.1
 *
 * @category  Apm Agent
 * @package   Payever\Core
 * @author    payever GmbH <service@payever.de>
 * @copyright 2017-2023 payever GmbH
 * @license   MIT <https://opensource.org/licenses/MIT>
 * @link      https://docs.payever.org/shopsystems/api/getting-started
 */

namespace Payever\Sdk\Core\Apm\Events\Context\Request;

use Payever\Sdk\Core\Http\ApmRequestEntity;

/**
 * Class SocketEntity
 * @todo Change properties to be the camel case style
 * @method bool getEncrypted()
 * @method self setEncrypted(bool $encrypted)
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 */
class SocketEntity extends ApmRequestEntity
{
    /** @var bool $encrypted */
    protected $encrypted;

    /** @var string $remote_address */
    protected $remote_address;

    /**
     * @param array $data
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function __construct($data = [])
    {
        if (!isset($data['remote_address'])) {
            $data['remote_address'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        }

        if (!isset($data['encrypted'])) {
            $data['encrypted'] = isset($_SERVER['HTTPS']);
        }

        parent::__construct($data);
    }

    /**
     * @param string $address
     * @return $this
     */
    public function setRemoteAddress($address)
    {
        $this->remote_address = $address;

        return $this;
    }

    /**
     * @return string
     */
    public function getRemoteAddress()
    {
        return $this->remote_address;
    }
}
