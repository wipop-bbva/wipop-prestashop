<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Recurring;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use PrestaShop\Module\Wipop\Recurring\RecurringSubscriptionStopPolicy;

final class RecurringSubscriptionStopPolicyTest extends TestCase
{
    public function testItStopsForClosedInitialPaymentStatuses(): void
    {
        $policy = new RecurringSubscriptionStopPolicy();

        self::assertTrue($policy->shouldStopForInitialAttemptStatus(PaymentAttemptStatus::FAILED));
        self::assertTrue($policy->shouldStopForInitialAttemptStatus(PaymentAttemptStatus::CANCELLED));
        self::assertTrue($policy->shouldStopForInitialAttemptStatus(PaymentAttemptStatus::REFUNDED));
        self::assertFalse($policy->shouldStopForInitialAttemptStatus(PaymentAttemptStatus::PAID));
        self::assertFalse($policy->shouldStopForInitialAttemptStatus(PaymentAttemptStatus::AUTHORIZED));
    }
}
