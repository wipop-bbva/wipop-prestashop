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

namespace PrestaShop\Module\Wipop\Security;

use RuntimeException;
use Throwable;

use function base64_decode;
use function base64_encode;
use function defined;
use function hash;
use function hash_hmac;
use function is_string;
use function openssl_cipher_iv_length;
use function openssl_decrypt;
use function openssl_encrypt;
use function random_bytes;
use function strlen;
use function substr;

use const OPENSSL_RAW_DATA;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class SecretCipher
{
    private const CIPHER = 'aes-256-cbc';

    public function encrypt(string $plain): string
    {
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if ($ivLength <= 0) {
            throw new RuntimeException('Unsupported secret encryption cipher.');
        }

        try {
            $iv = random_bytes($ivLength);
        } catch (Throwable $throwable) {
            throw new RuntimeException('Unable to generate secret encryption IV.', 0, $throwable);
        }

        $encrypted = openssl_encrypt($plain, self::CIPHER, self::encryptionKey(), OPENSSL_RAW_DATA, $iv);
        if (!is_string($encrypted) || $encrypted === '') {
            throw new RuntimeException('Unable to encrypt secret.');
        }

        return base64_encode($iv . $encrypted);
    }

    public function decrypt(string $encryptedValue): string
    {
        $decoded = base64_decode($encryptedValue, true);
        if (!is_string($decoded) || $decoded === '') {
            throw new RuntimeException('Stored secret is not valid base64.');
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if ($ivLength <= 0) {
            throw new RuntimeException('Unsupported secret encryption cipher.');
        }

        if (strlen($decoded) <= $ivLength) {
            throw new RuntimeException('Stored secret payload is incomplete.');
        }

        $plain = openssl_decrypt(
            substr($decoded, $ivLength),
            self::CIPHER,
            self::encryptionKey(),
            OPENSSL_RAW_DATA,
            substr($decoded, 0, $ivLength)
        );

        if (!is_string($plain) || $plain === '') {
            throw new RuntimeException('Unable to decrypt stored secret.');
        }

        return $plain;
    }

    public function fingerprint(string $plain): string
    {
        return hash_hmac('sha256', $plain, self::encryptionKey());
    }

    private static function encryptionKey(): string
    {
        $cookieKey = defined('_COOKIE_KEY_') && is_string(_COOKIE_KEY_) ? _COOKIE_KEY_ : '';
        $cookieIv = defined('_COOKIE_IV_') && is_string(_COOKIE_IV_) ? _COOKIE_IV_ : '';

        return hash('sha256', $cookieKey . '|' . $cookieIv, true);
    }
}
