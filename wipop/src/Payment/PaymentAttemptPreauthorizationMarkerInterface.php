<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Payment;

interface PaymentAttemptPreauthorizationMarkerInterface
{
    public function markPaid(int $attemptId): void;

    public function markCancelled(int $attemptId): void;
}
