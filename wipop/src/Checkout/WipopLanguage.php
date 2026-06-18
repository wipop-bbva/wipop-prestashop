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

namespace PrestaShop\Module\Wipop\Checkout;

use function str_replace;
use function str_starts_with;
use function strtolower;
use function trim;

if (!defined('_PS_VERSION_')) {
    exit;
}

enum WipopLanguage: string
{
    case SPANISH = 'es-ES';
    case CATALAN = 'ca-ES';
    case BASQUE = 'eu-ES';
    case GALICIAN = 'gl-ES';
    case ENGLISH = 'en-GB';

    public static function fromPrestashopLocale(?string $locale): self
    {
        $normalizedLocale = strtolower(str_replace('_', '-', trim((string) $locale)));
        if ($normalizedLocale === '') {
            return self::SPANISH;
        }

        return match (true) {
            str_starts_with($normalizedLocale, 'ca') => self::CATALAN,
            str_starts_with($normalizedLocale, 'eu') => self::BASQUE,
            str_starts_with($normalizedLocale, 'gl') => self::GALICIAN,
            str_starts_with($normalizedLocale, 'en') => self::ENGLISH,
            str_starts_with($normalizedLocale, 'es') => self::SPANISH,
            default => self::SPANISH,
        };
    }
}
