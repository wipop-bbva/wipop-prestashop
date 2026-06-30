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

namespace PrestaShop\Module\Wipop\Card;

use Wipop\Domain\Card;

use function preg_replace;
use function str_pad;
use function strlen;
use function substr;
use function trim;

use const STR_PAD_LEFT;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class StoredCardDetails
{
    public function __construct(
        public readonly string $sourceId,
        public readonly ?string $brand,
        public readonly ?string $last4,
        public readonly ?string $expirationMonth,
        public readonly ?string $expirationYear,
    ) {
    }

    public static function fromWipopCard(Card $card): ?self
    {
        $sourceId = trim((string) $card->id);
        if ($sourceId === '') {
            return null;
        }

        return new self(
            $sourceId,
            self::nullableString($card->brand),
            self::last4($card),
            self::month($card->expirationMonth),
            self::year($card->expirationYear),
        );
    }

    private static function last4(Card $card): ?string
    {
        $masked = trim((string) ($card->cardNumber ?? $card->number ?? ''));
        if ($masked === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $masked);
        if (!is_string($digits) || strlen($digits) < 4) {
            return null;
        }

        return substr($digits, -4);
    }

    private static function month(?string $month): ?string
    {
        $month = self::nullableString($month);

        return $month === null ? null : str_pad($month, 2, '0', STR_PAD_LEFT);
    }

    private static function year(?string $year): ?string
    {
        $year = self::nullableString($year);
        if ($year === null) {
            return null;
        }

        return strlen($year) === 2 ? '20' . $year : $year;
    }

    private static function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
