<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Checkout;

final class CartOrderIdFactory
{
    public function fromCartAttempt(\Cart $cart, int $attemptId): string
    {
        if ($attemptId <= 0) {
            throw new \InvalidArgumentException('Wipop payment attempt identifier must be positive.');
        }

        $numericId = max(0, (int) $cart->id);
        $prefix = str_pad((string) ($numericId % 10000), 4, '0', STR_PAD_LEFT);

        $suffix = strtoupper(base_convert((string) $attemptId, 10, 36));

        // Wipop order suffix is 8 alphanumeric characters.
        return $prefix . str_pad($suffix, 8, '0', STR_PAD_LEFT);
    }
}
