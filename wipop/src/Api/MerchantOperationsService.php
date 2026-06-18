<?php
/**
 * 2007-2026 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2026 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Api;

use Configuration;
use PrestaShop\Module\Wipop\Configuration\ConfigKeys;
use PrestaShop\Module\Wipop\Configuration\Settings;
use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use Wipop\Domain\ProductType;
use Wipop\Domain\Value\Terminal;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function time;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class MerchantOperationsService
{
    private const CACHE_TTL_IN_SECONDS = 1 * 60 * 60;

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
        return $this->getAvailableGatewaysFor($this->settingsRepository->get(), $forceRefresh);
    }

    /**
     * @return string[]
     */
    public function getAvailableGatewaysFor(Settings $settings, bool $forceRefresh = false): array
    {
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

        $normalized = Settings::normalizeGateways($gateways);
        $this->writeCachedGateways($settings, $normalized);

        return $normalized;
    }

    /**
     * @return string[]
     */
    public function getCheckoutGateways(bool $forceRefresh = false): array
    {
        return $this->getCheckoutGatewaysFor($this->settingsRepository->get(), $forceRefresh);
    }

    /**
     * @return string[]
     */
    public function getCheckoutGatewaysFor(Settings $settings, bool $forceRefresh = false): array
    {
        return $settings->filterGateways($this->getAvailableGatewaysFor($settings, $forceRefresh));
    }

    public static function supportsGateway(string $method): bool
    {
        return Settings::normalizeGateways([$method]) !== [];
    }

    /**
     * @return string[]|null
     */
    private function readCachedGateways(Settings $settings): ?array
    {
        $rawSnapshot = Configuration::get(ConfigKeys::AVAILABLE_GATEWAYS_CACHE, default: '');

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

        return Settings::normalizeGateways($methods);
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
