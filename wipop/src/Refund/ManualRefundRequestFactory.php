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

namespace PrestaShop\Module\Wipop\Refund;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ManualRefundRequestFactory
{
    /**
     * @param array<string, mixed> $data
     */
    public function fromSubmittedData(array $data, ManualRefundFormData $formData): ManualRefundRequest
    {
        $lines = [];
        foreach ($formData->lines as $line) {
            $quantity = $this->positiveInt($data['quantity_' . $line->orderDetailId] ?? null);
            $amount = $this->positiveFloat($data['amount_' . $line->orderDetailId] ?? null);

            if ($quantity <= 0 && $amount <= 0.0) {
                continue;
            }

            $lines[] = new ManualRefundLine($line->orderDetailId, $quantity, $amount);
        }

        return new ManualRefundRequest(
            $lines,
            $this->positiveFloat($data['shipping_amount'] ?? null),
            (bool) ($data['restock'] ?? false),
        );
    }

    private function positiveInt(mixed $value): int
    {
        return max(0, (int) $value);
    }

    private function positiveFloat(mixed $value): float
    {
        return max(0.0, (float) $value);
    }
}
