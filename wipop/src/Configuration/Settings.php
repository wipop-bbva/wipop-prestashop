<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Configuration;

use Wipop\Domain\ChargeMethod;
use Wipop\Domain\Value\Terminal;

use function array_intersect;
use function array_values;
use function in_array;
use function is_scalar;
use function strtoupper;
use function trim;

final class Settings
{
    public const MIN_TERMINAL_ID = Terminal::MIN_TERMINAL_ID;
    public const MAX_TERMINAL_ID = Terminal::MAX_TERMINAL_ID;
    public const DEFAULT_TERMINAL_ID = self::MIN_TERMINAL_ID;
    public const SUPPORTED_GATEWAYS = [ChargeMethod::CARD, ChargeMethod::BIZUM];

    /**
     * @param string[] $enabledGateways
     */
    public function __construct(
        public bool $liveMode,
        public string $merchantId,
        public string $secretKey,
        public string $publicSecretKey,
        public int $terminalId,
        public array $enabledGateways = [],
        public CaptureMode $captureMode = CaptureMode::IMMEDIATE,
    ) {
        $this->enabledGateways = self::normalizeGateways($enabledGateways);

        if ($this->enabledGateways === []) {
            $this->enabledGateways = self::SUPPORTED_GATEWAYS;
        }
    }

    public function isConfigured(): bool
    {
        return $this->merchantId !== ''
            && $this->secretKey !== ''
            && $this->terminalId >= self::MIN_TERMINAL_ID
            && $this->terminalId <= self::MAX_TERMINAL_ID;
    }

    /**
     * @param mixed[] $availableGateways
     *
     * @return string[]
     */
    public function filterGateways(array $availableGateways): array
    {
        $availableGateways = self::normalizeGateways($availableGateways);

        return array_values(array_intersect($availableGateways, $this->enabledGateways));
    }

    public function shouldCaptureImmediately(string $method): bool
    {
        return $this->captureMode !== CaptureMode::MANUAL
            || !self::isCardMethod($method);
    }

    public static function isCardMethod(string $method): bool
    {
        return self::normalizeGateway($method) === ChargeMethod::CARD;
    }

    /**
     * @param mixed[] $gateways
     *
     * @return string[]
     */
    public static function normalizeGateways(array $gateways): array
    {
        $normalized = [];

        foreach ($gateways as $gateway) {
            if (!is_scalar($gateway)) {
                continue;
            }

            $gateway = self::normalizeGateway((string) $gateway);

            if ($gateway === '' || !in_array($gateway, self::SUPPORTED_GATEWAYS, true)) {
                continue;
            }

            if (!in_array($gateway, $normalized, true)) {
                $normalized[] = $gateway;
            }
        }

        return $normalized;
    }

    private static function normalizeGateway(string $gateway): string
    {
        return strtoupper(trim($gateway));
    }
}
