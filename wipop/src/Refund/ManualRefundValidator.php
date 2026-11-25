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

use PrestaShop\Module\Wipop\Configuration\Settings;
use PrestaShop\Module\Wipop\Payment\PaymentAmount;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptSummary;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ManualRefundValidator
{
    public function __construct(private WipopRefundTaxIncludedAmountCalculator $taxIncludedAmountCalculator)
    {
    }

    /**
     * @throws ManualRefundException
     */
    public function assertRefundable(
        PaymentAttemptSummary $summary,
        ManualRefundRequest $request,
        ManualRefundFormData $formData,
    ): void {
        if ($summary->status !== PaymentAttemptStatus::PAID) {
            throw new ManualRefundException('Only paid Wipop orders can be refunded.');
        }

        if (trim((string) $summary->wipopTransactionId) === '') {
            throw new ManualRefundException('The original Wipop transaction is missing.');
        }

        if (!Settings::isCardMethod((string) $summary->paymentMethod)) {
            throw new ManualRefundException('Only Wipop card payments can be refunded automatically.');
        }

        if ($this->normalizeCurrency($summary->currencyIso) !== $this->normalizeCurrency($formData->currencyIso)) {
            throw new ManualRefundException('The order currency does not match the Wipop payment currency.');
        }

        $this->assertLines($request, $formData);
        $this->assertShipping($request, $formData);

        if ($request->totalCents() <= 0) {
            throw new ManualRefundException('The refund amount must be greater than zero.');
        }

        $wipopAmountCents = $this->taxIncludedAmountCalculator->amountCentsForWipop($request, $formData);

        if ($wipopAmountCents > $this->remainingCents($summary)) {
            throw new ManualRefundException('The refund exceeds the remaining Wipop amount.');
        }
    }

    private function assertLines(ManualRefundRequest $request, ManualRefundFormData $formData): void
    {
        foreach ($request->lines() as $line) {
            $formLine = $formData->requireLineByOrderDetailId($line->orderDetailId);

            if ($line->quantity <= 0 || $line->amountCents() <= 0) {
                throw new ManualRefundException('Each refunded product must include quantity and amount.');
            }

            if ($line->quantity > $formLine->quantityRefundable) {
                throw new ManualRefundException('The refund quantity exceeds the refundable product quantity.');
            }

            if ($line->amountCents() > $formLine->amountRefundableForQuantityCents($line->quantity)) {
                throw new ManualRefundException('The refund amount exceeds the refundable product amount.');
            }
        }
    }

    private function assertShipping(ManualRefundRequest $request, ManualRefundFormData $formData): void
    {
        if ($request->shippingCents() > $formData->shippingRefundableCents()) {
            throw new ManualRefundException('The shipping refund exceeds the refundable shipping amount.');
        }
    }

    private function remainingCents(PaymentAttemptSummary $summary): int
    {
        $paidCents = PaymentAmount::toCents($summary->amount);
        $refundedCents = PaymentAmount::toCents($summary->refundSummary->refundedAmount ?? 0.0);

        return max(0, $paidCents - $refundedCents);
    }

    private function normalizeCurrency(string $currencyIso): string
    {
        return strtoupper(trim($currencyIso));
    }
}
