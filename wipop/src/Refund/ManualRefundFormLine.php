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

namespace PrestaShop\Module\Wipop\Refund;

use PrestaShop\Module\Wipop\Payment\PaymentAmount;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ManualRefundFormLine
{
    public function __construct(
        public readonly int $orderDetailId,
        public readonly string $name,
        public readonly string $reference,
        public readonly int $quantityRefundable,
        public readonly float $unitAmount,
        public readonly float $unitAmountTaxIncluded,
        public readonly float $amountRefundable,
        public readonly string $amountRefundableFormatted,
    ) {
    }

    public function amountRefundableForQuantityCents(int $quantity): int
    {
        $quantityAmountCents = PaymentAmount::toCents($this->unitAmount * $quantity);

        return min($quantityAmountCents, PaymentAmount::toCents($this->amountRefundable));
    }

    public function taxIncludedAmountCentsFor(int $amountCents): int
    {
        if ($this->unitAmount <= 0.0) {
            return $amountCents;
        }

        $taxIncludedAmount = PaymentAmount::fromCents($amountCents)
            * $this->unitAmountTaxIncluded
            / $this->unitAmount;

        return PaymentAmount::toCents($taxIncludedAmount);
    }
}
