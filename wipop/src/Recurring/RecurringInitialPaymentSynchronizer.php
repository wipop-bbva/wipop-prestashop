<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

use DateTimeImmutable;
use PrestaShop\Module\Wipop\Card\CardCofIntent;
use PrestaShop\Module\Wipop\Card\StoredCardRepository;
use PrestaShop\Module\Wipop\Log\Logger;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use Wipop\Domain\Transaction;

final class RecurringInitialPaymentSynchronizer
{
    public function __construct(
        private RecurringSubscriptionRepository $subscriptions,
        private StoredCardRepository $storedCards,
        private RecurringDateCalculator $dateCalculator,
        private Logger $logger,
    ) {
    }

    public function sync(PaymentAttempt $attempt, Transaction $transaction, PaymentAttemptStatus $status): void
    {
        if ($status !== PaymentAttemptStatus::PAID || !$this->subscriptions->hasPendingForAttempt($attempt->id)) {
            return;
        }

        if ($attempt->orderId === null) {
            $this->logger->warning('Wipop recurring subscription pending without parent order.', [
                'attempt_id' => $attempt->id,
            ]);

            return;
        }

        $storedCardId = $attempt->storedCardId;
        if ($storedCardId === null && $attempt->cofIntent === CardCofIntent::SAVE) {
            $storedCardId = $this->storedCards->findActiveBySaveAttemptId($attempt->id)?->id;
        }

        if ($storedCardId === null) {
            $this->subscriptions->stopPendingForAttempt($attempt->id, $attempt->orderId);
            $this->logger->warning('Wipop recurring subscription could not be activated without stored card.', [
                'attempt_id' => $attempt->id,
                'order_id' => $attempt->orderId,
            ]);

            return;
        }

        $this->subscriptions->activateForAttempt(
            $attempt->id,
            $attempt->orderId,
            $storedCardId,
            $this->paidAt($transaction),
            $this->dateCalculator
        );
    }

    private function paidAt(Transaction $transaction): DateTimeImmutable
    {
        return $transaction->operationDate
            ?? $transaction->creationDate
            ?? new DateTimeImmutable();
    }
}
