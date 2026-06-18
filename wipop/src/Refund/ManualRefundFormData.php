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

final class ManualRefundFormData
{
    /**
     * @param ManualRefundFormLine[] $lines
     */
    public function __construct(
        public readonly array $lines,
        public readonly float $shippingRefundableAmount,
        public readonly float $shippingRefundableAmountTaxIncluded,
        public readonly string $shippingRefundableAmountFormatted,
        public readonly string $currencyIso,
        public readonly string $taxMethod,
        public readonly bool $isTaxIncluded,
    ) {
    }

    public function shippingRefundableCents(): int
    {
        return PaymentAmount::toCents($this->shippingRefundableAmount);
    }

    public function shippingTaxIncludedAmountCentsFor(int $shippingCents): int
    {
        if ($this->isTaxIncluded) {
            return $shippingCents;
        }

        $shippingRefundableCents = $this->shippingRefundableCents();
        if ($shippingRefundableCents <= 0) {
            return $shippingCents;
        }

        $shippingRefundableTaxIncludedCents = PaymentAmount::toCents($this->shippingRefundableAmountTaxIncluded);

        return (int) round($shippingCents * $shippingRefundableTaxIncludedCents / $shippingRefundableCents);
    }

    /**
     * @throws ManualRefundException
     */
    public function requireLineByOrderDetailId(int $orderDetailId): ManualRefundFormLine
    {
        foreach ($this->lines as $line) {
            if ($line->orderDetailId === $orderDetailId) {
                return $line;
            }
        }

        throw new ManualRefundException('The refund contains an unknown order line.');
    }
}
