<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Webhook;

use PrestaShop\Module\Wipop\Exception\WebhookException;
use PrestaShop\Module\Wipop\Log\Logger;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptRepository;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use Wipop\Domain\Transaction;
use Wipop\Domain\TransactionStatus;

final class WebhookTransactionHandler
{
    public function __construct(
        private WebhookTransactionHydrator $hydrator,
        private PaymentAttemptRepository $paymentAttemptRepository,
        private WebhookOrderSynchronizer $orderSynchronizer,
        private Logger $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws WebhookException
     */
    public function handle(array $payload): void
    {
        $transaction = $this->hydrator->hydrate($payload);
        $attemptStatus = $this->mapStatus($transaction);
        $attempt = $this->paymentAttemptRepository->findByRemoteIdentifiers(
            $transaction->orderId,
            $transaction->id
        );

        if ($attempt === null) {
            throw new WebhookException('Could not find the local Wipop payment attempt.', 404);
        }

        $this->paymentAttemptRepository->syncFromWebhook($attempt, $transaction, $attemptStatus);
        $this->orderSynchronizer->sync($attempt, $transaction);

        $this->logger->info('Webhook transaction synchronized.', [
            'attempt_id' => $attempt->id,
            'cart_id' => $attempt->cartId,
            'transaction_id' => $transaction->id ?? '',
            'wipop_order_id' => $transaction->orderId ?? '',
            'remote_status' => $transaction->status->value,
            'attempt_status' => $attemptStatus->value,
        ]);
    }

    /**
     * @throws WebhookException
     */
    private function mapStatus(Transaction $transaction): PaymentAttemptStatus
    {
        return match ($transaction->status) {
            TransactionStatus::COMPLETED => PaymentAttemptStatus::PAID,
            TransactionStatus::FAILED,
            TransactionStatus::ERROR => PaymentAttemptStatus::FAILED,
            TransactionStatus::IN_PROGRESS,
            TransactionStatus::CHARGE_PENDING => PaymentAttemptStatus::CHECKOUT_PENDING,
            null => throw new WebhookException('Missing transaction status.', 400),
        };
    }
}
