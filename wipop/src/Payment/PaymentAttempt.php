<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Payment;

final class PaymentAttempt
{
    public function __construct(
        public readonly int $id,
        public readonly int $cartId,
        public readonly ?int $orderId,
        public readonly PaymentAttemptStatus $status,
        public readonly float $amount,
    ) {
    }
}
