<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Refund;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptSummary;
use PrestaShop\Module\Wipop\Payment\PaymentRefundSummary;
use PrestaShop\Module\Wipop\Refund\ManualRefundEligibilityChecker;

final class ManualRefundEligibilityCheckerTest extends TestCase
{
    public function testItAllowsPaidAttemptsWithRemainingAmount(): void
    {
        self::assertTrue((new ManualRefundEligibilityChecker())->canRefund($this->summary()));
    }

    public function testItRejectsAttemptsThatCannotBeRefunded(): void
    {
        $eligibility = new ManualRefundEligibilityChecker();

        self::assertFalse($eligibility->canRefund($this->summary(status: PaymentAttemptStatus::FAILED)));
        self::assertFalse($eligibility->canRefund($this->summary(transactionId: null)));
        self::assertFalse($eligibility->canRefund($this->summary(paymentMethod: 'BIZUM')));
        self::assertFalse($eligibility->canRefund($this->summary(refundedAmount: 49.9)));
    }

    private function summary(
        PaymentAttemptStatus $status = PaymentAttemptStatus::PAID,
        ?string $transactionId = 'tx_charge',
        string $paymentMethod = 'card',
        float $refundedAmount = 0.0,
    ): PaymentAttemptSummary {
        return new PaymentAttemptSummary(
            id: 12,
            cartId: 34,
            paymentMethod: $paymentMethod,
            status: $status,
            amount: 49.9,
            currencyIso: 'EUR',
            wipopOrderId: 'WP1234567890',
            wipopTransactionId: $transactionId,
            remoteStatus: 'COMPLETED',
            remoteErrorCode: null,
            remoteErrorMessage: null,
            webhookReceivedAt: '2026-05-19 10:11:12',
            refundSummary: $this->refundSummary(refundedAmount: $refundedAmount),
        );
    }

    private function refundSummary(float $refundedAmount): ?PaymentRefundSummary
    {
        if ($refundedAmount <= 0.0) {
            return null;
        }

        return new PaymentRefundSummary(
            refundedAmount: $refundedAmount,
            currencyIso: 'EUR',
            completedRefundCount: 1,
            lastRefundTransactionId: 'tx_refund',
            lastRefundStatus: 'COMPLETED',
            lastRefundAmount: $refundedAmount,
            lastRefundCurrencyIso: 'EUR',
            lastRefundReceivedAt: null,
            lastRefundErrorCode: null,
            lastRefundErrorMessage: null,
        );
    }
}
