<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Webhook;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Configuration\CaptureMode;
use PrestaShop\Module\Wipop\Exception\WebhookException;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use PrestaShop\Module\Wipop\Payment\PaymentEventData;
use PrestaShop\Module\Wipop\Payment\PreauthorizationEventStoreInterface;
use PrestaShop\Module\Wipop\Preauthorization\PreauthorizationOrderUpdaterInterface;
use PrestaShop\Module\Wipop\Webhook\WebhookPaymentValidator;
use PrestaShop\Module\Wipop\Webhook\WebhookPreauthorizationOperationSynchronizer;
use Wipop\Domain\Transaction;
use Wipop\Domain\TransactionStatus;

final class WebhookPreauthorizationOperationSynchronizerTest extends TestCase
{
    public function testItRecordsCaptureWebhookAndMarksOrderPaid(): void
    {
        $savedEvent = null;
        $employeeId = null;
        $capturedOrder = [];
        $synchronizer = $this->synchronizer(
            $this->eventStore($savedEvent, $employeeId),
            $this->orderUpdater(capturedOrder: $capturedOrder)
        );

        $status = $synchronizer->sync(
            $this->attempt(),
            $this->transaction('tx_capture', 'capture')
        );

        self::assertSame(PaymentAttemptStatus::PAID, $status);
        self::assertSame(PaymentEventData::TYPE_CAPTURE, $savedEvent?->transactionType);
        self::assertSame('tx_capture', $savedEvent?->remoteTransactionId);
        self::assertSame('tx_auth', $savedEvent?->parentTransactionId);
        self::assertNull($employeeId);
        self::assertSame([
            'order_id' => 56,
            'amount' => 49.9,
            'currency' => 'EUR',
            'transaction_id' => 'tx_capture',
            'employee_id' => 0,
        ], $capturedOrder);
    }

    public function testItRecordsReversalWebhookAndCancelsOrder(): void
    {
        $savedEvent = null;
        $cancelledOrderId = null;
        $synchronizer = $this->synchronizer(
            $this->eventStore($savedEvent),
            $this->orderUpdater(cancelledOrderId: $cancelledOrderId)
        );

        $status = $synchronizer->sync(
            $this->attempt(),
            $this->transaction('tx_reversal', 'reversal', TransactionStatus::CANCELLED)
        );

        self::assertSame(PaymentAttemptStatus::CANCELLED, $status);
        self::assertSame(PaymentEventData::TYPE_REVERSAL, $savedEvent?->transactionType);
        self::assertSame(TransactionStatus::CANCELLED->value, $savedEvent?->status);
        self::assertSame('tx_reversal', $savedEvent?->remoteTransactionId);
        self::assertSame('tx_auth', $savedEvent?->parentTransactionId);
        self::assertSame(56, $cancelledOrderId);
    }

    public function testItRecordsFailedReversalWebhookWithoutCancellingAuthorization(): void
    {
        $savedEvent = null;
        $capturedOrder = null;
        $cancelledOrderId = null;
        $synchronizer = $this->synchronizer(
            $this->eventStore($savedEvent),
            $this->orderUpdater(capturedOrder: $capturedOrder, cancelledOrderId: $cancelledOrderId)
        );

        $status = $synchronizer->sync(
            $this->attempt(),
            $this->transaction(
                'tx_reversal_failed',
                'reversal',
                TransactionStatus::FAILED,
                amount: null,
                currency: null
            )
        );

        self::assertSame(PaymentAttemptStatus::AUTHORIZED, $status);
        if (!$savedEvent instanceof PaymentEventData) {
            self::fail('Expected failed reversal webhook to be recorded.');
        }

        self::assertSame(PaymentEventData::TYPE_REVERSAL, $savedEvent->transactionType);
        self::assertSame(TransactionStatus::FAILED->value, $savedEvent->status);
        self::assertSame('tx_reversal_failed', $savedEvent->remoteTransactionId);
        self::assertNull($capturedOrder);
        self::assertNull($cancelledOrderId);
    }

    public function testItCanRepairACaptureWebhookAfterAttemptWasMarkedPaid(): void
    {
        $capturedOrder = [];
        $synchronizer = $this->synchronizer(
            $this->eventStore(),
            $this->orderUpdater(capturedOrder: $capturedOrder)
        );

        $status = $synchronizer->sync(
            $this->attempt(status: PaymentAttemptStatus::PAID),
            $this->transaction('tx_capture', 'capture')
        );

        self::assertSame(PaymentAttemptStatus::PAID, $status);
        self::assertSame('tx_capture', $capturedOrder['transaction_id'] ?? null);
    }

    public function testItCanProcessGenericCancelledAuthorizationWebhook(): void
    {
        $savedEvent = null;
        $cancelledOrderId = null;
        $synchronizer = $this->synchronizer(
            $this->eventStore($savedEvent),
            $this->orderUpdater(cancelledOrderId: $cancelledOrderId)
        );

        $status = $synchronizer->sync(
            $this->attempt(),
            $this->transaction('tx_auth', 'IN', TransactionStatus::CANCELLED)
        );

        self::assertSame(PaymentAttemptStatus::CANCELLED, $status);
        self::assertSame(PaymentEventData::TYPE_REVERSAL, $savedEvent?->transactionType);
        self::assertSame('tx_auth', $savedEvent?->remoteTransactionId);
        self::assertSame(56, $cancelledOrderId);
    }

    public function testItRecordsFailedCaptureWebhookWithoutFinalizingAuthorization(): void
    {
        $savedEvent = null;
        $capturedOrder = null;
        $cancelledOrderId = null;
        $synchronizer = $this->synchronizer(
            $this->eventStore($savedEvent),
            $this->orderUpdater(capturedOrder: $capturedOrder, cancelledOrderId: $cancelledOrderId)
        );

        $status = $synchronizer->sync(
            $this->attempt(),
            $this->transaction(
                'tx_capture_failed',
                'capture',
                TransactionStatus::ERROR,
                amount: null,
                currency: null
            )
        );

        self::assertSame(PaymentAttemptStatus::AUTHORIZED, $status);
        if (!$savedEvent instanceof PaymentEventData) {
            self::fail('Expected failed capture webhook to be recorded.');
        }

        self::assertSame(PaymentEventData::TYPE_CAPTURE, $savedEvent->transactionType);
        self::assertSame(TransactionStatus::ERROR->value, $savedEvent->status);
        self::assertSame('tx_capture_failed', $savedEvent->remoteTransactionId);
        self::assertNull($savedEvent->amount);
        self::assertNull($savedEvent->currencyIso);
        self::assertNull($capturedOrder);
        self::assertNull($cancelledOrderId);
    }

    public function testItDoesNotSupportUnknownCompletedManualCardOperations(): void
    {
        $synchronizer = $this->synchronizer($this->eventStore(), $this->orderUpdater());

        self::assertFalse($synchronizer->supports(
            $this->attempt(),
            $this->transaction('tx_operation', 'authorization')
        ));
    }

    public function testItRejectsOperationWebhookWithoutLinkedOrder(): void
    {
        $synchronizer = $this->synchronizer($this->eventStore(), $this->orderUpdater());

        $this->expectException(WebhookException::class);

        $synchronizer->sync(
            $this->attempt(orderId: null),
            $this->transaction('tx_capture', 'capture')
        );
    }

    private function synchronizer(
        PreauthorizationEventStoreInterface $eventStore,
        PreauthorizationOrderUpdaterInterface $orderUpdater,
    ): WebhookPreauthorizationOperationSynchronizer {
        return new WebhookPreauthorizationOperationSynchronizer(
            $eventStore,
            new WebhookPaymentValidator(),
            $orderUpdater
        );
    }

    private function attempt(
        ?int $orderId = 56,
        PaymentAttemptStatus $status = PaymentAttemptStatus::AUTHORIZED,
    ): PaymentAttempt {
        return new PaymentAttempt(
            id: 12,
            cartId: 34,
            orderId: $orderId,
            status: $status,
            amount: 49.9,
            currencyIso: 'EUR',
            paymentMethod: 'CARD',
            captureMode: CaptureMode::MANUAL,
            wipopTransactionId: 'tx_auth'
        );
    }

    private function transaction(
        string $transactionId,
        ?string $operationType,
        TransactionStatus $status = TransactionStatus::COMPLETED,
        ?float $amount = 49.9,
        ?string $currency = 'EUR',
    ): Transaction {
        $transaction = new Transaction();
        $transaction->id = $transactionId;
        $transaction->orderId = 'WP1234567890';
        $transaction->transactionType = 'CHARGE';
        $transaction->operationType = $operationType;
        $transaction->status = $status;
        $transaction->amount = $amount;
        $transaction->currency = $currency;

        return $transaction;
    }

    private function eventStore(
        ?PaymentEventData &$savedEvent = null,
        ?int &$employeeId = null,
    ): PreauthorizationEventStoreInterface {
        /** @var PreauthorizationEventStoreInterface&MockObject $eventStore */
        $eventStore = $this->createMock(PreauthorizationEventStoreInterface::class);
        $eventStore->method('savePreauthorizationOperation')->willReturnCallback(
            static function (
                PaymentAttempt $attempt,
                PaymentEventData $event,
                ?int $manualEmployeeId,
            ) use (
                &$savedEvent,
                &$employeeId
            ): void {
                $savedEvent = $event;
                $employeeId = $manualEmployeeId;
            }
        );

        return $eventStore;
    }

    /**
     * @param array<string, float|int|string>|null $capturedOrder
     */
    private function orderUpdater(
        ?array &$capturedOrder = null,
        ?int &$cancelledOrderId = null,
    ): PreauthorizationOrderUpdaterInterface {
        /** @var PreauthorizationOrderUpdaterInterface&MockObject $updater */
        $updater = $this->createMock(PreauthorizationOrderUpdaterInterface::class);
        $updater->method('markCaptured')->willReturnCallback(
            static function (
                int $orderId,
                float $amount,
                string $currencyIso,
                string $transactionId,
                int $employeeId,
            ) use (&$capturedOrder): void {
                $capturedOrder = [
                    'order_id' => $orderId,
                    'amount' => $amount,
                    'currency' => $currencyIso,
                    'transaction_id' => $transactionId,
                    'employee_id' => $employeeId,
                ];
            }
        );
        $updater->method('markCancelled')->willReturnCallback(
            static function (int $orderId) use (&$cancelledOrderId): void {
                $cancelledOrderId = $orderId;
            }
        );

        return $updater;
    }
}
