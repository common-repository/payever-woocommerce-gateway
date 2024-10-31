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

use Payever\Sdk\Core\Http\RequestEntity;

/**
 * This class represents helper functions for data
 * @SuppressWarnings(PHPMD.MissingImport)
 */
class DataHelper
{
    /**
     * Make Entity Instance if possible.
     *
     * @param mixed $data
     * @param string $className
     * @return RequestEntity|object|null
     */
    public static function getEntityInstance($data, $className)
    {
        // Decode string as JSON
        if (is_string($data)) {
            try {
                $data = StringHelper::jsonDecode($data, true);
            } catch (\UnexpectedValueException $exception) {
                return null;
            }
        }

        if (!$data) {
            return null;
        }

        if (is_object($data) && is_a($data, $className)) {
            /** @var RequestEntity $data */
            return $data;
        }

        if (!is_object($data) && !is_array($data)) {
            return null;
        }

        /** @var RequestEntity $data */
        $data = new $className($data);

        return $data;
    }
}
