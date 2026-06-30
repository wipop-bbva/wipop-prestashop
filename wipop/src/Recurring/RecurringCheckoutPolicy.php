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

namespace PrestaShop\Module\Wipop\Recurring;

use PrestaShop\Module\Wipop\Configuration\CaptureMode;
use PrestaShop\Module\Wipop\Configuration\Settings;
use Wipop\Domain\ChargeMethod;

if (!defined('_PS_VERSION_')) {
    exit;
}

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
