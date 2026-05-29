<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Refund;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Log\LoggerInterface;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptRefundMarkerInterface;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptSummary;
use PrestaShop\Module\Wipop\Payment\PaymentEventData;
use PrestaShop\Module\Wipop\Payment\RefundEventStoreInterface;
use PrestaShop\Module\Wipop\Refund\ManualRefundException;
use PrestaShop\Module\Wipop\Refund\ManualRefundFormData;
use PrestaShop\Module\Wipop\Refund\ManualRefundFormLine;
use PrestaShop\Module\Wipop\Refund\ManualRefundLine;
use PrestaShop\Module\Wipop\Refund\ManualRefundRequest;
use PrestaShop\Module\Wipop\Refund\ManualRefundService;
use PrestaShop\Module\Wipop\Refund\ManualRefundValidator;
use PrestaShop\Module\Wipop\Refund\OrderSlipIssuerInterface;
use PrestaShop\Module\Wipop\Refund\WipopRefundClientInterface;
use PrestaShop\Module\Wipop\Refund\WipopRefundResult;
use PrestaShop\Module\Wipop\Refund\WipopRefundTaxIncludedAmountCalculator;
use RuntimeException;

final class ManualRefundServiceTest extends TestCase
{
    public function testItSavesWipopRefundAndCreatesOrderSlip(): void
    {
        $savedEvent = null;
        $employeeId = null;
        $linkedOrderSlips = [];
        $orderSlipCalls = [];
        $refunded = false;
        $eventStore = $this->eventStore(1000, $savedEvent, $employeeId, $linkedOrderSlips);
        $marker = $this->refundMarker($refunded);
        $orderSlipIssuer = $this->orderSlipIssuer(77, null, $orderSlipCalls);

        $outcome = $this->service(
            $this->wipopRefundClient($this->refundResult()),
            $orderSlipIssuer,
            $eventStore,
            $marker,
        )->refund(56, $this->summary(), $this->request(), $this->formData(), 7);

        self::assertSame('tx_refund', $outcome->refundTransactionId);
        self::assertSame(77, $outcome->orderSlipId);
        self::assertNull($outcome->localWarning);
        self::assertSame('tx_refund', $savedEvent?->remoteTransactionId);
        self::assertSame(7, $employeeId);
        self::assertSame(['tx_refund' => 77], $linkedOrderSlips);
        self::assertFalse($refunded);
        self::assertSame(['assert', 'issue'], $orderSlipCalls);
    }

    public function testItDoesNotCreateOrderSlipWhenWipopRefundFails(): void
    {
        $savedEvent = null;
        $employeeId = null;
        $linkedOrderSlips = [];
        $orderSlipCalls = [];
        $refunded = false;
        $eventStore = $this->eventStore(0, $savedEvent, $employeeId, $linkedOrderSlips);
        $orderSlipIssuer = $this->orderSlipIssuer(77, null, $orderSlipCalls);

        $this->expectException(ManualRefundException::class);

        try {
            $this->service(
                $this->wipopRefundClient(null, new RuntimeException('API down')),
                $orderSlipIssuer,
                $eventStore,
                $this->refundMarker($refunded),
            )->refund(56, $this->summary(), $this->request(), $this->formData(), 7);
        } finally {
            self::assertNull($savedEvent);
            self::assertSame(['assert'], $orderSlipCalls);
        }
    }

    public function testItKeepsWipopEventWhenOrderSlipCreationFails(): void
    {
        $savedEvent = null;
        $employeeId = null;
        $linkedOrderSlips = [];
        $orderSlipCalls = [];
        $refunded = false;
        $eventStore = $this->eventStore(4990, $savedEvent, $employeeId, $linkedOrderSlips);
        $marker = $this->refundMarker($refunded);
        $orderSlipIssuer = $this->orderSlipIssuer(null, new RuntimeException('Cannot create slip'), $orderSlipCalls);

        $outcome = $this->service(
            $this->wipopRefundClient($this->refundResult()),
            $orderSlipIssuer,
            $eventStore,
            $marker,
        )->refund(56, $this->summary(), $this->request(), $this->formData(), 7);

        self::assertSame('tx_refund', $savedEvent?->remoteTransactionId);
        self::assertNull($outcome->orderSlipId);
        self::assertNotNull($outcome->localWarning);
        self::assertTrue($refunded);
    }

    public function testItKeepsOrderSlipWhenRefundEventLinkingFails(): void
    {
        $savedEvent = null;
        $employeeId = null;
        $linkedOrderSlips = [];
        $orderSlipCalls = [];
        $refunded = false;
        $eventStore = $this->eventStore(
            1000,
            $savedEvent,
            $employeeId,
            $linkedOrderSlips,
            new RuntimeException('Cannot link slip')
        );
        $marker = $this->refundMarker($refunded);
        $orderSlipIssuer = $this->orderSlipIssuer(77, null, $orderSlipCalls);

        $outcome = $this->service(
            $this->wipopRefundClient($this->refundResult()),
            $orderSlipIssuer,
            $eventStore,
            $marker,
        )->refund(56, $this->summary(), $this->request(), $this->formData(), 7);

        self::assertSame('tx_refund', $savedEvent?->remoteTransactionId);
        self::assertSame(77, $outcome->orderSlipId);
        self::assertNotNull($outcome->localWarning);
        self::assertSame(['assert', 'issue'], $orderSlipCalls);
        self::assertSame([], $linkedOrderSlips);
        self::assertFalse($refunded);
    }

    public function testItRefundsTaxIncludedAmountThroughWipopWhenOrderUsesTaxExcludedAmounts(): void
    {
        $savedEvent = null;
        $employeeId = null;
        $linkedOrderSlips = [];
        $orderSlipCalls = [];
        $lastAmount = null;
        $refunded = false;
        $eventStore = $this->eventStore(0, $savedEvent, $employeeId, $linkedOrderSlips);
        $wipopRefundClient = $this->wipopRefundClient(
            $this->refundResult(amount: 12.1),
            null,
            $lastAmount,
        );

        $this->service(
            $wipopRefundClient,
            $this->orderSlipIssuer(77, null, $orderSlipCalls),
            $eventStore,
            $this->refundMarker($refunded),
        )->refund(
            56,
            $this->summary(),
            $this->request(),
            $this->taxExcludedFormData(),
            7,
        );

        self::assertSame(12.1, $lastAmount);
        self::assertSame(12.1, $savedEvent?->amount);
    }

    public function testItRejectsNonCompletedWipopRefundResultsBeforeCreatingOrderSlip(): void
    {
        $this->assertWipopResultIsRejectedBeforeLocalChanges(
            $this->refundResult(status: 'IN_PROGRESS'),
        );
    }

    public function testItRejectsWipopRefundResultsWithDifferentAmountBeforeCreatingOrderSlip(): void
    {
        $this->assertWipopResultIsRejectedBeforeLocalChanges(
            $this->refundResult(amount: 9.99),
        );
    }

    public function testItRejectsWipopRefundResultsWithDifferentCurrencyBeforeCreatingOrderSlip(): void
    {
        $this->assertWipopResultIsRejectedBeforeLocalChanges(
            $this->refundResult(currencyIso: 'USD'),
        );
    }

    private function service(
        WipopRefundClientInterface $wipopRefundClient,
        OrderSlipIssuerInterface $orderSlipIssuer,
        RefundEventStoreInterface $eventStore,
        PaymentAttemptRefundMarkerInterface $marker,
    ): ManualRefundService {
        $amountCalculator = new WipopRefundTaxIncludedAmountCalculator();

        return new ManualRefundService(
            new ManualRefundValidator($amountCalculator),
            $amountCalculator,
            $wipopRefundClient,
            $orderSlipIssuer,
            $eventStore,
            $marker,
            $this->logger(),
        );
    }

    private function summary(): PaymentAttemptSummary
    {
        return new PaymentAttemptSummary(
            id: 12,
            cartId: 34,
            paymentMethod: 'card',
            status: PaymentAttemptStatus::PAID,
            amount: 49.9,
            currencyIso: 'EUR',
            wipopOrderId: 'WP1234567890',
            wipopTransactionId: 'tx_charge',
            remoteStatus: 'COMPLETED',
            remoteErrorCode: null,
            remoteErrorMessage: null,
            webhookReceivedAt: '2026-05-19 10:11:12',
        );
    }

    private function request(): ManualRefundRequest
    {
        return new ManualRefundRequest(
            lines: [
                new ManualRefundLine(
                    orderDetailId: 90,
                    quantity: 1,
                    amount: 10.0,
                ),
            ],
            shippingAmount: 0.0,
            restock: false,
        );
    }

    private function formData(): ManualRefundFormData
    {
        return new ManualRefundFormData(
            lines: [
                new ManualRefundFormLine(
                    orderDetailId: 90,
                    name: 'Product',
                    reference: 'REF',
                    quantityRefundable: 2,
                    unitAmount: 15.0,
                    unitAmountTaxIncluded: 15.0,
                    amountRefundable: 30.0,
                    amountRefundableFormatted: '30.00 EUR',
                ),
            ],
            shippingRefundableAmount: 5.0,
            shippingRefundableAmountTaxIncluded: 5.0,
            shippingRefundableAmountFormatted: '5.00 EUR',
            currencyIso: 'EUR',
            taxMethod: 'tax incl.',
            isTaxIncluded: true,
        );
    }

    private function taxExcludedFormData(): ManualRefundFormData
    {
        return new ManualRefundFormData(
            lines: [
                new ManualRefundFormLine(
                    orderDetailId: 90,
                    name: 'Product',
                    reference: 'REF',
                    quantityRefundable: 2,
                    unitAmount: 10.0,
                    unitAmountTaxIncluded: 12.1,
                    amountRefundable: 20.0,
                    amountRefundableFormatted: '20.00 EUR',
                ),
            ],
            shippingRefundableAmount: 5.0,
            shippingRefundableAmountTaxIncluded: 6.05,
            shippingRefundableAmountFormatted: '5.00 EUR',
            currencyIso: 'EUR',
            taxMethod: 'tax excl.',
            isTaxIncluded: false,
        );
    }

    private function assertWipopResultIsRejectedBeforeLocalChanges(WipopRefundResult $refundResult): void
    {
        $savedEvent = null;
        $employeeId = null;
        $linkedOrderSlips = [];
        $orderSlipCalls = [];
        $refunded = false;
        $eventStore = $this->eventStore(0, $savedEvent, $employeeId, $linkedOrderSlips);
        $orderSlipIssuer = $this->orderSlipIssuer(77, null, $orderSlipCalls);

        $this->expectException(ManualRefundException::class);

        try {
            $this->service(
                $this->wipopRefundClient($refundResult),
                $orderSlipIssuer,
                $eventStore,
                $this->refundMarker($refunded),
            )->refund(56, $this->summary(), $this->request(), $this->formData(), 7);
        } finally {
            self::assertNull($savedEvent);
            self::assertSame(['assert'], $orderSlipCalls);
        }
    }

    private function refundResult(
        string $refundTransactionId = 'tx_refund',
        ?string $parentTransactionId = 'tx_charge',
        ?string $wipopOrderId = 'WP1234567890',
        string $status = 'COMPLETED',
        float $amount = 10.0,
        string $currencyIso = 'EUR',
        ?string $operationType = 'refund',
        ?string $remoteErrorCode = null,
        ?string $remoteErrorMessage = null,
    ): WipopRefundResult {
        return new WipopRefundResult(
            refundTransactionId: $refundTransactionId,
            parentTransactionId: $parentTransactionId,
            wipopOrderId: $wipopOrderId,
            status: $status,
            amount: $amount,
            currencyIso: $currencyIso,
            operationType: $operationType,
            remoteErrorCode: $remoteErrorCode,
            remoteErrorMessage: $remoteErrorMessage,
        );
    }

    private function refundResultFor(string $transactionId, float $amount): WipopRefundResult
    {
        return $this->refundResult(parentTransactionId: $transactionId, wipopOrderId: null, amount: $amount);
    }

    private function wipopRefundClient(
        ?WipopRefundResult $result = null,
        ?RuntimeException $exception = null,
        ?float &$lastAmount = null,
    ): WipopRefundClientInterface {
        /** @var WipopRefundClientInterface&MockObject $client */
        $client = $this->createMock(WipopRefundClientInterface::class);
        $client->method('refundCharge')->willReturnCallback(
            function (
                string $transactionId,
                float $amount,
            ) use (
                $result,
                $exception,
                &$lastAmount
            ): WipopRefundResult {
                $lastAmount = $amount;

                if ($exception instanceof RuntimeException) {
                    throw $exception;
                }

                return $result ?? $this->refundResultFor($transactionId, $amount);
            }
        );

        return $client;
    }

    /**
     * @param string[] $calls
     */
    private function orderSlipIssuer(
        ?int $orderSlipId,
        ?RuntimeException $exception,
        array &$calls,
    ): OrderSlipIssuerInterface {
        /** @var OrderSlipIssuerInterface&MockObject $issuer */
        $issuer = $this->createMock(OrderSlipIssuerInterface::class);
        $issuer->method('assertCanIssueOrderSlip')->willReturnCallback(
            static function () use (&$calls): void {
                $calls[] = 'assert';
            }
        );
        $issuer->method('issue')->willReturnCallback(
            static function () use ($orderSlipId, $exception, &$calls): int {
                $calls[] = 'issue';

                if ($exception instanceof RuntimeException) {
                    throw $exception;
                }

                return $orderSlipId ?? 0;
            }
        );

        return $issuer;
    }

    /**
     * @param array<string, int> $linkedOrderSlips
     */
    private function eventStore(
        int $refundedCents,
        ?PaymentEventData &$savedEvent,
        ?int &$employeeId,
        array &$linkedOrderSlips,
        ?RuntimeException $linkException = null,
    ): RefundEventStoreInterface {
        /** @var RefundEventStoreInterface&MockObject $eventStore */
        $eventStore = $this->createMock(RefundEventStoreInterface::class);
        $eventStore->method('saveManualRefund')->willReturnCallback(
            static function (
                PaymentAttempt $attempt,
                PaymentEventData $event,
                int $manualEmployeeId,
            ) use (
                &$savedEvent,
                &$employeeId
            ): void {
                $savedEvent = $event;
                $employeeId = $manualEmployeeId;
            }
        );
        $eventStore->method('linkOrderSlip')->willReturnCallback(
            static function (
                string $remoteTransactionId,
                int $orderSlipId,
            ) use (
                &$linkedOrderSlips,
                $linkException
            ): void {
                if ($linkException instanceof RuntimeException) {
                    throw $linkException;
                }

                $linkedOrderSlips[$remoteTransactionId] = $orderSlipId;
            }
        );
        $eventStore->method('completedRefundedCentsForAttempt')->willReturn($refundedCents);

        return $eventStore;
    }

    private function refundMarker(bool &$refunded): PaymentAttemptRefundMarkerInterface
    {
        /** @var PaymentAttemptRefundMarkerInterface&MockObject $marker */
        $marker = $this->createMock(PaymentAttemptRefundMarkerInterface::class);
        $marker->method('markRefunded')->willReturnCallback(
            static function () use (&$refunded): void {
                $refunded = true;
            }
        );

        return $marker;
    }

    private function logger(): LoggerInterface
    {
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        return $logger;
    }
}
