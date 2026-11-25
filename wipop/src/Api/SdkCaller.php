<?php
/**
 * 2007-2026 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2026 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Api;

use PrestaShop\Module\Wipop\Exception\ApiCallException;
use PrestaShop\Module\Wipop\Log\Logger;
use Wipop\Exception\WipopException;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class SdkCaller
{
    public function __construct(private Logger $logger)
    {
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     *
     * @throws ApiCallException
     */
    public function call(string $operation, callable $callback)
    {
        try {
            return $callback();
        } catch (WipopException $exception) {
            $context = ['operation' => $operation];
            $apiCode = $exception->getApiCode();

            if ($apiCode !== null && $apiCode !== '') {
                $context['api_code'] = $apiCode;
            }

            $this->logger->error(
                sprintf('Wipop API error during %s: %s', $operation, $exception->getMessage()),
                $context
            );

            $message = sprintf('Unable to complete the Wipop %s: %s', $operation, $exception->getMessage());

            throw new ApiCallException($message, 0, $exception);
        } catch (\Throwable $throwable) {
            $this->logger->error(
                sprintf('Unexpected Wipop error during %s: %s', $operation, $throwable->getMessage()),
                ['operation' => $operation],
            );

            throw new ApiCallException('Unexpected error while communicating with Wipop.', 0, $throwable);
        }
    }
}
