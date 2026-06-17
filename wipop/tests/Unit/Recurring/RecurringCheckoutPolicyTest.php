<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Recurring;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Configuration\CaptureMode;
use PrestaShop\Module\Wipop\Recurring\RecurringCartGroup;
use PrestaShop\Module\Wipop\Recurring\RecurringCartSummary;
use PrestaShop\Module\Wipop\Recurring\RecurringCheckoutPolicy;
use PrestaShop\Module\Wipop\Recurring\RecurringPeriod;
use Wipop\Domain\ChargeMethod;

final class RecurringCheckoutPolicyTest extends TestCase
{
    public function testItLeavesNonRecurringCheckoutUnchanged(): void
    {
        $policy = new RecurringCheckoutPolicy();
        $summary = new RecurringCartSummary([], false);

        self::assertSame(
            [ChargeMethod::CARD, ChargeMethod::BIZUM],
            $policy->availableGateways($summary, [ChargeMethod::CARD, ChargeMethod::BIZUM])
        );
        self::assertNull($policy->startError($summary, ChargeMethod::BIZUM));
        self::assertNull($policy->captureModeOverride($summary));
    }

    public function testItRestrictsRecurringCheckoutToVirtualCardPayments(): void
    {
        $policy = new RecurringCheckoutPolicy();
        $summary = $this->summary(isVirtualCart: true);

        self::assertSame([ChargeMethod::CARD], $policy->availableGateways($summary, [
            ChargeMethod::CARD,
            ChargeMethod::BIZUM,
        ]));
        self::assertSame(
            'Recurring products must be paid by card.',
            $policy->startError($summary, ChargeMethod::BIZUM)
        );
        self::assertNull($policy->startError($summary, ChargeMethod::CARD));
        self::assertTrue($policy->requiresReusableCard($summary));
        self::assertSame(CaptureMode::IMMEDIATE, $policy->captureModeOverride($summary));
    }

    public function testItBlocksPhysicalRecurringCheckout(): void
    {
        $policy = new RecurringCheckoutPolicy();
        $summary = $this->summary(isVirtualCart: false);

        self::assertSame([], $policy->availableGateways($summary, [ChargeMethod::CARD]));
        self::assertSame(
            'Recurring payments are available only for virtual products.',
            $policy->startError($summary, ChargeMethod::CARD)
        );
    }

    public function testItBlocksRecurringCheckoutWithMixedPeriods(): void
    {
        $policy = new RecurringCheckoutPolicy();
        $summary = new RecurringCartSummary([
            new RecurringCartGroup(RecurringPeriod::MONTHLY, 10.0, []),
            new RecurringCartGroup(RecurringPeriod::YEARLY, 20.0, []),
        ], true);

        self::assertSame([], $policy->availableGateways($summary, [ChargeMethod::CARD]));
        self::assertSame(
            'Recurring products in the same cart must use the same period.',
            $policy->startError($summary, ChargeMethod::CARD)
        );
    }

    private function summary(bool $isVirtualCart): RecurringCartSummary
    {
        return new RecurringCartSummary([
            new RecurringCartGroup(RecurringPeriod::MONTHLY, 10.0, []),
        ], $isVirtualCart);
    }
}
