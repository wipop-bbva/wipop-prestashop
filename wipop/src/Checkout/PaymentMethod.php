<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Checkout;

enum PaymentMethod: string
{
    case CARD = 'card';
    case BIZUM = 'bizum';

    /**
     * @return self[]
     */
    public static function checkoutMethods(): array
    {
        return [
            self::CARD,
            self::BIZUM,
        ];
    }
}
