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

    public function testItUsesWebhookStatusForNonRefundedAttempts(): void
    {
        self::assertSame(
            PaymentAttemptStatus::PAID,
            PaymentAttemptStatus::CHECKOUT_PENDING->syncWithWebhookStatus(PaymentAttemptStatus::PAID)
        );
    }
}
