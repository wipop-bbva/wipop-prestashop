<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Checkout;

final class CartOrderIdFactory
{
    public function fromCart(\Cart $cart): string
    {
        $numericId = max(0, (int) $cart->id);
        $prefix = str_pad((string) ($numericId % 10000), 4, '0', STR_PAD_LEFT);

        $seed = trim((string) $cart->secure_key);
        if ($seed === '') {
            $seed = sprintf(
                '%d|%s',
                (int) $cart->id_customer,
                (string) $cart->date_add
            );
        }

        // Wipop order suffix is 8 alphanumeric characters.
        $hash = strtoupper(hash('crc32b', $prefix . '|' . $seed));
        $suffix = substr($hash, 0, 8);

        return $prefix . $suffix;
    }
}
