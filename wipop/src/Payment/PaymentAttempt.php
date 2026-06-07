<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Payment;

use PrestaShop\Module\Wipop\Card\CardCofIntent;
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
        public readonly int $shopId = 0,
        public readonly bool $isLive = false,
        public readonly int $terminalId = 0,
        public readonly CardCofIntent $cofIntent = CardCofIntent::NONE,
        public readonly ?int $storedCardId = null,
        public readonly int $customerId = 0,
        public readonly string $merchantId = '',
    ) {
    }
}
