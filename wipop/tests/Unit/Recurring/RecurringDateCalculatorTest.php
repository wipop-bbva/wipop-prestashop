<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Recurring;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Recurring\RecurringDateCalculator;
use PrestaShop\Module\Wipop\Recurring\RecurringPeriod;

final class RecurringDateCalculatorTest extends TestCase
{
    public function testItCalculatesMonthlyRenewalDate(): void
    {
        $next = (new RecurringDateCalculator())->next(
            new DateTimeImmutable('2026-06-10 09:30:00'),
            RecurringPeriod::MONTHLY,
        );

        self::assertSame('2026-07-10 09:30:00', $next->format('Y-m-d H:i:s'));
    }

    public function testItClampsMonthlyRenewalDateToTheTargetMonth(): void
    {
        $next = (new RecurringDateCalculator())->next(
            new DateTimeImmutable('2026-01-31 09:30:00'),
            RecurringPeriod::MONTHLY,
        );

        self::assertSame('2026-02-28 09:30:00', $next->format('Y-m-d H:i:s'));
    }

    public function testItCalculatesYearlyRenewalDate(): void
    {
        $next = (new RecurringDateCalculator())->next(
            new DateTimeImmutable('2026-06-10 09:30:00'),
            RecurringPeriod::YEARLY,
        );

        self::assertSame('2027-06-10 09:30:00', $next->format('Y-m-d H:i:s'));
    }

    public function testItClampsYearlyRenewalDateToTheTargetMonth(): void
    {
        $next = (new RecurringDateCalculator())->next(
            new DateTimeImmutable('2024-02-29 09:30:00'),
            RecurringPeriod::YEARLY,
        );

        self::assertSame('2025-02-28 09:30:00', $next->format('Y-m-d H:i:s'));
    }
}
