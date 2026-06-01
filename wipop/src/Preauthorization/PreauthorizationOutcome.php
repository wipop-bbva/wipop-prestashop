<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Preauthorization;

final class PreauthorizationOutcome
{
    public function __construct(
        public readonly float $amount,
        public readonly string $currencyIso,
        public readonly ?string $localWarning = null,
    ) {
    }
}
