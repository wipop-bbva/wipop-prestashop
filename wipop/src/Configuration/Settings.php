<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Configuration;

final class Settings
{
    public const MIN_TERMINAL_ID = 0;
    public const MAX_TERMINAL_ID = 99;
    public const DEFAULT_TERMINAL_ID = 1;

    public function __construct(
        public bool $liveMode,
        public string $merchantId,
        public string $secretKey,
        public string $publicSecretKey,
        public int $terminalId,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->merchantId !== ''
            && $this->secretKey !== ''
            && $this->publicSecretKey !== ''
            && $this->terminalId >= self::MIN_TERMINAL_ID
            && $this->terminalId <= self::MAX_TERMINAL_ID;
    }
}
