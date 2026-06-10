<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

use PrestaShop\Module\Wipop\Configuration\CaptureMode;
use PrestaShop\Module\Wipop\Configuration\Settings;
use Wipop\Domain\ChargeMethod;

final class RecurringCheckoutPolicy
{
    /**
     * @param string[] $gateways
     *
     * @return string[]
     */
    public function availableGateways(RecurringCartSummary $summary, array $gateways): array
    {
        if (!$summary->hasRecurringProducts()) {
            return array_values($gateways);
        }

        if (!$summary->isVirtualCart || $this->hasMixedPeriods($summary)) {
            return [];
        }

        return in_array(ChargeMethod::CARD, $gateways, true) ? [ChargeMethod::CARD] : [];
    }

    public function startError(RecurringCartSummary $summary, string $paymentMethod): ?string
    {
        if (!$summary->hasRecurringProducts()) {
            return null;
        }

        if (!Settings::isCardMethod($paymentMethod)) {
            return 'Recurring products must be paid by card.';
        }

        if (!$summary->isVirtualCart) {
            return 'Recurring payments are available only for virtual products.';
        }

        if ($this->hasMixedPeriods($summary)) {
            return 'Recurring products in the same cart must use the same period.';
        }

        return null;
    }

    public function requiresReusableCard(RecurringCartSummary $summary): bool
    {
        return $summary->hasRecurringProducts();
    }

    public function captureModeOverride(RecurringCartSummary $summary): ?CaptureMode
    {
        return $summary->hasRecurringProducts() ? CaptureMode::IMMEDIATE : null;
    }

    public function shouldCaptureImmediately(
        RecurringCartSummary $summary,
        Settings $settings,
        string $paymentMethod,
    ): bool {
        return $summary->hasRecurringProducts() || $settings->shouldCaptureImmediately($paymentMethod);
    }

    public function usesRecurringPostType(RecurringCartSummary $summary): bool
    {
        return $summary->hasRecurringProducts();
    }

    private function hasMixedPeriods(RecurringCartSummary $summary): bool
    {
        $periods = [];
        foreach ($summary->groups as $group) {
            $periods[$group->period->value] = true;
        }

        return count($periods) > 1;
    }
}
