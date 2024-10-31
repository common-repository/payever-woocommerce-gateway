<?php

/**
 * PHP version 5.4 and 8
 *
 * @category  ResponseEntity
 * @package   Payever\Payments
 * @author    payever GmbH <service@payever.de>
 * @copyright 2017-2023 payever GmbH
 * @license   MIT <https://opensource.org/licenses/MIT>
 * @link      https://docs.payever.org/shopsystems/api/getting-started
 */

namespace Payever\Sdk\Payments\Http\ResponseEntity;

use Payever\Sdk\Core\Helper\StringHelper;
use Payever\Sdk\Core\Http\ResponseEntity;
use Payever\Sdk\Payments\Http\MessageEntity\PaymentSettingsAddressEntity;
use Payever\Sdk\Payments\Http\MessageEntity\PaymentSettingsLanguagesEntity;

/**
 * This class represents Payment Settings ResponseInterface Entity
 *
 * @method string  getName()
 * @method self    setName(string $name)
 * @method string  getType()
 * @method self    setType(string $type)
 * @method boolean getB2bSearch()
 * @method self    setB2bSearch(boolean $b2bSearch)
 * @method PaymentSettingsLanguagesEntity[] getLanguages()
 * @method boolean getTestingMode()
 * @method self    setTestingMode(boolean $testingMode)
 * @method boolean getCompanyAddress()
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class PaymentSettingsResponse extends ResponseEntity
{
    /** @var string $name */
    protected $name;

    /** @var string $type */
    protected $type;

    /** @var boolean $b2bSearch */
    protected $b2bSearch;

    /** @var array $languages */
    protected $languages;

    /** @var boolean $testingMode */
    protected $testingMode;

    /** @var PaymentSettingsAddressEntity $companyAddress */
    protected $companyAddress;

    /**
     * Sets Company address
     *
     * @param PaymentSettingsAddressEntity $companyAddress
     * @return self
     */
    public function setCompanyAddress($companyAddress)
    {
        $this->companyAddress = new PaymentSettingsAddressEntity($companyAddress);
    }

    /**
     * Sets Languages
     *
     * @param array|string $languages
     * @return self
     * @throws \Exception
     */
    public function setLanguages($languages)
    {
        if (is_string($languages)) {
            $languages = StringHelper::jsonDecode($languages);
        }

        if ($languages && is_array($languages)) {
            foreach ($languages as $language) {
                $this->languages[] = new PaymentSettingsLanguagesEntity($language);
            }
        }

        return $this;
    }
}
