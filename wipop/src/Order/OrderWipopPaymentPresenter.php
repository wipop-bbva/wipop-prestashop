<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Order;

use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptSummary;
use PrestaShop\Module\Wipop\Payment\PaymentRefundSummary;

final class OrderWipopPaymentPresenter
{
    /**
     * @return array<string, bool|string>
     */
    public function present(PaymentAttemptSummary $summary): array
    {
        $remoteError = $this->joinValues($summary->remoteErrorCode, $summary->remoteErrorMessage);
        $refund = $this->presentRefund($summary);

        return [
            'attempt_id' => (string) $summary->id,
            'cart_id' => (string) $summary->cartId,
            'status_label' => $this->formatStatus($summary->status->value),
            'status_badge_class' => $this->badgeClass($summary->status),
            'remote_status' => $this->displayValue($summary->remoteStatus),
            'payment_method' => $this->displayValue($summary->paymentMethod),
            'amount' => $this->formatAmount($summary->amount, $summary->currencyIso),
            'wipop_order_id' => $this->displayValue($summary->wipopOrderId),
            'wipop_transaction_id' => $this->displayValue($summary->wipopTransactionId),
            'webhook_received_at' => $this->displayValue($summary->webhookReceivedAt),
            'has_error' => $remoteError !== null,
            'remote_error' => $this->displayValue($remoteError),
            ...$refund,
        ];
    }

    private function badgeClass(PaymentAttemptStatus $status): string
    {
        return match ($status) {
            PaymentAttemptStatus::PAID => 'success',
            PaymentAttemptStatus::FAILED,
            PaymentAttemptStatus::CANCELLED => 'danger',
            PaymentAttemptStatus::REFUNDED => 'info',
            PaymentAttemptStatus::AUTHORIZED => 'primary',
            PaymentAttemptStatus::INITIALIZED,
            PaymentAttemptStatus::CHECKOUT_PENDING => 'warning',
        };
    }

    private function displayValue(?string $value): string
    {
        $value = $this->nullableString($value);

        return $value ?? '-';
    }

    private function formatAmount(float $amount, string $currencyIso): string
    {
        return sprintf('%s %s', number_format($amount, 2, '.', ''), strtoupper($currencyIso));
    }

    private function formatStatus(string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * @return array<string, bool|string>
     */
    private function presentRefund(PaymentAttemptSummary $summary): array
    {
        $refund = $summary->refundSummary;
        if (!$refund instanceof PaymentRefundSummary) {
            return [
                'has_refund' => false,
                'refund_status' => '-',
                'refunded_amount' => '-',
                'last_refund_transaction_id' => '-',
                'last_refund_amount' => '-',
                'last_refund_status' => '-',
                'last_refund_received_at' => '-',
                'has_refund_error' => false,
                'last_refund_error' => '-',
            ];
        }

        $refundError = $this->joinValues($refund->lastRefundErrorCode, $refund->lastRefundErrorMessage);

        return [
            'has_refund' => true,
            'refund_status' => $this->refundStatus($summary),
            'refunded_amount' => $refund->completedRefundCount === 0
                ? '-'
                : $this->formatAmount($refund->refundedAmount, $refund->currencyIso),
            'last_refund_transaction_id' => $this->displayValue($refund->lastRefundTransactionId),
            'last_refund_amount' => $this->formatOptionalAmount(
                $refund->lastRefundAmount,
                $refund->lastRefundCurrencyIso ?? $refund->currencyIso
            ),
            'last_refund_status' => $this->displayValue($refund->lastRefundStatus),
            'last_refund_received_at' => $this->displayValue($refund->lastRefundReceivedAt),
            'has_refund_error' => $refundError !== null,
            'last_refund_error' => $this->displayValue($refundError),
        ];
    }

    private function refundStatus(PaymentAttemptSummary $summary): string
    {
        if ($summary->status === PaymentAttemptStatus::REFUNDED) {
            return 'Refunded';
        }

        if ($summary->refundSummary?->completedRefundCount === 0) {
            return 'Refund event';
        }

        return 'Partial refund';
    }

    private function formatOptionalAmount(?float $amount, string $currencyIso): string
    {
        return $amount === null ? '-' : $this->formatAmount($amount, $currencyIso);
    }

    private function joinValues(?string $first, ?string $second): ?string
    {
        $first = $this->nullableString($first);
        $second = $this->nullableString($second);

        if ($first === null) {
            return $second;
        }

        if ($second === null) {
            return $first;
        }

        return sprintf('%s - %s', $first, $second);
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
