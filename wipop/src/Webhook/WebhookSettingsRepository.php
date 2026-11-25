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

namespace PrestaShop\Module\Wipop\Webhook;

use Configuration;
use JsonException;
use PrestaShop\Module\Wipop\Exception\WebhookSettingsReadException;
use PrestaShop\Module\Wipop\Exception\WebhookSettingsWriteException;

use function array_fill_keys;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function trim;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class WebhookSettingsRepository
{
    public const CONFIG_KEY = 'WIPOP_WEBHOOK_SETTINGS';

    public const KEY_USERNAME = 'webhook_auth_username';
    public const KEY_PASSWORD_ENCRYPTED = 'webhook_auth_password_enc';
    public const KEY_STATE = 'webhook_state';
    public const KEY_VERIFICATION_CODE = 'webhook_verification_code';
    public const KEY_VERIFICATION_EVENT_ID = 'webhook_verification_event_id';
    public const KEY_VERIFICATION_RECEIVED_AT = 'webhook_verification_received_at';
    public const KEY_CONNECTED_AT = 'webhook_connected_at';
    public const KEY_CREDENTIALS_ROTATED_AT = 'webhook_credentials_rotated_at';

    /**
     * @return array<string, string>
     */
    public function getSettings(): array
    {
        $rawSettings = Configuration::get(self::CONFIG_KEY, default: '');
        if (!is_string($rawSettings) || $rawSettings === '') {
            return self::defaults();
        }

        try {
            $settings = json_decode($rawSettings, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new WebhookSettingsReadException('Stored Wipop webhook settings JSON is invalid.', 0, $exception);
        }

        if (!is_array($settings)) {
            throw new WebhookSettingsReadException('Stored Wipop webhook settings JSON must be an object.');
        }

        return self::normalizeSettings($settings);
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @throws WebhookSettingsWriteException
     */
    public function saveSettings(array $settings): void
    {
        $payload = [];

        foreach (self::keys() as $key) {
            $payload[$key] = (string) ($settings[$key] ?? '');
        }

        try {
            $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new WebhookSettingsWriteException('Unable to encode Wipop webhook settings JSON.', 0, $exception);
        }

        if (!Configuration::updateValue(self::CONFIG_KEY, $encodedPayload)) {
            throw new WebhookSettingsWriteException('Unable to persist Wipop webhook settings JSON.');
        }
    }

    public function deleteAll(): bool
    {
        return Configuration::deleteByName(self::CONFIG_KEY);
    }

    /**
     * @return array<string, string>
     */
    private static function defaults(): array
    {
        return array_fill_keys(self::keys(), '');
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, string>
     */
    private static function normalizeSettings(array $settings): array
    {
        $normalized = [];

        foreach (self::keys() as $key) {
            $normalized[$key] = trim((string) ($settings[$key] ?? ''));
        }

        return $normalized;
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
}
