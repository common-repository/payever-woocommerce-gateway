<?php

/**
 * PHP version 5.4 and 8
 *
 * @category  Helper
 * @package   Payever\Core
 * @author    payever GmbH <service@payever.de>
 * @copyright 2017-2021 payever GmbH
 * @license   MIT <https://opensource.org/licenses/MIT>
 * @link      https://docs.payever.org/shopsystems/api/getting-started
 */

namespace Payever\Sdk\Core\Helper;

/**
 * This class represents helper functions for strings
 * @SuppressWarnings(PHPMD.MissingImport)
 */
class StringHelper
{
    /**
     * Converts field names for setters and getters
     *
     * @param string $name
     *
     * @return string
     */
    public static function underscore($name)
    {
        return strtolower(preg_replace('/(.)([A-Z0-9])/', "$1_$2", $name));
    }

    /**
     * Will capitalize word's first letters and convert separators if needed
     *
     * @param string $name
     * @param bool   $firstLetter
     *
     * @return string
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public static function camelize($name, $firstLetter = false)
    {
        $string = str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));

        if (!$firstLetter) {
            $string = lcfirst($string);
        }

        return $string;
    }

    /**
     * Returns decoded JSON
     *
     * @see json_decode()
     * @param array|object|\stdClass|string $object
     * @param bool|null $associative
     * @param int $depth [optional]
     * @param int $flags [optional]
     *
     * @return bool|mixed
     * @throws \UnexpectedValueException
     */
    public static function jsonDecode($object, $associative = null, $depth = 512, $flags = 0)
    {
        if (!is_string($object)) {
            return $object;
        }

        $result = json_decode($object, $associative, $depth, $flags);
        $lastError = json_last_error();
        if ($lastError !== JSON_ERROR_NONE) {
            $errorMessage = function_exists('json_last_error_msg') ? json_last_error_msg() : $lastError;

            throw new \UnexpectedValueException(
                'JSON Decode Error: ' . $errorMessage,
                $lastError
            );
        }

        return $result;
    }
}
