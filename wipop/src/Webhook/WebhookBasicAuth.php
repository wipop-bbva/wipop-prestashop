<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Webhook;

use function base64_decode;
use function base64_encode;
use function hash_equals;
use function is_string;
use function stripos;
use function strpos;
use function substr;
use function trim;

final class WebhookBasicAuth
{
    /**
     * @param array<string, mixed> $settings
     */
    public function authorizationStatus(array $settings, string $expectedPassword): WebhookAuthStatus
    {
        $header = self::readAuthorizationHeader();
        if ($header === '') {
            return WebhookAuthStatus::MISSING;
        }

        $parsed = self::parseAuthorizationHeader($header);
        if ($parsed['status'] !== WebhookAuthStatus::VALID_BASIC) {
            return $parsed['status'];
        }

        $credentials = $parsed['credentials'];
        $expectedUsername = trim((string) ($settings[WebhookSettingsRepository::KEY_USERNAME] ?? ''));

        if ($expectedUsername === '' || $expectedPassword === '') {
            return WebhookAuthStatus::INVALID_BASIC;
        }

        $isValid = hash_equals($expectedUsername, $credentials['username'])
            && hash_equals($expectedPassword, $credentials['password']);

        return $isValid ? WebhookAuthStatus::VALID_BASIC : WebhookAuthStatus::INVALID_BASIC;
    }

    private static function readAuthorizationHeader(): string
    {
        $authorization = self::serverValue('HTTP_AUTHORIZATION');
        if ($authorization !== '') {
            return $authorization;
        }

        $redirectAuthorization = self::serverValue('REDIRECT_HTTP_AUTHORIZATION');
        if ($redirectAuthorization !== '') {
            return $redirectAuthorization;
        }

        $username = self::serverValue('PHP_AUTH_USER');
        if ($username !== '') {
            return 'Basic ' . base64_encode($username . ':' . self::serverValue('PHP_AUTH_PW'));
        }

        return '';
    }

    private static function serverValue(string $key): string
    {
        $value = $_SERVER[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return array{status: WebhookAuthStatus, credentials?: array{username: string, password: string}}
     */
    private static function parseAuthorizationHeader(string $header): array
    {
        $prefix = 'Basic ';
        if (stripos($header, $prefix) !== 0) {
            return ['status' => WebhookAuthStatus::OTHER_SCHEME];
        }

        $encoded = substr($header, strlen($prefix));
        $credentials = self::parseBasicCredentials($encoded);
        if ($credentials === null) {
            return ['status' => WebhookAuthStatus::INVALID_BASIC];
        }

        return [
            'status' => WebhookAuthStatus::VALID_BASIC,
            'credentials' => $credentials,
        ];
    }

    /**
     * @return array{username: string, password: string}|null
     */
    private static function parseBasicCredentials(string $encoded): ?array
    {
        $decoded = base64_decode($encoded, true);

        if (!is_string($decoded) || $decoded === '') {
            return null;
        }

        $separator = strpos($decoded, ':');
        if ($separator === false) {
            return null;
        }

        return [
            'username' => substr($decoded, 0, $separator),
            'password' => substr($decoded, $separator + 1),
        ];
    }
}
