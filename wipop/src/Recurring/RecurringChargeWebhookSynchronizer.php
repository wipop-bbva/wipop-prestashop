<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

use PrestaShop\Module\Wipop\Log\Logger;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use Wipop\Domain\Transaction;

final class RecurringChargeWebhookSynchronizer
{
    public function __construct(
        private RecurringChargeRepository $charges,
        private RecurringSubscriptionRepository $subscriptions,
        private RecurringDateCalculator $dateCalculator,
        private Logger $logger,
    ) {
    }

    public function sync(PaymentAttempt $attempt, Transaction $transaction, PaymentAttemptStatus $status): void
    {
        $charge = $this->charges->findByAttemptId($attempt->id);
        if ($charge === null) {
            return;
        }

        if ($charge->status === RecurringChargeStatus::FAILED) {
            return;
        }

        $subscription = $this->subscriptions->findById($charge->subscriptionId);
        if ($subscription === null) {
            return;
        }

        if ($status === PaymentAttemptStatus::PAID) {
            $this->markPaid($attempt, $transaction, $charge, $subscription);

            return;
        }

        if ($this->isTerminalFailure($status)) {
            $this->markFailed($transaction, $charge, $subscription);
        }
    }

    private function isTerminalFailure(PaymentAttemptStatus $status): bool
    {
        return in_array($status, [PaymentAttemptStatus::FAILED, PaymentAttemptStatus::CANCELLED], true);
    }

    private function markPaid(
        PaymentAttempt $attempt,
        Transaction $transaction,
        RecurringCharge $charge,
        RecurringSubscription $subscription,
    ): void {
        $transactionId = $this->transactionId($transaction) ?? $charge->wipopTransactionId;
        if ($transactionId === null) {
            $this->logger->warning('Wipop recurring charge paid webhook could not be linked locally.', [
                'attempt_id' => $attempt->id,
                'subscription_id' => $subscription->id,
                'charge_id' => $charge->id,
            ]);

            return;
        }

        if ($charge->status !== RecurringChargeStatus::PAID) {
            if ($attempt->orderId === null) {
                $this->logger->warning('Wipop recurring charge paid webhook could not be linked locally.', [
                    'attempt_id' => $attempt->id,
                    'subscription_id' => $subscription->id,
                    'charge_id' => $charge->id,
                ]);

                return;
            }

            $this->charges->markPaid($charge->id, $attempt->orderId, $transactionId);
        }

        if (
            $subscription->status !== RecurringSubscriptionStatus::ACTIVE
            || $subscription->nextSequence !== $charge->sequence
        ) {
            return;
        }

        $this->subscriptions->advance($subscription, $transactionId, $this->dateCalculator);
    }

    private function markFailed(
        Transaction $transaction,
        RecurringCharge $charge,
        RecurringSubscription $subscription,
    ): void {
        $this->charges->markFailed(
            $charge->id,
            $transaction->errorCode,
            $transaction->errorMessage,
            $this->transactionId($transaction)
        );
        $this->subscriptions->stop($subscription->id);
    }

    private function transactionId(Transaction $transaction): ?string
    {
        $transactionId = trim((string) $transaction->id);

        return $transactionId === '' ? null : $transactionId;
    }
}
