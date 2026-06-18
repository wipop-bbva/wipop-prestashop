<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Configuration;

use Configuration as PrestaShopConfiguration;
use JsonException;
use PrestaShop\Module\Wipop\Exception\SettingsReadException;
use PrestaShop\Module\Wipop\Exception\SettingsWriteException;

use function array_replace;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function trim;

final class SettingsRepository
{
    private const FIELD_LIVE_MODE = 'live_mode';
    private const FIELD_MERCHANT_ID = 'merchant_id';
    private const FIELD_SECRET_KEY = 'secret_key';
    private const FIELD_PUBLIC_SECRET_KEY = 'public_secret_key';
    private const FIELD_TERMINAL_ID = 'terminal_id';
    private const FIELD_ENABLED_GATEWAYS = 'enabled_gateways';
    private const FIELD_CAPTURE_MODE = 'capture_mode';

    public function installDefaults(): bool
    {
        try {
            $this->savePayload(self::defaultPayload());
        } catch (SettingsWriteException) {
            return false;
        }

        return true;
    }

    public function deleteAll(): bool
    {
        $result = true;

        foreach (ConfigKeys::all() as $key) {
            $result = $result && PrestaShopConfiguration::deleteByName($key);
        }

        return $result;
    }

    public function get(): Settings
    {
        $payload = $this->readPayload();

        return new Settings(
            (bool) $payload[self::FIELD_LIVE_MODE],
            trim((string) $payload[self::FIELD_MERCHANT_ID]),
            trim((string) $payload[self::FIELD_SECRET_KEY]),
            trim((string) $payload[self::FIELD_PUBLIC_SECRET_KEY]),
            (int) $payload[self::FIELD_TERMINAL_ID],
            $payload[self::FIELD_ENABLED_GATEWAYS],
            CaptureMode::fromValue($payload[self::FIELD_CAPTURE_MODE])
        );
    }

    /**
     * @throws SettingsWriteException
     */
    public function save(Settings $settings): void
    {
        $this->savePayload([
            self::FIELD_LIVE_MODE => $settings->liveMode,
            self::FIELD_MERCHANT_ID => $settings->merchantId,
            self::FIELD_SECRET_KEY => $settings->secretKey,
            self::FIELD_PUBLIC_SECRET_KEY => $settings->publicSecretKey,
            self::FIELD_TERMINAL_ID => $settings->terminalId,
            self::FIELD_ENABLED_GATEWAYS => $settings->enabledGateways,
            self::FIELD_CAPTURE_MODE => $settings->captureMode->value,
        ]);

        PrestaShopConfiguration::deleteByName(ConfigKeys::AVAILABLE_GATEWAYS_CACHE);
    }

    /**
     * @return array{
     *     live_mode: bool,
     *     merchant_id: string,
     *     secret_key: string,
     *     public_secret_key: string,
     *     terminal_id: int,
     *     enabled_gateways: string[],
     *     capture_mode: string
     * }
     */
    private static function defaultPayload(): array
    {
        return [
            self::FIELD_LIVE_MODE => false,
            self::FIELD_MERCHANT_ID => '',
            self::FIELD_SECRET_KEY => '',
            self::FIELD_PUBLIC_SECRET_KEY => '',
            self::FIELD_TERMINAL_ID => Settings::DEFAULT_TERMINAL_ID,
            self::FIELD_ENABLED_GATEWAYS => Settings::SUPPORTED_GATEWAYS,
            self::FIELD_CAPTURE_MODE => CaptureMode::IMMEDIATE->value,
        ];
    }

    /**
     * @return array{
     *     live_mode: bool,
     *     merchant_id: string,
     *     secret_key: string,
     *     public_secret_key: string,
     *     terminal_id: int,
     *     enabled_gateways: string[],
     *     capture_mode: string
     * }
     */
    private function readPayload(): array
    {
        $rawPayload = PrestaShopConfiguration::get(ConfigKeys::SETTINGS, default: '');
        if (!is_string($rawPayload) || $rawPayload === '') {
            return self::defaultPayload();
        }

        try {
            $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new SettingsReadException('Stored Wipop settings JSON is invalid.', 0, $exception);
        }

        if (!is_array($payload)) {
            throw new SettingsReadException('Stored Wipop settings JSON must be an object.');
        }

        return self::normalizePayload($payload);
    }

    /**
     * @param array<string, bool|int|string|string[]> $payload
     *
     * @throws SettingsWriteException
     */
    private function savePayload(array $payload): void
    {
        try {
            $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new SettingsWriteException('Unable to encode Wipop settings JSON.', 0, $exception);
        }

        if (!PrestaShopConfiguration::updateValue(ConfigKeys::SETTINGS, $encodedPayload)) {
            throw new SettingsWriteException('Unable to persist Wipop settings JSON.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{
     *     live_mode: bool,
     *     merchant_id: string,
     *     secret_key: string,
     *     public_secret_key: string,
     *     terminal_id: int,
     *     enabled_gateways: string[],
     *     capture_mode: string
     * }
     */
    private static function normalizePayload(array $payload): array
    {
        $payload = array_replace(self::defaultPayload(), $payload);
        $enabledGateways = is_array($payload[self::FIELD_ENABLED_GATEWAYS])
            ? Settings::normalizeGateways($payload[self::FIELD_ENABLED_GATEWAYS])
            : [];

        return [
            self::FIELD_LIVE_MODE => (bool) $payload[self::FIELD_LIVE_MODE],
            self::FIELD_MERCHANT_ID => trim((string) $payload[self::FIELD_MERCHANT_ID]),
            self::FIELD_SECRET_KEY => trim((string) $payload[self::FIELD_SECRET_KEY]),
            self::FIELD_PUBLIC_SECRET_KEY => trim((string) $payload[self::FIELD_PUBLIC_SECRET_KEY]),
            self::FIELD_TERMINAL_ID => (int) $payload[self::FIELD_TERMINAL_ID],
            self::FIELD_ENABLED_GATEWAYS => $enabledGateways,
            self::FIELD_CAPTURE_MODE => CaptureMode::fromValue($payload[self::FIELD_CAPTURE_MODE])->value,
        ];
    }
}
