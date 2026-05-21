<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Webhook;

use PrestaShop\Module\Wipop\Exception\WebhookException;
use PrestaShop\Module\Wipop\Log\Logger;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptRepository;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use PrestaShop\Module\Wipop\Payment\TransactionType;
use Wipop\Domain\Transaction;
use Wipop\Domain\TransactionStatus;

final class WebhookTransactionHandler
{
    public function __construct(
        private WebhookTransactionHydrator $hydrator,
        private PaymentAttemptRepository $paymentAttemptRepository,
        private WebhookOrderSynchronizer $orderSynchronizer,
        private WebhookRefundExtractor $refundExtractor,
        private WebhookRefundSynchronizer $refundSynchronizer,
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
        $refund = $this->refundExtractor->extract($transaction, $payload);

        if ($refund !== null) {
            $attempt = $this->paymentAttemptRepository->findByRemoteIdentifiers(
                $refund->wipopOrderId,
                $refund->parentTransactionId
            );

            if ($attempt === null) {
                throw new WebhookException('Could not find the local Wipop payment attempt.', 404);
            }

            $this->refundSynchronizer->sync($attempt, $refund);

            $this->logger->info('Webhook refund synchronized.', [
                'attempt_id' => $attempt->id,
                'cart_id' => $attempt->cartId,
                'refund_transaction_id' => $refund->remoteTransactionId,
                'wipop_order_id' => $refund->wipopOrderId ?? '',
                'remote_status' => $refund->status ?? '',
            ]);

            return;
        }

        if (!TransactionType::isChargeOrMissing($transaction->transactionType)) {
            $this->logger->info('Webhook transaction ignored.', [
                'transaction_id' => $transaction->id ?? '',
                'wipop_order_id' => $transaction->orderId ?? '',
                'transaction_type' => $transaction->transactionType ?? '',
                'remote_status' => $transaction->status === null ? '' : $transaction->status->value,
            ]);

            return;
        }

        $attemptStatus = $this->mapStatus($transaction);
        $attempt = $this->paymentAttemptRepository->findByRemoteIdentifiers(
            $transaction->orderId,
            $transaction->id
        );

        if ($attempt === null) {
            throw new WebhookException('Could not find the local Wipop payment attempt.', 404);
        }

        $this->orderSynchronizer->sync($attempt, $transaction);
        $this->paymentAttemptRepository->syncFromWebhook($attempt, $transaction, $attemptStatus);

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
