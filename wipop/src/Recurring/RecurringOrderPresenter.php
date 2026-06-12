<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

use function sprintf;
use function strtoupper;

final class RecurringOrderPresenter
{
    public function __construct(
        private RecurringSubscriptionRepository $subscriptions,
        private RecurringChargeRepository $charges,
    ) {
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    public function presentForOrder(int $orderId): array
    {
        $subscription = $this->subscriptions->findByParentOrderId($orderId);
        $charge = null;

        if ($subscription === null) {
            $charge = $this->charges->findByOrderId($orderId);
            $subscription = $charge === null ? null : $this->subscriptions->findById($charge->subscriptionId);
        }

        if ($subscription === null) {
            return ['has_recurring' => false];
        }

        return [
            'has_recurring' => true,
            'subscription_id' => $subscription->id,
            'period' => $subscription->period->label(),
            'status' => $subscription->status->value,
            'status_label' => $this->statusLabel($subscription->status),
            'status_badge_class' => $this->statusBadgeClass($subscription->status),
            'amount' => sprintf(
                '%s %s',
                number_format($subscription->amount, 2, '.', ''),
                strtoupper($subscription->currencyIso)
            ),
            'next_attempt_at' => $subscription->nextAttemptAt,
            'next_sequence' => $subscription->nextSequence,
            'last_transaction_id' => $subscription->lastTransactionId,
            'charge_sequence' => $charge?->sequence,
            'can_stop' => $subscription->status === RecurringSubscriptionStatus::ACTIVE,
        ];
    }

    private function statusLabel(RecurringSubscriptionStatus $status): string
    {
        return match ($status) {
            RecurringSubscriptionStatus::PENDING_INITIAL_PAYMENT => 'Pending initial payment',
            RecurringSubscriptionStatus::ACTIVE => 'Active',
            RecurringSubscriptionStatus::STOPPED => 'Stopped',
        };
    }

    private function statusBadgeClass(RecurringSubscriptionStatus $status): string
    {
        return match ($status) {
            RecurringSubscriptionStatus::ACTIVE => 'success',
            RecurringSubscriptionStatus::PENDING_INITIAL_PAYMENT => 'warning',
            RecurringSubscriptionStatus::STOPPED => 'secondary',
        };
    }
}
