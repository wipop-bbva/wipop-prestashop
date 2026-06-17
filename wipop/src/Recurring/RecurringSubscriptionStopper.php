<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

use PrestaShop\Module\Wipop\Log\Logger;

final class RecurringSubscriptionStopper
{
    public function __construct(
        private RecurringSubscriptionRepository $subscriptions,
        private RecurringSubscriptionLock $subscriptionLock,
        private Logger $logger,
    ) {
    }

    /**
     * @param array<string, int|string> $context
     */
    public function stopActiveForParentOrder(int $orderId, string $reason, array $context = []): int
    {
        $stopped = 0;

        foreach ($this->subscriptions->findActiveByParentOrderId($orderId) as $subscription) {
            if ($this->stopLocked($subscription->id, $reason, $context)) {
                ++$stopped;
            }
        }

        return $stopped;
    }

    /**
     * @param array<string, int|string> $context
     */
    private function stopLocked(int $subscriptionId, string $reason, array $context): bool
    {
        return $this->subscriptionLock->run($subscriptionId, function () use (
            $subscriptionId,
            $reason,
            $context
        ): bool {
            $subscription = $this->subscriptions->findById($subscriptionId);
            if ($subscription === null || $subscription->status !== RecurringSubscriptionStatus::ACTIVE) {
                return false;
            }

            $this->subscriptions->stop($subscriptionId);
            $this->logger->warning($reason, [
                ...$context,
                'subscription_id' => $subscriptionId,
                'parent_order_id' => $subscription->parentOrderId ?? 0,
            ]);

            return true;
        });
    }
}
