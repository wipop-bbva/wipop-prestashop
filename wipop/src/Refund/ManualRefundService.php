<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Refund;

use PrestaShop\Module\Wipop\Log\LoggerInterface;
use PrestaShop\Module\Wipop\Payment\PaymentAmount;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptRefundMarkerInterface;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptSummary;
use PrestaShop\Module\Wipop\Payment\PaymentEventData;
use PrestaShop\Module\Wipop\Payment\RefundEventStoreInterface;
use Throwable;

final class ManualRefundService
{
    public function __construct(
        private ManualRefundValidator $validator,
        private WipopRefundTaxIncludedAmountCalculator $taxIncludedAmountCalculator,
        private WipopRefundClientInterface $wipopRefundClient,
        private OrderSlipIssuerInterface $orderSlipIssuer,
        private RefundEventStoreInterface $refundEventStore,
        private PaymentAttemptRefundMarkerInterface $paymentAttemptRefundMarker,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws ManualRefundException
     */
    public function refund(
        int $orderId,
        PaymentAttemptSummary $summary,
        ManualRefundRequest $request,
        ManualRefundFormData $formData,
        int $employeeId,
    ): ManualRefundOutcome {
        $this->validator->assertRefundable($summary, $request, $formData);
        $this->orderSlipIssuer->assertCanIssueOrderSlip($orderId, $request);

        $chargeTransactionId = trim((string) $summary->wipopTransactionId);
        $wipopAmount = $this->taxIncludedAmountCalculator->amountForWipop($request, $formData);
        $refundResult = $this->refundInWipop($chargeTransactionId, $wipopAmount);
        $this->assertCompletedRefundResult($summary, $wipopAmount, $refundResult);

        $attempt = $this->attemptFromSummary($orderId, $summary);
        $event = $this->eventFromRefundResult($summary, $refundResult);

        $this->refundEventStore->saveManualRefund($attempt, $event, $employeeId);
        $this->markAttemptRefundedIfFullyRefunded($summary);

        return $this->issueOrderSlip($orderId, $request, $refundResult, $event);
    }

    /**
     * @throws ManualRefundException
     */
    private function refundInWipop(string $chargeTransactionId, float $amount): WipopRefundResult
    {
        try {
            return $this->wipopRefundClient->refundCharge($chargeTransactionId, $amount);
        } catch (ManualRefundException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new ManualRefundException('Unable to complete the Wipop refund.', 0, $throwable);
        }
    }

    /**
     * @throws ManualRefundException
     */
    private function assertCompletedRefundResult(
        PaymentAttemptSummary $summary,
        float $wipopAmount,
        WipopRefundResult $refundResult,
    ): void {
        if (strtoupper(trim($refundResult->status)) !== 'COMPLETED') {
            throw new ManualRefundException('Wipop did not confirm the refund.');
        }

        if (!PaymentAmount::matches($wipopAmount, $refundResult->amount)) {
            throw new ManualRefundException('Wipop returned a different refund amount.');
        }

        if (strtoupper(trim($refundResult->currencyIso)) !== strtoupper(trim($summary->currencyIso))) {
            throw new ManualRefundException('Wipop returned a different refund currency.');
        }
    }

    private function attemptFromSummary(int $orderId, PaymentAttemptSummary $summary): PaymentAttempt
    {
        return new PaymentAttempt(
            $summary->id,
            $summary->cartId,
            $orderId,
            $summary->status,
            $summary->amount,
            $summary->currencyIso,
        );
    }

    private function eventFromRefundResult(
        PaymentAttemptSummary $summary,
        WipopRefundResult $refundResult,
    ): PaymentEventData {
        return new PaymentEventData(
            $refundResult->refundTransactionId,
            $refundResult->parentTransactionId ?? $summary->wipopTransactionId,
            $refundResult->wipopOrderId ?? $summary->wipopOrderId,
            PaymentEventData::TYPE_REFUND,
            $refundResult->operationType ?? 'refund',
            $refundResult->status,
            $refundResult->amount,
            $refundResult->currencyIso,
            $refundResult->remoteErrorCode,
            $refundResult->remoteErrorMessage,
        );
    }

    private function markAttemptRefundedIfFullyRefunded(PaymentAttemptSummary $summary): void
    {
        $refundedCents = $this->refundEventStore->completedRefundedCentsForAttempt(
            $summary->id,
            $summary->currencyIso,
        );

        if ($refundedCents === PaymentAmount::toCents($summary->amount)) {
            $this->paymentAttemptRefundMarker->markRefunded($summary->id);
        }
    }

    private function issueOrderSlip(
        int $orderId,
        ManualRefundRequest $request,
        WipopRefundResult $refundResult,
        PaymentEventData $event,
    ): ManualRefundOutcome {
        try {
            $orderSlipId = $this->orderSlipIssuer->issue($orderId, $request);
        } catch (Throwable $throwable) {
            $this->logger->error('Credit slip creation failed after Wipop refund.', [
                'order_id' => $orderId,
                'refund_transaction_id' => $refundResult->refundTransactionId,
                'error_class' => $throwable::class,
            ]);

            return new ManualRefundOutcome(
                $refundResult->refundTransactionId,
                $refundResult->amount,
                $refundResult->currencyIso,
                null,
                'Wipop refunded the payment, but the credit slip could not be created.',
            );
        }

        try {
            $this->refundEventStore->linkOrderSlip($event->remoteTransactionId, $orderSlipId);
        } catch (Throwable $throwable) {
            $this->logger->error('Refund event link failed after credit slip creation.', [
                'order_id' => $orderId,
                'refund_transaction_id' => $refundResult->refundTransactionId,
                'order_slip_id' => $orderSlipId,
                'error_class' => $throwable::class,
            ]);

            return new ManualRefundOutcome(
                $refundResult->refundTransactionId,
                $refundResult->amount,
                $refundResult->currencyIso,
                $orderSlipId,
                'The refund and credit slip were completed, but the event could not be linked.',
            );
        }

        return new ManualRefundOutcome(
            $refundResult->refundTransactionId,
            $refundResult->amount,
            $refundResult->currencyIso,
            $orderSlipId,
            null,
        );
    }
}
