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

use PrestaShop\Module\Wipop\Card\CardCofIntent;
use PrestaShop\Module\Wipop\Configuration\CaptureMode;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PaymentAttempt
{
    public function __construct(
        public readonly int $id,
        public readonly int $cartId,
        public readonly ?int $orderId,
        public readonly PaymentAttemptStatus $status,
        public readonly float $amount,
        public readonly string $currencyIso,
        public readonly ?string $paymentMethod = null,
        public readonly CaptureMode $captureMode = CaptureMode::IMMEDIATE,
        public readonly ?string $wipopTransactionId = null,
        public readonly int $shopId = 0,
        public readonly bool $isLive = false,
        public readonly int $terminalId = 0,
        public readonly CardCofIntent $cofIntent = CardCofIntent::NONE,
        public readonly ?int $storedCardId = null,
        public readonly int $customerId = 0,
        public readonly string $merchantId = '',
    ) {
    }
}
