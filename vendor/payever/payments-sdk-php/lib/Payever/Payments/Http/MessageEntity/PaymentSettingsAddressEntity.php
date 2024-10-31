<?php

/**
 * PHP version 5.4 and 8
 *
 * @category  MessageEntity
 * @package   Payever\Payments
 * @author    payever GmbH <service@payever.de>
 * @copyright 2017-2023 payever GmbH
 * @license   MIT <https://opensource.org/licenses/MIT>
 * @link      https://docs.payever.org/shopsystems/api/getting-started
 */

namespace Payever\Sdk\Payments\Http\MessageEntity;

use Payever\Sdk\Core\Base\MessageEntity;

/**
 * This class represents Payment Settings Company Address Entity
 *
 * @method string getCity()
 * @method string getCountry()
 * @method string getStreet()
 * @method string getZipCode()
 * @method self   setCity(string $city)
 * @method self   setCountry(string $country)
 * @method self   setStreet(string $street)
 * @method self   setZipCode(string $zipCode)
 */
class PaymentSettingsAddressEntity extends MessageEntity
{
    /** @var string $city */
    protected $city;

    /** @var string $country */
    protected $country;

    /** @var string $street */
    protected $street;

    /** @var string $zipCode */
    protected $zipCode;
}
