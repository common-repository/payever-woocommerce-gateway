<?php

/**
 * PHP version 5.4 and 8
 *
 * @category  RequestEntity
 * @package   Payever\Payments
 * @author    payever GmbH <service@payever.de>
 * @copyright 2017-2023 payever GmbH
 * @license   MIT <https://opensource.org/licenses/MIT>
 * @link      https://docs.payever.org/shopsystems/api/getting-started
 */

namespace Payever\Sdk\Payments\Http\RequestEntity;

use Payever\Sdk\Core\Http\RequestEntity;
use Payever\Sdk\Payments\Http\RequestEntity\CompanySearchCredit\CompanyEntity;

/**
 * This class represents Company Search Credit Entity
 *
 * @method CompanyEntity getCompany()
 */
class CompanySearchCreditRequest extends RequestEntity
{
    /**
     * @var CompanyEntity
     */
    protected $company;

    /**
     * {@inheritdoc}
     */
    public function getRequired()
    {
        return [
            'company'
        ];
    }

    /**
     * Set Company.
     *
     * @param CompanyEntity|array|string $company
     *
     * @return $this
     */
    public function setCompany($company)
    {
        if (!$company) {
            return $this;
        }

        if (is_string($company)) {
            $company = json_decode($company);
        }

        if (is_array($company)) {
            $this->company = new CompanyEntity($company);

            return $this;
        }

        if ($company instanceof CompanyEntity) {
            $this->company = $company;

            return $this;
        }

        return $this;
    }
}
