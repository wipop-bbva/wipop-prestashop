<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Preauthorization;

use PrestaShop\Module\Wipop\Configuration\Settings;
use PrestaShop\Module\Wipop\Log\LoggerInterface;
use PrestaShop\Module\Wipop\Payment\PaymentAmount;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptPreauthorizationMarkerInterface;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptSummary;
use PrestaShop\Module\Wipop\Payment\PaymentEventData;
use PrestaShop\Module\Wipop\Payment\PreauthorizationEventStoreInterface;
use Throwable;

final class PreauthorizationService
{
    public function __construct(
        private WipopPreauthorizationClientInterface $wipopClient,
        private PreauthorizationEventStoreInterface $eventStore,
        private PaymentAttemptPreauthorizationMarkerInterface $attemptMarker,
        private PreauthorizationOrderUpdaterInterface $orderUpdater,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws PreauthorizationException
     */
    public function capture(int $orderId, PaymentAttemptSummary $summary, int $employeeId): PreauthorizationOutcome
    {
        $authorizationTransactionId = $this->assertActionable($summary);
        $this->assertNoSuccessfulEvent(
            $summary,
            PaymentEventData::TYPE_CAPTURE,
            'This Wipop authorization has already been captured.'
        );
        $this->assertNoSuccessfulEvent(
            $summary,
            PaymentEventData::TYPE_REVERSAL,
            'This Wipop preauthorization has already been voided.'
        );

        $result = $this->captureInWipop($authorizationTransactionId, $summary->amount);
        $event = $this->eventFromResult(PaymentEventData::TYPE_CAPTURE, $summary, $result);
        /* Persist Wipop's response before local validation so inconsistent outcomes remain auditable. */
        $this->eventStore->savePreauthorizationOperation(
            $this->attemptFromSummary($orderId, $summary),
            $event,
            $employeeId
        );
        $this->assertSuccessfulResult(
            $summary,
            $result,
            PaymentEventData::TYPE_CAPTURE,
            'We could not capture the preauthorization in Wipop'
        );

        $warning = $this->finishCaptureLocally($orderId, $summary, $result, $employeeId);

        return new PreauthorizationOutcome(
            $result->amount,
            $result->currencyIso,
            $warning,
        );
    }

    /**
     * @throws PreauthorizationException
     */
    public function reverse(int $orderId, PaymentAttemptSummary $summary, int $employeeId): PreauthorizationOutcome
    {
        $authorizationTransactionId = $this->assertActionable($summary);
        $this->assertNoSuccessfulEvent(
            $summary,
            PaymentEventData::TYPE_CAPTURE,
            'Captured Wipop preauthorizations cannot be voided.'
        );
        $this->assertNoSuccessfulEvent(
            $summary,
            PaymentEventData::TYPE_REVERSAL,
            'This Wipop preauthorization has already been voided.'
        );

        $result = $this->reverseInWipop($authorizationTransactionId);
        $event = $this->eventFromResult(PaymentEventData::TYPE_REVERSAL, $summary, $result);
        /* Persist Wipop's response before local validation so inconsistent outcomes remain auditable. */
        $this->eventStore->savePreauthorizationOperation(
            $this->attemptFromSummary($orderId, $summary),
            $event,
            $employeeId
        );
        $this->assertSuccessfulResult(
            $summary,
            $result,
            PaymentEventData::TYPE_REVERSAL,
            'We could not void the preauthorization in Wipop.'
        );

        $warning = $this->finishReversalLocally($orderId, $summary);

        return new PreauthorizationOutcome(
            $result->amount,
            $result->currencyIso,
            $warning,
        );
    }

    /**
     * @throws PreauthorizationException
     */
    private function assertActionable(PaymentAttemptSummary $summary): string
    {
        if ($summary->status !== PaymentAttemptStatus::AUTHORIZED) {
            throw new PreauthorizationException('Only authorized Wipop payments can be captured or voided.');
        }

        if (!Settings::isCardMethod((string) $summary->paymentMethod)) {
            throw new PreauthorizationException('Only Wipop card preauthorizations can be captured or voided.');
        }

        $transactionId = trim((string) $summary->wipopTransactionId);
        if ($transactionId === '') {
            throw new PreauthorizationException('The original Wipop authorization is missing.');
        }

        if (PaymentAmount::toCents($summary->amount) <= 0) {
            throw new PreauthorizationException('The Wipop authorization amount must be greater than zero.');
        }

        if (trim($summary->currencyIso) === '') {
            throw new PreauthorizationException('The Wipop authorization currency is missing.');
        }

        return $transactionId;
    }

    /**
     * @throws PreauthorizationException
     */
    private function assertNoSuccessfulEvent(PaymentAttemptSummary $summary, string $eventType, string $message): void
    {
        if ($this->eventStore->hasSuccessfulPreauthorizationEvent($summary->id, $eventType)) {
            throw new PreauthorizationException($message);
        }
    }

    /**
     * @throws PreauthorizationException
     */
    private function captureInWipop(string $authorizationTransactionId, float $amount): PreauthorizationResult
    {
        try {
            return $this->wipopClient->capture($authorizationTransactionId, $amount);
        } catch (PreauthorizationException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new PreauthorizationException('We could not capture the preauthorization in Wipop', 0, $throwable);
        }
    }

    /**
     * @throws PreauthorizationException
     */
    private function reverseInWipop(string $authorizationTransactionId): PreauthorizationResult
    {
        try {
            return $this->wipopClient->reverse($authorizationTransactionId);
        } catch (PreauthorizationException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new PreauthorizationException('We could not void the preauthorization in Wipop.', 0, $throwable);
        }
    }

    /**
     * @throws PreauthorizationException
     */
    private function assertSuccessfulResult(
        PaymentAttemptSummary $summary,
        PreauthorizationResult $result,
        string $eventType,
        string $message,
    ): void {
        if (!PreauthorizationOperationStatus::isSuccessful($eventType, $result->status)) {
            throw new PreauthorizationException($message);
        }

        if (!PaymentAmount::matches($summary->amount, $result->amount)) {
            throw new PreauthorizationException('Wipop returned a different operation amount.');
        }

        if (strtoupper(trim($result->currencyIso)) !== strtoupper(trim($summary->currencyIso))) {
            throw new PreauthorizationException('Wipop returned a different operation currency.');
        }
    }

    private function finishCaptureLocally(
        int $orderId,
        PaymentAttemptSummary $summary,
        PreauthorizationResult $result,
        int $employeeId,
    ): ?string {
        $warning = null;

        /* Wipop already completed the remote operation; local failures become warnings so recovery stays visible. */
        try {
            $this->attemptMarker->markPaid($summary->id);
        } catch (Throwable $throwable) {
            $this->logger->error('Payment attempt update failed after Wipop capture.', [
                'order_id' => $orderId,
                'attempt_id' => $summary->id,
                'capture_transaction_id' => $result->remoteTransactionId,
                'error_class' => $throwable::class,
            ]);
            $warning = 'Wipop captured the authorization, but the local payment attempt could not be marked as paid.';
        }

        try {
            $this->orderUpdater->markCaptured(
                $orderId,
                $result->amount,
                $result->currencyIso,
                $result->remoteTransactionId,
                $employeeId,
            );
        } catch (Throwable $throwable) {
            $this->logger->error('Order update failed after Wipop capture.', [
                'order_id' => $orderId,
                'attempt_id' => $summary->id,
                'capture_transaction_id' => $result->remoteTransactionId,
                'error_class' => $throwable::class,
            ]);
            $warning = 'Wipop captured the authorization, but the PrestaShop order could not be marked as paid.';
        }

        return $warning;
    }

    private function finishReversalLocally(int $orderId, PaymentAttemptSummary $summary): ?string
    {
        $warning = null;

        /* Wipop already completed the remote operation; local failures become warnings so recovery stays visible. */
        try {
            $this->attemptMarker->markCancelled($summary->id);
        } catch (Throwable $throwable) {
            $this->logger->error('Payment attempt update failed after Wipop reversal.', [
                'order_id' => $orderId,
                'attempt_id' => $summary->id,
                'error_class' => $throwable::class,
            ]);
            $warning = 'Wipop voided the preauthorization, but the local attempt could not be marked as cancelled.';
        }

        try {
            $this->orderUpdater->markCancelled($orderId);
        } catch (Throwable $throwable) {
            $this->logger->error('Order update failed after Wipop reversal.', [
                'order_id' => $orderId,
                'attempt_id' => $summary->id,
                'error_class' => $throwable::class,
            ]);
            $warning = 'Wipop voided the preauthorization, but the PrestaShop order could not be marked as cancelled.';
        }

        return $warning;
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
            $summary->paymentMethod,
            wipopTransactionId: $summary->wipopTransactionId,
        );
    }

    private function eventFromResult(
        string $eventType,
        PaymentAttemptSummary $summary,
        PreauthorizationResult $result,
    ): PaymentEventData {
        return new PaymentEventData(
            $result->remoteTransactionId,
            $result->parentTransactionId,
            $result->wipopOrderId ?? $summary->wipopOrderId,
            $eventType,
            $result->operationType,
            $result->status,
            $result->amount,
            $result->currencyIso,
            $result->remoteErrorCode,
            $result->remoteErrorMessage,
        );
    }
}
