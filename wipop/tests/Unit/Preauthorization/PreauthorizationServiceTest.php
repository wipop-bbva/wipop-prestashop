<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Preauthorization;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Log\LoggerInterface;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptPreauthorizationMarkerInterface;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptSummary;
use PrestaShop\Module\Wipop\Payment\PaymentEventData;
use PrestaShop\Module\Wipop\Payment\PreauthorizationEventStoreInterface;
use PrestaShop\Module\Wipop\Preauthorization\PreauthorizationException;
use PrestaShop\Module\Wipop\Preauthorization\PreauthorizationOrderUpdaterInterface;
use PrestaShop\Module\Wipop\Preauthorization\PreauthorizationResult;
use PrestaShop\Module\Wipop\Preauthorization\PreauthorizationService;
use PrestaShop\Module\Wipop\Preauthorization\WipopPreauthorizationClientInterface;
use Wipop\Domain\TransactionStatus;

final class PreauthorizationServiceTest extends TestCase
{
    public function testItCapturesAuthorizedWipopPaymentAndKeepsOriginalAuthorizationId(): void
    {
        $savedEvent = null;
        $employeeId = null;
        $paidAttemptId = null;
        $capturedOrder = [];
        $client = $this->client(captureResult: $this->preauthorizationResult(remoteTransactionId: 'tx_capture'));

        $outcome = $this->service(
            $client,
            $this->eventStore($savedEvent, $employeeId),
            $this->marker($paidAttemptId),
            $this->orderUpdater($capturedOrder),
        )->capture(56, $this->summary(), 7);

        self::assertNull($outcome->localWarning);
        self::assertSame(PaymentEventData::TYPE_CAPTURE, $savedEvent?->transactionType);
        self::assertSame('tx_capture', $savedEvent?->remoteTransactionId);
        self::assertSame('tx_auth', $savedEvent?->parentTransactionId);
        self::assertSame(7, $employeeId);
        self::assertSame(12, $paidAttemptId);
        self::assertSame([
            'order_id' => 56,
            'amount' => 49.9,
            'currency' => 'EUR',
            'transaction_id' => 'tx_capture',
            'employee_id' => 7,
        ], $capturedOrder);
    }

    public function testItCancelsAuthorizedWipopPayment(): void
    {
        $savedEvent = null;
        $employeeId = null;
        $cancelledAttemptId = null;
        $cancelledOrderId = null;

        $outcome = $this->service(
            $this->client(reverseResult: $this->preauthorizationResult(
                remoteTransactionId: 'tx_reversal',
                status: TransactionStatus::CANCELLED->value
            )),
            $this->eventStore($savedEvent, $employeeId),
            $this->marker(cancelledAttemptId: $cancelledAttemptId),
            $this->orderUpdater(cancelledOrderId: $cancelledOrderId),
        )->reverse(56, $this->summary(), 7);

        self::assertSame(PaymentEventData::TYPE_REVERSAL, $savedEvent?->transactionType);
        self::assertSame(TransactionStatus::CANCELLED->value, $savedEvent?->status);
        self::assertSame('tx_auth', $savedEvent?->parentTransactionId);
        self::assertSame(12, $cancelledAttemptId);
        self::assertSame(56, $cancelledOrderId);
    }

    public function testItPersistsNonCompletedReversalBeforeRejectingIt(): void
    {
        $savedEvent = null;
        $cancelledAttemptId = null;
        $cancelledOrderId = null;

        $this->expectException(PreauthorizationException::class);

        try {
            $this->service(
                $this->client(reverseResult: $this->preauthorizationResult(
                    remoteTransactionId: 'tx_reversal_failed',
                    status: 'FAILED'
                )),
                $this->eventStore($savedEvent),
                $this->marker(cancelledAttemptId: $cancelledAttemptId),
                $this->orderUpdater(cancelledOrderId: $cancelledOrderId),
            )->reverse(56, $this->summary(), 7);
        } finally {
            self::assertSame(PaymentEventData::TYPE_REVERSAL, $savedEvent?->transactionType);
            self::assertSame('FAILED', $savedEvent?->status);
            self::assertNull($cancelledAttemptId);
            self::assertNull($cancelledOrderId);
        }
    }

    public function testItRejectsNonAuthorizedAttemptsBeforeCallingWipop(): void
    {
        $called = false;

        $this->expectException(PreauthorizationException::class);

        try {
            $this->service(
                $this->client(called: $called),
                $this->eventStore(),
                $this->marker(),
                $this->orderUpdater(),
            )->capture(56, $this->summary(status: PaymentAttemptStatus::PAID), 7);
        } finally {
            self::assertFalse($called);
        }
    }

    public function testItRejectsDuplicateCompletedCaptureBeforeCallingWipop(): void
    {
        $called = false;

        $this->expectException(PreauthorizationException::class);

        try {
            $this->service(
                $this->client(called: $called),
                $this->eventStore(successfulEvents: [PaymentEventData::TYPE_CAPTURE]),
                $this->marker(),
                $this->orderUpdater(),
            )->capture(56, $this->summary(), 7);
        } finally {
            self::assertFalse($called);
        }
    }

    public function testItPersistsNonCompletedRemoteResultBeforeRejectingIt(): void
    {
        $savedEvent = null;
        $paidAttemptId = null;

        $this->expectException(PreauthorizationException::class);

        try {
            $this->service(
                $this->client(captureResult: $this->preauthorizationResult(status: 'FAILED')),
                $this->eventStore($savedEvent),
                $this->marker($paidAttemptId),
                $this->orderUpdater(),
            )->capture(56, $this->summary(), 7);
        } finally {
            self::assertSame('FAILED', $savedEvent?->status);
            self::assertNull($paidAttemptId);
        }
    }

    private function service(
        WipopPreauthorizationClientInterface $client,
        PreauthorizationEventStoreInterface $eventStore,
        PaymentAttemptPreauthorizationMarkerInterface $marker,
        PreauthorizationOrderUpdaterInterface $orderUpdater,
    ): PreauthorizationService {
        return new PreauthorizationService(
            $client,
            $eventStore,
            $marker,
            $orderUpdater,
            $this->logger(),
        );
    }

    private function summary(PaymentAttemptStatus $status = PaymentAttemptStatus::AUTHORIZED): PaymentAttemptSummary
    {
        return new PaymentAttemptSummary(
            id: 12,
            cartId: 34,
            paymentMethod: 'CARD',
            status: $status,
            amount: 49.9,
            currencyIso: 'EUR',
            wipopOrderId: 'WP1234567890',
            wipopTransactionId: 'tx_auth',
            remoteStatus: 'IN_PROGRESS',
            remoteErrorCode: null,
            remoteErrorMessage: null,
            webhookReceivedAt: '2026-05-31 10:11:12',
        );
    }

    private function preauthorizationResult(
        string $remoteTransactionId = 'tx_operation',
        string $status = 'COMPLETED',
    ): PreauthorizationResult {
        return new PreauthorizationResult(
            remoteTransactionId: $remoteTransactionId,
            parentTransactionId: 'tx_auth',
            wipopOrderId: 'WP1234567890',
            status: $status,
            amount: 49.9,
            currencyIso: 'EUR',
            operationType: null,
            remoteErrorCode: null,
            remoteErrorMessage: null,
        );
    }

    private function client(
        ?PreauthorizationResult $captureResult = null,
        ?PreauthorizationResult $reverseResult = null,
        ?bool &$called = null,
    ): WipopPreauthorizationClientInterface {
        /** @var WipopPreauthorizationClientInterface&MockObject $client */
        $client = $this->createMock(WipopPreauthorizationClientInterface::class);
        $client->method('capture')->willReturnCallback(
            function () use ($captureResult, &$called): PreauthorizationResult {
                $called = true;

                return $captureResult ?? $this->preauthorizationResult(remoteTransactionId: 'tx_capture');
            }
        );
        $client->method('reverse')->willReturnCallback(
            function () use ($reverseResult, &$called): PreauthorizationResult {
                $called = true;

                return $reverseResult ?? $this->preauthorizationResult(remoteTransactionId: 'tx_reversal');
            }
        );

        return $client;
    }

    /**
     * @param string[] $successfulEvents
     */
    private function eventStore(
        ?PaymentEventData &$savedEvent = null,
        ?int &$employeeId = null,
        array $successfulEvents = [],
    ): PreauthorizationEventStoreInterface {
        /** @var PreauthorizationEventStoreInterface&MockObject $eventStore */
        $eventStore = $this->createMock(PreauthorizationEventStoreInterface::class);
        $eventStore->method('hasSuccessfulPreauthorizationEvent')->willReturnCallback(
            static fn (int $attemptId, string $eventType): bool => in_array($eventType, $successfulEvents, true)
        );
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

    private function marker(
        ?int &$paidAttemptId = null,
        ?int &$cancelledAttemptId = null,
    ): PaymentAttemptPreauthorizationMarkerInterface {
        /** @var PaymentAttemptPreauthorizationMarkerInterface&MockObject $marker */
        $marker = $this->createMock(PaymentAttemptPreauthorizationMarkerInterface::class);
        $marker->method('markPaid')->willReturnCallback(
            static function (int $attemptId) use (&$paidAttemptId): void {
                $paidAttemptId = $attemptId;
            }
        );
        $marker->method('markCancelled')->willReturnCallback(
            static function (int $attemptId) use (&$cancelledAttemptId): void {
                $cancelledAttemptId = $attemptId;
            }
        );

        return $marker;
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
            static function (
                int $orderId,
                ?string $transactionId = null,
                ?int $employeeId = null,
            ) use (&$cancelledOrderId): void {
                $cancelledOrderId = $orderId;
            }
        );

        return $updater;
    }

    private function logger(): LoggerInterface
    {
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        return $logger;
    }
}
