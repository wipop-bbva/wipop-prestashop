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

namespace PrestaShop\Module\Wipop\Configuration;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ConfigKeys
{
    public const SETTINGS = 'WIPOP_SETTINGS';
    public const AVAILABLE_GATEWAYS_CACHE = 'WIPOP_AVAILABLE_GATEWAYS_CACHE';
    public const AUTHORIZED_ORDER_STATE_ID = 'WIPOP_AUTHORIZED_ORDER_STATE_ID';
    public const RECURRING_AUTO_RUNNER_LAST_ATTEMPT_AT = 'WIPOP_RECURRING_AUTO_RUNNER_LAST_ATTEMPT_AT';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::SETTINGS,
            self::AVAILABLE_GATEWAYS_CACHE,
            self::AUTHORIZED_ORDER_STATE_ID,
            self::RECURRING_AUTO_RUNNER_LAST_ATTEMPT_AT,
        ];
    }
}
