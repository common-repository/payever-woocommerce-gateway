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

use Payever\Sdk\Core\Http\ResponseEntity;

/**
 * This class represents CompanySearch Credit ResponseEntity
 * @method string getBuyerId()
 * @method string getMaximum()
 * @method string getMaxInvoiceAmount()
 * @method string getCurrency()
 * @method self setBuyerId(string $value)
 * @method self setMaximum(string $value)
 * @method self setMaxInvoiceAmount(string $value)
 * @method self setCurrency(string $value)
 */
class CompanySearchCreditResponse extends ResponseEntity
{
    /**
     * @var string
     */
    protected $buyerId;

    /**
     * @var string
     */
    protected $maximum;

    /**
     * @var string
     */
    protected $maxInvoiceAmount;

    /**
     * @var string
     */
    protected $currency;

    /**
     * {@inheritdoc}
     */
    public function getRequired()
    {
        return [
            'buyerId',
            'maximum',
            'maxInvoiceAmount',
            'currency',
        ];
    }
}
