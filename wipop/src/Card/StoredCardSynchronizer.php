<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Card;

use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use Wipop\Domain\ChargeMethod;
use Wipop\Domain\Transaction;

use function strtoupper;
use function trim;

final class StoredCardSynchronizer
{
    public function __construct(private StoredCardRepository $storedCards)
    {
    }

    public function sync(PaymentAttempt $attempt, Transaction $transaction, PaymentAttemptStatus $status): void
    {
        if (!$this->isSuccessfulCardPayment($attempt, $status)) {
            return;
        }

        if ($attempt->cofIntent === CardCofIntent::USE_EXISTING) {
            $this->markExistingCardUsed($attempt);

            return;
        }

        if ($attempt->cofIntent !== CardCofIntent::SAVE || $transaction->card === null) {
            return;
        }

        $details = StoredCardDetails::fromWipopCard($transaction->card);
        if ($details === null) {
            return;
        }

        if ($attempt->customerId <= 0 || $attempt->merchantId === '') {
            return;
        }

        $this->storedCards->store(
            $attempt->customerId,
            $attempt->shopId,
            $attempt->merchantId,
            $attempt->isLive,
            $attempt->terminalId,
            $details,
            $transaction->customerId,
            $attempt->id
        );
    }

    private function markExistingCardUsed(PaymentAttempt $attempt): void
    {
        if ($attempt->storedCardId !== null) {
            $this->storedCards->markUsed($attempt->storedCardId);
        }
    }

    private function isSuccessfulCardPayment(PaymentAttempt $attempt, PaymentAttemptStatus $status): bool
    {
        return strtoupper(trim((string) $attempt->paymentMethod)) === ChargeMethod::CARD
            && in_array($status, [PaymentAttemptStatus::PAID, PaymentAttemptStatus::AUTHORIZED], true);
    }
}
