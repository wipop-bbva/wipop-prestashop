<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Webhook;

use PrestaShop\Module\Wipop\Exception\WebhookCredentialGenerationException;
use PrestaShop\Module\Wipop\Exception\WebhookPasswordDecryptionException;
use PrestaShop\Module\Wipop\Exception\WebhookPasswordEncryptionException;
use PrestaShop\Module\Wipop\Security\SecretCipher;
use Throwable;

use function bin2hex;
use function gmdate;
use function random_bytes;
use function trim;

final class WebhookCredentials
{
    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     *
     * @throws WebhookCredentialGenerationException
     * @throws WebhookPasswordDecryptionException
     * @throws WebhookPasswordEncryptionException
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
     *
     * @throws WebhookCredentialGenerationException
     * @throws WebhookPasswordEncryptionException
     */
    public function regenerate(array $settings): array
    {
        $generated = $this->build();

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
     *
     * @throws WebhookPasswordDecryptionException
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
     * @return array{username: string, password_enc: string}
     *
     * @throws WebhookCredentialGenerationException
     * @throws WebhookPasswordEncryptionException
     */
    private function build(): array
    {
        $usernameSuffix = self::randomHex(6);
        $password = self::randomHex(16);

        $passwordEncrypted = self::encryptPassword($password);

        return [
            'username' => 'wipop_' . $usernameSuffix,
            'password_enc' => $passwordEncrypted,
        ];
    }

    /**
     * @throws WebhookCredentialGenerationException
     */
    private static function randomHex(int $bytes): string
    {
        try {
            return bin2hex(random_bytes($bytes));
        } catch (Throwable $throwable) {
            $message = 'Unable to generate secure webhook credential material.';

            throw new WebhookCredentialGenerationException($message, 0, $throwable);
        }
    }

    /**
     * @throws WebhookPasswordEncryptionException
     */
    private static function encryptPassword(string $password): string
    {
        try {
            return (new SecretCipher())->encrypt($password);
        } catch (Throwable $throwable) {
            throw new WebhookPasswordEncryptionException('Unable to encrypt webhook password.', 0, $throwable);
        }
    }

    /**
     * @throws WebhookPasswordDecryptionException
     */
    private static function decryptPassword(string $encryptedValue): string
    {
        try {
            return (new SecretCipher())->decrypt($encryptedValue);
        } catch (Throwable $throwable) {
            throw new WebhookPasswordDecryptionException('Unable to decrypt stored webhook password.', 0, $throwable);
        }
    }
}
