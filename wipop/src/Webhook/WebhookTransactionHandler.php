<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Webhook;

use Configuration;
use PrestaShop\Module\Wipop\Configuration\CaptureMode;
use PrestaShop\Module\Wipop\Configuration\ConfigKeys;
use PrestaShop\Module\Wipop\Exception\WebhookException;
use PrestaShop\Module\Wipop\Log\Logger;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptRepository;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use PrestaShop\Module\Wipop\Payment\TransactionType;
use Wipop\Domain\ChargeMethod;
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
        private WebhookPreauthorizationOperationSynchronizer $preauthorizationOperationSynchronizer,
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

        $attempt = $this->paymentAttemptRepository->findByRemoteIdentifiers(
            $transaction->orderId,
            $transaction->id
        );

        if ($attempt === null) {
            throw new WebhookException('Could not find the local Wipop payment attempt.', 404);
        }

        if ($this->preauthorizationOperationSynchronizer->supports($attempt, $transaction)) {
            $attemptStatus = $this->preauthorizationOperationSynchronizer->sync($attempt, $transaction);
            $this->paymentAttemptRepository->syncFromWebhook($attempt, $transaction, $attemptStatus);

            $this->logger->info('Webhook preauthorization operation synchronized.', [
                'attempt_id' => $attempt->id,
                'cart_id' => $attempt->cartId,
                'transaction_id' => $transaction->id ?? '',
                'wipop_order_id' => $transaction->orderId ?? '',
                'operation_type' => $transaction->operationType ?? '',
                'remote_status' => $transaction->status->value,
                'attempt_status' => $attemptStatus->value,
            ]);

            return;
        }

        $attemptStatus = $this->mapStatus($attempt, $transaction);
        $effectiveAttemptStatus = $attempt->status->syncWithWebhookStatus($attemptStatus);
        $this->syncOrderIfNeeded($attempt, $transaction, $effectiveAttemptStatus);
        $this->paymentAttemptRepository->syncFromWebhook($attempt, $transaction, $effectiveAttemptStatus);

        $this->logger->info('Webhook transaction synchronized.', [
            'attempt_id' => $attempt->id,
            'cart_id' => $attempt->cartId,
            'transaction_id' => $transaction->id ?? '',
            'wipop_order_id' => $transaction->orderId ?? '',
            'remote_status' => $transaction->status->value,
            'attempt_status' => $effectiveAttemptStatus->value,
        ]);
    }

    /**
     * @throws WebhookException
     */
    private function mapStatus(PaymentAttempt $attempt, Transaction $transaction): PaymentAttemptStatus
    {
        /* Manual card auths are not paid here; capture/reversal webhooks finalize them later */
        return match ($transaction->status) {
            TransactionStatus::COMPLETED => $this->isOpenManualCardAuthorization($attempt)
                ? $attempt->status
                : PaymentAttemptStatus::PAID,
            TransactionStatus::FAILED,
            TransactionStatus::ERROR => PaymentAttemptStatus::FAILED,
            TransactionStatus::IN_PROGRESS => $this->isOpenManualCardAuthorization($attempt)
                ? PaymentAttemptStatus::AUTHORIZED
                : PaymentAttemptStatus::CHECKOUT_PENDING,
            TransactionStatus::CHARGE_PENDING => PaymentAttemptStatus::CHECKOUT_PENDING,
            null => throw new WebhookException('Missing transaction status.', 400),
        };
    }

    /**
     * @throws WebhookException
     */
    private function syncOrderIfNeeded(
        PaymentAttempt $attempt,
        Transaction $transaction,
        PaymentAttemptStatus $attemptStatus,
    ): void {
        if ($attemptStatus === PaymentAttemptStatus::PAID) {
            $this->orderSynchronizer->sync($attempt, $transaction, (int) Configuration::get('PS_OS_PAYMENT'));

            return;
        }

        if ($attemptStatus === PaymentAttemptStatus::AUTHORIZED) {
            $this->orderSynchronizer->sync(
                $attempt,
                $transaction,
                (int) Configuration::get(ConfigKeys::AUTHORIZED_ORDER_STATE_ID)
            );
        }
    }

    private function isOpenManualCardAuthorization(PaymentAttempt $attempt): bool
    {
        return strtoupper(trim((string) $attempt->paymentMethod)) === ChargeMethod::CARD
            && $attempt->captureMode === CaptureMode::MANUAL
            && in_array($attempt->status, [
                PaymentAttemptStatus::INITIALIZED,
                PaymentAttemptStatus::CHECKOUT_PENDING,
                PaymentAttemptStatus::AUTHORIZED,
            ], true);
    }
}
