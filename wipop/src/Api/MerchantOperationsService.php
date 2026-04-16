<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Api;

use Configuration;
use PrestaShop\Module\Wipop\Configuration\ConfigKeys;
use PrestaShop\Module\Wipop\Configuration\Settings;
use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use Wipop\Domain\ChargeMethod;
use Wipop\Domain\ProductType;
use Wipop\Domain\Value\Terminal;

use function array_intersect;
use function array_map;
use function array_values;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function in_array;
use function strtoupper;
use function time;
use function trim;

final class MerchantOperationsService
{
    private const CACHE_TTL_IN_SECONDS = 1 * 60 * 60;
    private const SUPPORTED_GATEWAYS = [ChargeMethod::CARD, ChargeMethod::BIZUM]; // TODO add GOOGLE_PAY when available

    public function __construct(
        private SettingsRepository $settingsRepository,
        private ClientFactory $clientFactory,
        private SdkCaller $sdkCaller,
    ) {
    }

    /**
     * @return string[]
     */
    public function getAvailableGateways(bool $forceRefresh = false): array
    {
        $settings = $this->settingsRepository->get();

        if (!$settings->isConfigured()) {
            return [];
        }

        if (!$forceRefresh) {
            $cached = $this->readCachedGateways($settings);
            if ($cached !== null) {
                return $cached;
            }
        }

        $client = $this->clientFactory->create($settings);
        $terminal = new Terminal($settings->terminalId);

        $gateways = $this->sdkCaller->call(
            'merchant.list_payment_methods',
            static fn () => $client
                ->merchantOperation()
                ->listPaymentMethods(ProductType::PAYMENT_GATEWAY, $terminal)
        );

        $normalized = self::normalizeGateways($gateways);
        $this->writeCachedGateways($settings, $normalized);

        return $normalized;
    }

    public static function supportsGateway(string $method): bool
    {
        return in_array(self::normalizeGateway($method), self::SUPPORTED_GATEWAYS, true);
    }

    public function isGatewayAvailable(string $method, bool $forceRefresh = false): bool
    {
        $method = self::normalizeGateway($method);

        if (!self::supportsGateway($method)) {
            return false;
        }

        return in_array($method, $this->getAvailableGateways($forceRefresh), true);
    }

    /**
     * @param array<int, string> $gateways
     *
     * @return string[]
     */
    private static function normalizeGateways(array $gateways): array
    {
        $normalized = array_map(static fn ($gateway): string => self::normalizeGateway((string) $gateway), $gateways);

        return array_values(array_intersect($normalized, self::SUPPORTED_GATEWAYS));
    }

    private static function normalizeGateway(string $gateway): string
    {
        return strtoupper(trim($gateway));
    }

    /**
     * @return string[]|null
     */
    private function readCachedGateways(Settings $settings): ?array
    {
        $rawSnapshot = Configuration::get(ConfigKeys::AVAILABLE_GATEWAYS_CACHE, '');

        if (!is_string($rawSnapshot) || $rawSnapshot === '') {
            return null;
        }

        $snapshot = json_decode($rawSnapshot, true);
        if (!is_array($snapshot)) {
            return null;
        }

        if (!$this->matchesSettings($snapshot, $settings)) {
            return null;
        }

        $refreshedAt = (int) ($snapshot['refreshed_at'] ?? 0);
        if ($refreshedAt <= 0 || (time() - $refreshedAt) > self::CACHE_TTL_IN_SECONDS) {
            return null;
        }

        $methods = $snapshot['methods'] ?? null;
        if (!is_array($methods)) {
            return null;
        }

        return self::normalizeGateways($methods);
    }

    /**
     * @param string[] $gateways
     */
    private function writeCachedGateways(Settings $settings, array $gateways): void
    {
        $snapshot = [
            'merchant_id' => $settings->merchantId,
            'terminal_id' => $settings->terminalId,
            'live_mode' => $settings->liveMode,
            'methods' => $gateways,
            'refreshed_at' => time(),
        ];

        $encodedSnapshot = json_encode($snapshot);
        if ($encodedSnapshot === false) {
            return;
        }

        Configuration::updateValue(ConfigKeys::AVAILABLE_GATEWAYS_CACHE, $encodedSnapshot);
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function matchesSettings(array $snapshot, Settings $settings): bool
    {
        return (string) ($snapshot['merchant_id'] ?? '') === $settings->merchantId
            && (int) ($snapshot['terminal_id'] ?? -1) === $settings->terminalId
            && (bool) ($snapshot['live_mode'] ?? null) === $settings->liveMode;
    }
}
