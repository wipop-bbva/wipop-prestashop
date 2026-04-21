<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Webhook;

use Configuration;

final class WebhookSettingsRepository
{
    public const KEY_USERNAME = 'webhook_auth_username';
    public const KEY_PASSWORD_ENCRYPTED = 'webhook_auth_password_enc';
    public const KEY_STATE = 'webhook_state';
    public const KEY_VERIFICATION_CODE = 'webhook_verification_code';
    public const KEY_VERIFICATION_EVENT_ID = 'webhook_verification_event_id';
    public const KEY_VERIFICATION_RECEIVED_AT = 'webhook_verification_received_at';
    public const KEY_CONNECTED_AT = 'webhook_connected_at';
    public const KEY_CREDENTIALS_ROTATED_AT = 'webhook_credentials_rotated_at';

    private const CONFIG_PREFIX = 'WIPOP_';

    /**
     * @return array<string, string>
     */
    public function getSettings(): array
    {
        $settings = [];

        foreach (self::keys() as $key) {
            $settings[$key] = trim((string) Configuration::get($this->configurationKey($key), ''));
        }

        return $settings;
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function saveSettings(array $settings): bool
    {
        $result = true;

        foreach (self::keys() as $key) {
            $result = $result && Configuration::updateValue(
                $this->configurationKey($key),
                (string) ($settings[$key] ?? '')
            );
        }

        return $result;
    }

    public function deleteAll(): bool
    {
        $result = true;

        foreach (self::keys() as $key) {
            $result = $result && Configuration::deleteByName($this->configurationKey($key));
        }

        return $result;
    }

    /**
     * @return string[]
     */
    private static function keys(): array
    {
        return [
            self::KEY_USERNAME,
            self::KEY_PASSWORD_ENCRYPTED,
            self::KEY_STATE,
            self::KEY_VERIFICATION_CODE,
            self::KEY_VERIFICATION_EVENT_ID,
            self::KEY_VERIFICATION_RECEIVED_AT,
            self::KEY_CONNECTED_AT,
            self::KEY_CREDENTIALS_ROTATED_AT,
        ];
    }

    private function configurationKey(string $key): string
    {
        return self::CONFIG_PREFIX . strtoupper($key);
    }
}
