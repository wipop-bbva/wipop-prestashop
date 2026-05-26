<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Refund;

interface WipopRefundClientInterface
{
    public function refundCharge(string $chargeTransactionId, float $amount): WipopRefundResult;
}
