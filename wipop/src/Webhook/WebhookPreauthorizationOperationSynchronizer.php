<?php
/**
 * 2007-2026 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2026 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Webhook;

use PrestaShop\Module\Wipop\Configuration\CaptureMode;
use PrestaShop\Module\Wipop\Configuration\Settings;
use PrestaShop\Module\Wipop\Exception\WebhookException;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use PrestaShop\Module\Wipop\Payment\PaymentEventData;
use PrestaShop\Module\Wipop\Payment\PreauthorizationEventStoreInterface;
use PrestaShop\Module\Wipop\Preauthorization\PreauthorizationOperationStatus;
use PrestaShop\Module\Wipop\Preauthorization\PreauthorizationOrderUpdaterInterface;
use Wipop\Domain\Transaction;

if (!defined('_PS_VERSION_')) {
    exit;
}

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
                $this->currencyIso($transaction) ?? strtoupper(trim($attempt->currencyIso)),
                $transactionId,
                self::WEBHOOK_EMPLOYEE_ID
            );
        } else {
            $this->orderUpdater->markCancelled($orderId, $transactionId, self::WEBHOOK_EMPLOYEE_ID);
        }

        return $targetStatus;
    }

    private function targetStatus(
        PaymentAttempt $attempt,
        Transaction $transaction,
        string $eventType,
    ): ?PaymentAttemptStatus {
        $remoteStatus = $transaction->status?->value;
        if (!PreauthorizationOperationStatus::isSuccessful($eventType, $remoteStatus)) {
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

        return $this->eventTypeFromOperation($transaction)
            ?? $this->eventTypeFromOriginalAuthorizationStatus($attempt, $transaction);
    }

    private function isManualCardAttempt(PaymentAttempt $attempt): bool
    {
        return Settings::isCardMethod((string) $attempt->paymentMethod)
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

    private function eventTypeFromOriginalAuthorizationStatus(
        PaymentAttempt $attempt,
        Transaction $transaction,
    ): ?string {
        if (!$this->sameTransaction($attempt, $transaction)) {
            return null;
        }

        $remoteStatus = $transaction->status?->value;

        if (
            PreauthorizationOperationStatus::isCancelled($remoteStatus)
            && in_array($attempt->status, [PaymentAttemptStatus::AUTHORIZED, PaymentAttemptStatus::CANCELLED], true)
        ) {
            return PaymentEventData::TYPE_REVERSAL;
        }

        return null;
    }

    private function sameTransaction(PaymentAttempt $attempt, Transaction $transaction): bool
    {
        $attemptTransactionId = trim((string) $attempt->wipopTransactionId);
        $transactionId = trim((string) $transaction->id);

        return $attemptTransactionId !== '' && $attemptTransactionId === $transactionId;
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
