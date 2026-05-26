<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Refund;

use PrestaShop\Module\Wipop\Payment\PaymentAmount;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptSummary;

final class ManualRefundEligibilityChecker
{
    public function canRefund(PaymentAttemptSummary $summary): bool
    {
        if ($summary->status !== PaymentAttemptStatus::PAID || trim((string) $summary->wipopTransactionId) === '') {
            return false;
        }

        $refundedCents = PaymentAmount::toCents($summary->refundSummary->refundedAmount ?? 0.0);

        return $refundedCents < PaymentAmount::toCents($summary->amount);
    }
}
