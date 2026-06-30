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

final class ManualRefundRequest
{
    /**
     * @param ManualRefundLine[] $lines
     */
    public function __construct(
        private array $lines,
        public readonly float $shippingAmount,
        public readonly bool $restock,
    ) {
    }

    /**
     * @return ManualRefundLine[]
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function shippingCents(): int
    {
        return PaymentAmount::toCents($this->shippingAmount);
    }

    public function totalCents(): int
    {
        $total = $this->shippingCents();
        foreach ($this->lines as $line) {
            $total += $line->amountCents();
        }

        return $total;
    }

    public function shippingAmountForCommand(): string
    {
        return ManualRefundLine::formatCents($this->shippingCents());
    }

    /**
     * @return array<int, array{quantity: int, amount: string}>
     */
    public function orderDetailRefundsForCommand(): array
    {
        $refunds = [];
        foreach ($this->lines as $line) {
            $refunds[$line->orderDetailId] = [
                'quantity' => $line->quantity,
                'amount' => $line->amountForCommand(),
            ];
        }

        return $refunds;
    }
}
