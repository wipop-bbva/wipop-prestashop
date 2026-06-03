<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Payment;

use PrestaShop\Module\Wipop\Configuration\CaptureMode;

final class PaymentAttempt
{
    public function __construct(
        public readonly int $id,
        public readonly int $cartId,
        public readonly ?int $orderId,
        public readonly PaymentAttemptStatus $status,
        public readonly float $amount,
        public readonly string $currencyIso,
        public readonly ?string $paymentMethod = null,
        public readonly CaptureMode $captureMode = CaptureMode::IMMEDIATE,
        public readonly ?string $wipopTransactionId = null,
    ) {
    }
}
