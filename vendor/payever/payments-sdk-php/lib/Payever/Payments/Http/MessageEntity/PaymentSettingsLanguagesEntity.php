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
 * This class represents Payment Settings Languages Entity
 *
 * @method boolean getActive()
 * @method boolean getIsDefault()
 * @method string  getCode()
 * @method string  getName()
 * @method boolean getIsToggleButton()
 * @method boolean getIsHovered()
 * @method self    setActive(boolean $active)
 * @method self    setIsDefault(boolean $isDefault)
 * @method self    setCode(string $code)
 * @method self    setName(string $name)
 * @method self    setIsToggleButton(boolean $isToggleButton)
 * @method self    setIsHovered(boolean $isHovered)
 */
class PaymentSettingsLanguagesEntity extends MessageEntity
{
    /** @var boolean $active */
    protected $active;

    /** @var boolean $isDefault */
    protected $isDefault;

    /** @var string $code */
    protected $code;

    /** @var string $name */
    protected $name;

    /** @var boolean $isToggleButton */
    protected $isToggleButton;

    /** @var boolean $isHovered */
    protected $isHovered;
}
