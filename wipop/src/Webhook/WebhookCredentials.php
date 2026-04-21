<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Webhook;

use PrestaShop\Module\Wipop\Log\Logger;
use Throwable;

use function base64_decode;
use function base64_encode;
use function bin2hex;
use function gmdate;
use function hash;
use function is_string;
use function openssl_cipher_iv_length;
use function openssl_decrypt;
use function openssl_encrypt;
use function random_bytes;
use function strlen;
use function substr;
use function trim;

final class WebhookCredentials
{
    private const CIPHER = 'aes-256-cbc';

    public function __construct(private Logger $logger)
    {
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    public function ensure(array $settings, string $state): array
    {
        $settings[WebhookSettingsRepository::KEY_STATE] = $state;

        $username = trim((string) ($settings[WebhookSettingsRepository::KEY_USERNAME] ?? ''));
        $password = $this->decryptedPassword($settings);

        if ($username !== '' && $password !== '') {
            return $settings;
        }

        $generated = $this->build();
        if ($generated === null) {
            return $settings;
        }

        $settings[WebhookSettingsRepository::KEY_USERNAME] = $generated['username'];
        $settings[WebhookSettingsRepository::KEY_PASSWORD_ENCRYPTED] = $generated['password_enc'];
        $settings[WebhookSettingsRepository::KEY_STATE] = WebhookAuth::STATE_DISCONNECTED;
        $settings[WebhookSettingsRepository::KEY_CREDENTIALS_ROTATED_AT] = self::now();

        return $settings;
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    public function regenerate(array $settings): array
    {
        $generated = $this->build();
        if ($generated === null) {
            return $settings;
        }

        $settings[WebhookSettingsRepository::KEY_USERNAME] = $generated['username'];
        $settings[WebhookSettingsRepository::KEY_PASSWORD_ENCRYPTED] = $generated['password_enc'];
        $settings[WebhookSettingsRepository::KEY_STATE] = WebhookAuth::STATE_DISCONNECTED;
        $settings[WebhookSettingsRepository::KEY_CREDENTIALS_ROTATED_AT] = self::now();
        $settings[WebhookSettingsRepository::KEY_VERIFICATION_CODE] = '';
        $settings[WebhookSettingsRepository::KEY_VERIFICATION_EVENT_ID] = '';
        $settings[WebhookSettingsRepository::KEY_VERIFICATION_RECEIVED_AT] = '';
        $settings[WebhookSettingsRepository::KEY_CONNECTED_AT] = '';

        return $settings;
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function decryptedPassword(array $settings): string
    {
        $encrypted = trim((string) ($settings[WebhookSettingsRepository::KEY_PASSWORD_ENCRYPTED] ?? ''));
        if ($encrypted === '') {
            return '';
        }

        return self::decryptPassword($encrypted);
    }

    public static function now(): string
    {
        return gmdate('c');
    }

    /**
     * @return array{username: string, password_enc: string}|null
     */
    private function build(): ?array
    {
        $usernameSuffix = self::randomHex(6);
        $password = self::randomHex(16);

        if ($usernameSuffix === null || $password === null) {
            $this->logger->error('Unable to generate webhook credentials.');

            return null;
        }

        $passwordEncrypted = self::encryptPassword($password);
        if ($passwordEncrypted === null) {
            $this->logger->error('Fail during webhook password encryption.');

            return null;
        }

        return [
            'username' => 'wipop_' . $usernameSuffix,
            'password_enc' => $passwordEncrypted,
        ];
    }

    private static function randomHex(int $bytes): ?string
    {
        try {
            return bin2hex(random_bytes($bytes));
        } catch (Throwable) {
            return null;
        }
    }

    private static function encryptionKey(): string
    {
        $cookieKey = defined('_COOKIE_KEY_') && is_string(_COOKIE_KEY_) ? _COOKIE_KEY_ : '';
        $cookieIv = defined('_COOKIE_IV_') && is_string(_COOKIE_IV_) ? _COOKIE_IV_ : '';

        return hash('sha256', $cookieKey . '|' . $cookieIv, true);
    }

    private static function encryptPassword(string $password): ?string
    {
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if ($ivLength <= 0) {
            return null;
        }

        try {
            $iv = random_bytes($ivLength);
        } catch (Throwable) {
            return null;
        }

        $encrypted = openssl_encrypt(
            $password,
            self::CIPHER,
            self::encryptionKey(),
            OPENSSL_RAW_DATA,
            $iv
        );

        if (!is_string($encrypted) || $encrypted === '') {
            return null;
        }

        return base64_encode($iv . $encrypted);
    }

    private static function decryptPassword(string $encryptedValue): string
    {
        $decoded = base64_decode($encryptedValue, true);
        if (!is_string($decoded) || $decoded === '') {
            return '';
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if ($ivLength <= 0 || strlen($decoded) <= $ivLength) {
            return '';
        }

        $iv = substr($decoded, 0, $ivLength);
        $cipherText = substr($decoded, $ivLength);

        $plain = openssl_decrypt(
            $cipherText,
            self::CIPHER,
            self::encryptionKey(),
            OPENSSL_RAW_DATA,
            $iv
        );

        return is_string($plain) ? $plain : '';
    }
}
