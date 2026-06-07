<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Customer;

use Cart;
use Context;

final class LoggedCartCustomerResolver
{
    public function resolveIdForCart(Cart $cart): ?int
    {
        $customer = Context::getContext()->customer;
        if ($customer === null || !$customer->isLogged()) {
            return null;
        }

        $customerId = (int) $customer->id;
        if ($customerId <= 0 || $customerId !== (int) $cart->id_customer) {
            return null;
        }

        return $customerId;
    }
}
