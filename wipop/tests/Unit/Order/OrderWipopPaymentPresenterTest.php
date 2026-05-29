<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Order;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Order\OrderWipopPaymentPresenter;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptSummary;
use PrestaShop\Module\Wipop\Payment\PaymentRefundSummary;

final class OrderWipopPaymentPresenterTest extends TestCase
{
    public function testItBuildsAPaidPaymentSummary(): void
    {
        $payment = (new OrderWipopPaymentPresenter())->present(
            $this->summary(currencyIso: 'eur', wipopTransactionId: 'tx_123')
        );

        self::assertSame('success', $payment['status_badge_class']);
        self::assertSame('Paid', $payment['status_label']);
        self::assertSame('49.90 EUR', $payment['amount']);
        self::assertSame('COMPLETED', $payment['remote_status']);
        self::assertSame('card', $payment['payment_method']);
        self::assertSame('WP1234567890', $payment['wipop_order_id']);
        self::assertSame('tx_123', $payment['wipop_transaction_id']);
        self::assertSame('2026-05-19 10:11:12', $payment['webhook_received_at']);
        self::assertFalse($payment['has_error']);
        self::assertSame('-', $payment['remote_error']);
        self::assertFalse($payment['has_refund']);
        self::assertTrue($payment['native_refunds_disabled']);
    }

    public function testItShowsRemoteErrorsOnlyWhenPresent(): void
    {
        $payment = (new OrderWipopPaymentPresenter())->present(
            $this->summary(
                paymentMethod: null,
                status: PaymentAttemptStatus::FAILED,
                amount: 10.0,
                wipopOrderId: null,
                wipopTransactionId: null,
                remoteStatus: null,
                remoteErrorCode: 'card_declined',
                remoteErrorMessage: 'Card was declined',
                webhookReceivedAt: null,
            )
        );

        self::assertSame('danger', $payment['status_badge_class']);
        self::assertSame('-', $payment['payment_method']);
        self::assertSame('-', $payment['wipop_transaction_id']);
        self::assertTrue($payment['has_error']);
        self::assertSame('card_declined - Card was declined', $payment['remote_error']);
        self::assertFalse($payment['native_refunds_disabled']);
    }

    public function testItDisablesNativeRefundsForAuthorizedAndRefundedWipopPayments(): void
    {
        $presenter = new OrderWipopPaymentPresenter();

        $authorizedPayment = $presenter->present($this->summary(status: PaymentAttemptStatus::AUTHORIZED));
        $refundedPayment = $presenter->present($this->summary(status: PaymentAttemptStatus::REFUNDED));

        self::assertTrue($authorizedPayment['native_refunds_disabled']);
        self::assertTrue($refundedPayment['native_refunds_disabled']);
    }

    public function testItBuildsARefundSummary(): void
    {
        $payment = (new OrderWipopPaymentPresenter())->present(
            $this->summary(refundSummary: $this->refundSummary())
        );

        self::assertTrue($payment['has_refund']);
        self::assertSame('Partial refund', $payment['refund_status']);
        self::assertSame('10.00 EUR', $payment['refunded_amount']);
        self::assertSame('tx_refund', $payment['last_refund_transaction_id']);
        self::assertSame('10.00 EUR', $payment['last_refund_amount']);
        self::assertSame('COMPLETED', $payment['last_refund_status']);
        self::assertSame('2026-05-20 09:10:11', $payment['last_refund_received_at']);
        self::assertTrue($payment['has_refund_order_slip']);
        self::assertSame('77', $payment['last_refund_order_slip_id']);
        self::assertFalse($payment['has_refund_error']);
    }

    private function summary(
        ?string $paymentMethod = 'card',
        PaymentAttemptStatus $status = PaymentAttemptStatus::PAID,
        float $amount = 49.9,
        string $currencyIso = 'EUR',
        ?string $wipopOrderId = 'WP1234567890',
        ?string $wipopTransactionId = 'tx_charge',
        ?string $remoteStatus = 'COMPLETED',
        ?string $remoteErrorCode = null,
        ?string $remoteErrorMessage = null,
        ?string $webhookReceivedAt = '2026-05-19 10:11:12',
        ?PaymentRefundSummary $refundSummary = null,
    ): PaymentAttemptSummary {
        return new PaymentAttemptSummary(
            id: 12,
            cartId: 34,
            paymentMethod: $paymentMethod,
            status: $status,
            amount: $amount,
            currencyIso: $currencyIso,
            wipopOrderId: $wipopOrderId,
            wipopTransactionId: $wipopTransactionId,
            remoteStatus: $remoteStatus,
            remoteErrorCode: $remoteErrorCode,
            remoteErrorMessage: $remoteErrorMessage,
            webhookReceivedAt: $webhookReceivedAt,
            refundSummary: $refundSummary,
        );
    }

    private function refundSummary(): PaymentRefundSummary
    {
        return new PaymentRefundSummary(
            refundedAmount: 10.0,
            currencyIso: 'EUR',
            completedRefundCount: 1,
            lastRefundTransactionId: 'tx_refund',
            lastRefundStatus: 'COMPLETED',
            lastRefundAmount: 10.0,
            lastRefundCurrencyIso: 'EUR',
            lastRefundReceivedAt: '2026-05-20 09:10:11',
            lastRefundErrorCode: null,
            lastRefundErrorMessage: null,
            lastRefundOrderSlipId: 77,
        );
    }
}
