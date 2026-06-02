<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Webhook;

use PrestaShop\Module\Wipop\Configuration\CaptureMode;
use PrestaShop\Module\Wipop\Exception\WebhookException;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use PrestaShop\Module\Wipop\Payment\PaymentEventData;
use PrestaShop\Module\Wipop\Payment\PreauthorizationEventStoreInterface;
use PrestaShop\Module\Wipop\Preauthorization\PreauthorizationOrderUpdaterInterface;
use Wipop\Domain\ChargeMethod;
use Wipop\Domain\Transaction;
use Wipop\Domain\TransactionStatus;

final class WebhookPreauthorizationOperationSynchronizer
{
    private const WEBHOOK_EMPLOYEE_ID = 0;

    public function __construct(
        private PreauthorizationEventStoreInterface $eventStore,
        private WebhookPaymentValidator $paymentValidator,
        private PreauthorizationOrderUpdaterInterface $orderUpdater,
    ) {
    }

    public function supports(PaymentAttempt $attempt, Transaction $transaction): bool
    {
        return $this->supportedEventType($attempt, $transaction) !== null;
    }

    /**
     * @throws WebhookException
     */
    public function sync(PaymentAttempt $attempt, Transaction $transaction): PaymentAttemptStatus
    {
        $eventType = $this->supportedEventType($attempt, $transaction);
        if ($eventType === null) {
            throw new WebhookException('Unsupported Wipop preauthorization operation webhook.', 400);
        }

        $orderId = $attempt->orderId;
        if ($orderId === null) {
            throw new WebhookException('Wipop preauthorization operation requires a linked order.', 400);
        }

        if ($transaction->status === null) {
            throw new WebhookException('Missing transaction status.', 400);
        }

        $transactionId = $this->transactionId($transaction);
        $targetStatus = $this->targetStatus($attempt, $transaction, $eventType);

        $this->eventStore->savePreauthorizationOperation(
            $attempt,
            $this->eventFromTransaction($attempt, $transaction, $eventType, $transactionId),
            null
        );

        if ($targetStatus === null) {
            /* Non-completed operation webhooks are audit-only */
            return $attempt->status;
        }

        $this->paymentValidator->assertTransactionMatches($attempt, $transaction);

        if ($targetStatus === PaymentAttemptStatus::PAID) {
            $this->orderUpdater->markCaptured(
                $orderId,
                (float) $transaction->amount,
                strtoupper(trim((string) $transaction->currency)),
                $transactionId,
                self::WEBHOOK_EMPLOYEE_ID
            );
        } else {
            $this->orderUpdater->markCancelled($orderId);
        }

        return $targetStatus;
    }

    private function targetStatus(
        PaymentAttempt $attempt,
        Transaction $transaction,
        string $eventType,
    ): ?PaymentAttemptStatus {
        if ($transaction->status !== TransactionStatus::COMPLETED) {
            return null;
        }

        return match ($eventType) {
            PaymentEventData::TYPE_CAPTURE => in_array(
                $attempt->status,
                [PaymentAttemptStatus::AUTHORIZED, PaymentAttemptStatus::PAID],
                true
            ) ? PaymentAttemptStatus::PAID : null,
            PaymentEventData::TYPE_REVERSAL => in_array(
                $attempt->status,
                [PaymentAttemptStatus::AUTHORIZED, PaymentAttemptStatus::CANCELLED],
                true
            ) ? PaymentAttemptStatus::CANCELLED : null,
            default => null,
        };
    }

    private function supportedEventType(PaymentAttempt $attempt, Transaction $transaction): ?string
    {
        if (!$this->isManualCardAttempt($attempt)) {
            return null;
        }

        return $this->eventTypeFromOperation($transaction);
    }

    private function isManualCardAttempt(PaymentAttempt $attempt): bool
    {
        return strtoupper(trim((string) $attempt->paymentMethod)) === ChargeMethod::CARD
            && $attempt->captureMode === CaptureMode::MANUAL;
    }

    private function eventTypeFromOperation(Transaction $transaction): ?string
    {
        $operationType = strtoupper(trim((string) $transaction->operationType));

        if (str_contains($operationType, 'CAPTURE')) {
            return PaymentEventData::TYPE_CAPTURE;
        }

        if (str_contains($operationType, 'REVERSAL')) {
            return PaymentEventData::TYPE_REVERSAL;
        }

        return null;
    }

    /**
     * @throws WebhookException
     */
    private function transactionId(Transaction $transaction): string
    {
        $transactionId = trim((string) $transaction->id);
        if ($transactionId === '') {
            throw new WebhookException('Missing transaction id for Wipop preauthorization operation.', 400);
        }

        return $transactionId;
    }

    private function eventFromTransaction(
        PaymentAttempt $attempt,
        Transaction $transaction,
        string $eventType,
        string $transactionId,
    ): PaymentEventData {
        return new PaymentEventData(
            $transactionId,
            $attempt->wipopTransactionId,
            $transaction->orderId,
            $eventType,
            $transaction->operationType,
            $transaction->status?->value,
            $transaction->amount === null ? null : (float) $transaction->amount,
            $this->currencyIso($transaction),
            $transaction->errorCode,
            $transaction->errorMessage,
        );
    }

    private function currencyIso(Transaction $transaction): ?string
    {
        $currencyIso = strtoupper(trim((string) $transaction->currency));

        return $currencyIso === '' ? null : $currencyIso;
    }
}
