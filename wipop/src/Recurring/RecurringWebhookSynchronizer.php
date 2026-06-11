<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use Wipop\Domain\Transaction;

final class RecurringWebhookSynchronizer
{
    public function __construct(
        private RecurringInitialPaymentSynchronizer $initialPaymentSynchronizer,
        private RecurringChargeWebhookSynchronizer $chargeWebhookSynchronizer,
    ) {
    }

    public function sync(PaymentAttempt $attempt, Transaction $transaction, PaymentAttemptStatus $status): void
    {
        $this->initialPaymentSynchronizer->sync($attempt, $transaction, $status);
        $this->chargeWebhookSynchronizer->sync($attempt, $transaction, $status);
    }
}
