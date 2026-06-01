<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Preauthorization;

interface PreauthorizationOrderUpdaterInterface
{
    public function markCaptured(
        int $orderId,
        float $amount,
        string $currencyIso,
        string $transactionId,
        int $employeeId,
    ): void;

    public function markCancelled(int $orderId): void;
}
