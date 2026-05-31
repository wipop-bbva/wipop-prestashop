<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;

final class PaymentAttemptStatusTest extends TestCase
{
    public function testItPreservesRefundedStatusAcrossWebhookRetries(): void
    {
        self::assertSame(
            PaymentAttemptStatus::REFUNDED,
            PaymentAttemptStatus::REFUNDED->syncWithWebhookStatus(PaymentAttemptStatus::PAID)
        );
    }

    public function testItPreservesTerminalStatusesAcrossLateWebhookRetries(): void
    {
        self::assertSame(
            PaymentAttemptStatus::PAID,
            PaymentAttemptStatus::PAID->syncWithWebhookStatus(PaymentAttemptStatus::CHECKOUT_PENDING)
        );
        self::assertSame(
            PaymentAttemptStatus::CANCELLED,
            PaymentAttemptStatus::CANCELLED->syncWithWebhookStatus(PaymentAttemptStatus::PAID)
        );
        self::assertSame(
            PaymentAttemptStatus::FAILED,
            PaymentAttemptStatus::FAILED->syncWithWebhookStatus(PaymentAttemptStatus::PAID)
        );
    }

    public function testItDoesNotDowngradeAuthorizedAttemptsToPendingOrFailed(): void
    {
        self::assertSame(
            PaymentAttemptStatus::AUTHORIZED,
            PaymentAttemptStatus::AUTHORIZED->syncWithWebhookStatus(PaymentAttemptStatus::CHECKOUT_PENDING)
        );
        self::assertSame(
            PaymentAttemptStatus::AUTHORIZED,
            PaymentAttemptStatus::AUTHORIZED->syncWithWebhookStatus(PaymentAttemptStatus::FAILED)
        );
    }

    public function testItUsesWebhookStatusForNonRefundedAttempts(): void
    {
        self::assertSame(
            PaymentAttemptStatus::PAID,
            PaymentAttemptStatus::CHECKOUT_PENDING->syncWithWebhookStatus(PaymentAttemptStatus::PAID)
        );
    }
}
