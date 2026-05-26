<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Refund;

interface OrderSlipIssuerInterface
{
    public function assertCanIssueOrderSlip(int $orderId, ManualRefundRequest $request): void;

    public function issue(int $orderId, ManualRefundRequest $request): int;
}
