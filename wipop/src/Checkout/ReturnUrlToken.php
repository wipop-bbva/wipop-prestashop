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

namespace PrestaShop\Module\Wipop\Checkout;

use RuntimeException;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ReturnUrlToken
{
    public function __construct(private string $secret)
    {
    }

    public static function fromPrestaShopCookieKey(): self
    {
        $cookieKey = defined('_COOKIE_KEY_') ? constant('_COOKIE_KEY_') : null;
        if (!is_string($cookieKey) || $cookieKey === '') {
            throw new RuntimeException('PrestaShop cookie key is required to sign Wipop return URLs.');
        }

        return new self($cookieKey);
    }

    public function create(int $attemptId): string
    {
        return hash_hmac('sha256', (string) $attemptId, $this->secret);
    }

    public function matches(int $attemptId, string $token): bool
    {
        return $attemptId > 0 && hash_equals($this->create($attemptId), $token);
    }
}
