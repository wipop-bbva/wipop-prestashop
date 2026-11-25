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

namespace PrestaShop\Module\Wipop\Payment;

if (!defined('_PS_VERSION_')) {
    exit;
}

enum PaymentAttemptStatus: string
{
    case INITIALIZED = 'initialized';
    case CHECKOUT_PENDING = 'checkout_pending';
    case AUTHORIZED = 'authorized';
    case PAID = 'paid';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function syncWithWebhookStatus(self $webhookStatus): self
    {
        if (in_array($this, [self::PAID, self::FAILED, self::CANCELLED, self::REFUNDED], true)) {
            return $this;
        }

        if ($this === self::AUTHORIZED && in_array($webhookStatus, [self::CHECKOUT_PENDING, self::FAILED], true)) {
            return self::AUTHORIZED;
        }

        return $webhookStatus;
    }
}
