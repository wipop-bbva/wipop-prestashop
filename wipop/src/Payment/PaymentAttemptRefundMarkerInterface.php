<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Payment;

interface PaymentAttemptRefundMarkerInterface
{
    public function markRefunded(int $attemptId): void;
}
