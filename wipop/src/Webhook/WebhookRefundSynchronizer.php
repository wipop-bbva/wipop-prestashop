<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Webhook;

use PrestaShop\Module\Wipop\Exception\WebhookException;
use PrestaShop\Module\Wipop\Payment\PaymentAmount;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptRepository;
use PrestaShop\Module\Wipop\Payment\PaymentEventData;
use PrestaShop\Module\Wipop\Payment\PaymentEventRepository;

final class WebhookRefundSynchronizer
{
    private const COMPLETED_STATUS = 'COMPLETED';

    public function __construct(
        private PaymentAttemptRepository $paymentAttemptRepository,
        private PaymentEventRepository $paymentEventRepository,
        private WebhookPaymentValidator $paymentValidator,
    ) {
    }

    /**
     * @throws WebhookException
     */
    public function sync(PaymentAttempt $attempt, PaymentEventData $refund): void
    {
        if ($this->isCompleted($refund)) {
            $this->assertCompletedRefundIsValid($attempt, $refund);
        }

        $this->paymentEventRepository->save($attempt, $refund);

        if (!$this->isCompleted($refund)) {
            return;
        }

        $refundedCents = $this->paymentEventRepository->completedRefundedCentsForAttempt(
            $attempt->id,
            $attempt->currencyIso
        );

        if ($refundedCents === PaymentAmount::toCents($attempt->amount)) {
            $this->paymentAttemptRepository->markRefunded($attempt->id);
        }
    }

    /**
     * @throws WebhookException
     */
    private function assertCompletedRefundIsValid(PaymentAttempt $attempt, PaymentEventData $refund): void
    {
        $this->paymentValidator->assertRefundMatches($attempt, $refund);

        $alreadyRefundedCents = $this->paymentEventRepository->completedRefundedCentsForAttempt(
            $attempt->id,
            $attempt->currencyIso,
            $refund->remoteTransactionId
        );
        $refundCents = PaymentAmount::toCents((float) $refund->amount);
        $paidCents = PaymentAmount::toCents($attempt->amount);

        if ($alreadyRefundedCents + $refundCents > $paidCents) {
            throw new WebhookException('Refund amount exceeds the local Wipop payment attempt.', 400);
        }
    }

    private function isCompleted(PaymentEventData $refund): bool
    {
        return strtoupper(trim((string) $refund->status)) === self::COMPLETED_STATUS;
    }
}
