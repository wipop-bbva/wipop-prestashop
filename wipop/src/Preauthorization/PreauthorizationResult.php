<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Preauthorization;

final class PreauthorizationResult
{
    public function __construct(
        public readonly string $remoteTransactionId,
        public readonly string $parentTransactionId,
        public readonly ?string $wipopOrderId,
        public readonly string $status,
        public readonly float $amount,
        public readonly string $currencyIso,
        public readonly ?string $operationType,
        public readonly ?string $remoteErrorCode,
        public readonly ?string $remoteErrorMessage,
    ) {
    }
}
