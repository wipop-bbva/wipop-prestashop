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
            new PaymentAttemptSummary(
                12,
                34,
                'card',
                PaymentAttemptStatus::PAID,
                49.9,
                'eur',
                'WP1234567890',
                'tx_123',
                'COMPLETED',
                null,
                null,
                '2026-05-19 10:11:12',
            )
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
    }

    public function testItShowsRemoteErrorsOnlyWhenPresent(): void
    {
        $payment = (new OrderWipopPaymentPresenter())->present(
            new PaymentAttemptSummary(
                12,
                34,
                null,
                PaymentAttemptStatus::FAILED,
                10.0,
                'EUR',
                null,
                null,
                null,
                'card_declined',
                'Card was declined',
                null,
            )
        );

        self::assertSame('danger', $payment['status_badge_class']);
        self::assertSame('-', $payment['payment_method']);
        self::assertSame('-', $payment['wipop_transaction_id']);
        self::assertTrue($payment['has_error']);
        self::assertSame('card_declined - Card was declined', $payment['remote_error']);
    }

    public function testItBuildsARefundSummary(): void
    {
        $payment = (new OrderWipopPaymentPresenter())->present(
            new PaymentAttemptSummary(
                12,
                34,
                'card',
                PaymentAttemptStatus::PAID,
                49.9,
                'EUR',
                'WP1234567890',
                'tx_charge',
                'COMPLETED',
                null,
                null,
                '2026-05-19 10:11:12',
                new PaymentRefundSummary(
                    10.0,
                    'EUR',
                    1,
                    'tx_refund',
                    'COMPLETED',
                    10.0,
                    'EUR',
                    '2026-05-20 09:10:11',
                    null,
                    null,
                ),
            )
        );

        self::assertTrue($payment['has_refund']);
        self::assertSame('Partial refund', $payment['refund_status']);
        self::assertSame('10.00 EUR', $payment['refunded_amount']);
        self::assertSame('tx_refund', $payment['last_refund_transaction_id']);
        self::assertSame('10.00 EUR', $payment['last_refund_amount']);
        self::assertSame('COMPLETED', $payment['last_refund_status']);
        self::assertSame('2026-05-20 09:10:11', $payment['last_refund_received_at']);
        self::assertFalse($payment['has_refund_error']);
    }
}
