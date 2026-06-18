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

use function trim;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class WebhookAuth
{
    public const STATE_DISCONNECTED = 'disconnected';
    public const STATE_PENDING_VERIFICATION = 'pending_verification';
    public const STATE_CONNECTED = 'connected';

    private WebhookCredentials $credentials;
    private WebhookBasicAuth $basicAuth;

    public function __construct(
        private WebhookSettingsRepository $settingsRepository,
    ) {
        $this->credentials = new WebhookCredentials();
        $this->basicAuth = new WebhookBasicAuth();
    }

    /**
     * @return array<string, mixed>
     */
    public function ensureCredentialsStored(): array
    {
        $settings = $this->settingsRepository->getSettings();
        $ensured = $this->credentials->ensure($settings, $this->state($settings));

        if ($ensured !== $settings) {
            $this->settingsRepository->saveSettings($ensured);
        }

        return $ensured;
    }

    /**
     * @return array<string, mixed>
     */
    public function regenerateCredentialsStored(): array
    {
        $settings = $this->credentials->regenerate($this->settingsRepository->getSettings());
        $this->settingsRepository->saveSettings($settings);

        return $settings;
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function state(array $settings): string
    {
        return (string) ($settings[WebhookSettingsRepository::KEY_STATE] ?? self::STATE_DISCONNECTED);
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function authorizationStatus(array $settings): WebhookAuthStatus
    {
        return $this->basicAuth->authorizationStatus($settings, $this->decryptedPassword($settings));
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function decryptedPassword(array $settings): string
    {
        return $this->credentials->decryptedPassword($settings);
    }

    public function markVerificationEvent(string $eventId, string $verificationCode): bool
    {
        $eventId = trim($eventId);
        $verificationCode = trim($verificationCode);

        if ($eventId === '' || $verificationCode === '') {
            return false;
        }

        $settings = $this->settingsRepository->getSettings();
        $currentEvent = trim((string) ($settings[WebhookSettingsRepository::KEY_VERIFICATION_EVENT_ID] ?? ''));
        if ($currentEvent === $eventId) {
            return false;
        }

        $settings[WebhookSettingsRepository::KEY_VERIFICATION_CODE] = $verificationCode;
        $settings[WebhookSettingsRepository::KEY_VERIFICATION_EVENT_ID] = $eventId;
        $settings[WebhookSettingsRepository::KEY_VERIFICATION_RECEIVED_AT] = WebhookCredentials::now();
        $settings[WebhookSettingsRepository::KEY_STATE] = self::STATE_PENDING_VERIFICATION;

        $this->settingsRepository->saveSettings($settings);

        return true;
    }

    public function markConnectedIfPending(bool $isAuthenticated): void
    {
        if (!$isAuthenticated) {
            return;
        }

        $settings = $this->settingsRepository->getSettings();
        if ($this->state($settings) !== self::STATE_PENDING_VERIFICATION) {
            return;
        }

        $settings[WebhookSettingsRepository::KEY_STATE] = self::STATE_CONNECTED;
        $settings[WebhookSettingsRepository::KEY_CONNECTED_AT] = WebhookCredentials::now();
        $this->settingsRepository->saveSettings($settings);
    }
}
