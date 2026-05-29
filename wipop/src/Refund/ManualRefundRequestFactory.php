<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Refund;

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
