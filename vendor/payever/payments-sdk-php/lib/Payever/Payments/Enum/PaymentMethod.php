<?php

/**
 * PHP version 5.4 and 8
 *
 * @category  Enum
 * @package   Payever\Payments
 * @author    payever GmbH <service@payever.de>
 * @author    Hennadii.Shymanskyi <gendosua@gmail.com>
 * @copyright 2017-2023 payever GmbH
 * @license   MIT <https://opensource.org/licenses/MIT>
 * @link      https://docs.payever.org/shopsystems/api/getting-started
 */

namespace Payever\Sdk\Payments\Enum;

use Payever\Sdk\Core\Base\EnumerableConstants;

/**
 * List of available for external integration payever payment methods
 */
class PaymentMethod extends EnumerableConstants
{
    const METHOD_SANTANDER_DE_FACTORING = 'santander_factoring_de';
    const METHOD_SANTANDER_DE_INVOICE = 'santander_invoice_de';
    const METHOD_SANTANDER_NO_INVOICE = 'santander_invoice_no';
    const METHOD_SANTANDER_DE_INSTALLMENT = 'santander_installment';
    const METHOD_SANTANDER_DE_CCP_INSTALLMENT = 'santander_ccp_installment';
    const METHOD_SANTANDER_NO_INSTALLMENT = 'santander_installment_no';
    const METHOD_SANTANDER_DK_INSTALLMENT = 'santander_installment_dk';
    const METHOD_SANTANDER_SE_INSTALLMENT = 'santander_installment_se';
    const METHOD_SANTANDER_FI_INSTALLMENT = 'santander_installment_fi';
    const METHOD_SANTANDER_NL_INSTALLMENT = 'santander_installment_nl';
    const METHOD_INSTANT_PAYMENT = 'instant_payment';

    const METHOD_SOFORT = 'sofort';
    const METHOD_PAYMILL_CREDIT_CARD = 'paymill_creditcard';
    const METHOD_PAYMILL_DIRECT_DEBIT = 'paymill_directdebit';
    const METHOD_PAYPAL = 'paypal';
    const METHOD_STRIPE_CREDIT_CARD = 'stripe';
    const METHOD_STRIPE_DIRECT_DEBIT = 'stripe_directdebit';
    const METHOD_PAYEX_FAKTURA = 'payex_faktura';
    const METHOD_PAYEX_CREDIT_CARD = 'payex_creditcard';
    const METHOD_OPENBANK = 'zinia_bnpl';
    const METHOD_ZINIA_BNPL = 'zinia_bnpl';
    const METHOD_ZINIA_BNPL_DE = 'zinia_bnpl_de';
    const METHOD_ZINIA_SLICE_THREE = 'zinia_slice_three';
    const METHOD_IVY = 'ivy';
    const METHOD_APPLE_PAY = 'apple_pay';
    const METHOD_GOOGLE_PAY = 'google_pay';
    const METHOD_IDEAL = 'ideal';
    const METHOD_ALLIANZ_TRADE_B2B_BNPL = 'allianz_trade_b2b_bnpl';

    const MOBILE_REGEXP = '/Mobile|iP(hone|od|ad)|Android|BlackBerry|IEMobile|Kindle|NetFront|Silk-Accelerated|(hpw|web)OS|Fennec|Minimo|Opera M(obi|ini)|Blazer|Dolfin|Dolphin|Skyfire|Zune/i'; //phpcs:ignore
    const IOS_REGEXP =  '/(((iPhone([\d\W]+)|iPhone|iPad)(;|\s\d+;|\s\w+;|\s\d+\w+;)?(\s+)?(U;)?)(\s+)?(CPU|iOS)([^\);]+)?)/i'; //phpcs:ignore
    const SAFARI_REGEXP = '/safari/i';
    const ANDROID_REGEXP = '/android/i';
    const CHROME_REGEXP = '/chrome/i';

    /**
     * Whether payment method must be hidden when shipping address differs from billing one
     *
     * @param string $method
     *
     * @return bool
     */
    public static function shouldHideOnDifferentAddress($method)
    {
        return in_array($method, static::getShouldHideOnDifferentAddressMethods());
    }

    /**
     * Whether payment method must be hidden after first unsuccessful payment attempt
     *
     * @param string $method
     *
     * @return bool
     */
    public static function shouldHideOnReject($method)
    {
        return in_array($method, static::getShouldHideOnRejectMethods());
    }

    /**
     * Checks if payment method should be hidden for current device
     *
     * @param string $method
     * @param null/srting $userAgent
     *
     * @return bool
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function shouldHideOnCurrentDevice($method, $userAgent = null)
    {
        if (!in_array($method, static::getShouldHideOnCurrentDeviceMethods())) {
            return false;
        }

        if (!$userAgent) {
            $userAgent = isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : '';
        }

        if ($method === static::METHOD_APPLE_PAY) {
            return static::isApplePayHidden($userAgent);
        }

        if ($method === static::METHOD_GOOGLE_PAY) {
            return static::isGooglePayHidden($userAgent);
        }

        return false;
    }

    /**
     * Apple Pay should be hidden for desktop and visible only for ios mobile (safari)
     *
     * @param string $userAgent
     *
     * @return bool
     */
    public static function isApplePayHidden($userAgent)
    {
        // desktop
        if (!static::isMobile($userAgent)) {
            return true;
        }

        // mobile
        if (!static::isIOS($userAgent) || !static::isSafariBrowser($userAgent)) {
            return true;
        }

        return false;
    }

    /**
     * Google Pay should be visible only for android mobile (chrome) and desktop chrome.
     *
     * @param string $userAgent
     *
     * @return bool
     */
    public static function isGooglePayHidden($userAgent)
    {
        // desktop
        if (!static::isMobile($userAgent)) {
            return true;
        }

        // mobile
        if (!static::isAndroidOS($userAgent) || !static::isChromeBrowser($userAgent)) {
            return true;
        }

        return false;
    }

    /**
     * Returns the payment methods what should be hidden if billing and shipping addresses are different
     *
     * @return array
     */
    public static function getShouldHideOnDifferentAddressMethods()
    {
        return [
            static::METHOD_SANTANDER_DE_CCP_INSTALLMENT,
            static::METHOD_SANTANDER_DE_INSTALLMENT,
            static::METHOD_SANTANDER_DE_FACTORING,
            static::METHOD_SANTANDER_DE_INVOICE,
            static::METHOD_PAYEX_FAKTURA,
            static::METHOD_ZINIA_BNPL,
            static::METHOD_ZINIA_BNPL_DE,
            static::METHOD_ZINIA_SLICE_THREE
        ];
    }

    /**
     * Returns payment methods what should be hidden after rejected payment
     *
     * @return array
     */
    public static function getShouldHideOnRejectMethods()
    {
        return [
            static::METHOD_SANTANDER_DE_FACTORING,
            static::METHOD_SANTANDER_DE_INVOICE,
        ];
    }

    /**
     * Returns payment methods which should be checked whether it is necessary to hide them for current device
     *
     * @return array
     */
    private static function getShouldHideOnCurrentDeviceMethods()
    {
        return [
            static::METHOD_APPLE_PAY,
            static::METHOD_GOOGLE_PAY,
        ];
    }

    /**
     * Checks if mobile device is used by user agent
     *
     * @param $userAgent
     * @return bool
     */
    private static function isMobile($userAgent)
    {
        return preg_match(static::MOBILE_REGEXP, $userAgent);
    }

    /**
     * Checks if iOS is used by user agent
     *
     * @param $userAgent
     * @return bool
     */
    private static function isIOS($userAgent)
    {
        return preg_match(static::IOS_REGEXP, $userAgent);
    }

    /**
     * Checks if Android OS is used by user agent
     *
     * @param $userAgent
     * @return bool
     */
    private static function isAndroidOS($userAgent)
    {
        return preg_match(static::ANDROID_REGEXP, $userAgent);
    }

    /**
     * Checks if Safari Browser is used by user agent
     *
     * @param $userAgent
     * @return bool
     */
    private static function isSafariBrowser($userAgent)
    {
        return preg_match(static::SAFARI_REGEXP, $userAgent);
    }

    /**
     * Checks if Chrome Browser is used by user agent
     *
     * @param $userAgent
     * @return bool
     */
    private static function isChromeBrowser($userAgent)
    {
        return preg_match(static::CHROME_REGEXP, $userAgent);
    }
}
