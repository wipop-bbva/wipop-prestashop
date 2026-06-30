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

use function sprintf;
use function strtoupper;
use function trim;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class StoredCardPresenter
{
    /**
     * @return array{id: int, brand: string, last4: string, expiry: ?string, label: string}
     */
    public function present(StoredCard|StoredCardSummary $card): array
    {
        $brand = strtoupper(trim((string) $card->brand));
        if ($brand === '') {
            $brand = 'Card';
        }

        $last4 = trim((string) $card->last4);
        $label = $last4 === '' ? $brand : sprintf('%s •••• %s', $brand, $last4);

        return [
            'id' => $card->id,
            'brand' => $brand,
            'last4' => $last4,
            'expiry' => $this->expiry($card),
            'label' => $label,
        ];
    }

    private function expiry(StoredCard|StoredCardSummary $card): ?string
    {
        $month = trim((string) $card->expirationMonth);
        $year = trim((string) $card->expirationYear);

        return $month === '' || $year === '' ? null : sprintf('%s/%s', $month, $year);
    }
}
