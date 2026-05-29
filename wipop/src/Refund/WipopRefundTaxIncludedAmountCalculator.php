<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Refund;

use PrestaShop\Module\Wipop\Payment\PaymentAmount;

final class WipopRefundTaxIncludedAmountCalculator
{
    public function amountForWipop(ManualRefundRequest $request, ManualRefundFormData $formData): float
    {
        return PaymentAmount::fromCents($this->amountCentsForWipop($request, $formData));
    }

    public function amountCentsForWipop(ManualRefundRequest $request, ManualRefundFormData $formData): int
    {
        if ($formData->isTaxIncluded) {
            return $request->totalCents();
        }

        $amountCents = $formData->shippingTaxIncludedAmountCentsFor($request->shippingCents());

        foreach ($request->lines() as $line) {
            $formLine = $formData->requireLineByOrderDetailId($line->orderDetailId);
            $amountCents += $formLine->taxIncludedAmountCentsFor($line->amountCents());
        }

        return $amountCents;
    }
}
