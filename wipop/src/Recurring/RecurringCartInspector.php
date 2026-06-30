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

namespace PrestaShop\Module\Wipop\Recurring;

use Cart;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class RecurringCartInspector
{
    public function __construct(private ProductRecurringSettingsRepository $settingsRepository)
    {
    }

    public function inspect(Cart $cart): RecurringCartSummary
    {
        /** @var array<string, array{period: RecurringPeriod, amount: float, items: list<RecurringCartItem>}> $groups */
        $groups = [];
        /** @var list<array{id_product: int|string, id_product_attribute: int|string, cart_quantity: int|string, total_wt: int|float|string}> $products */
        $products = $cart->getProducts();

        foreach ($products as $product) {
            $productId = (int) $product['id_product'];
            $period = $this->settingsRepository->findPeriod($productId, (int) $cart->id_shop);
            if ($period === null) {
                continue;
            }

            $key = $period->value;
            $groups[$key] ??= [
                'period' => $period,
                'amount' => 0.0,
                'items' => [],
            ];

            $groups[$key]['amount'] += round((float) $product['total_wt'], 2);
            $groups[$key]['items'][] = new RecurringCartItem(
                $productId,
                (int) $product['id_product_attribute'],
                (int) $product['cart_quantity']
            );
        }

        return new RecurringCartSummary(
            array_map(
                static fn (array $group): RecurringCartGroup => new RecurringCartGroup(
                    $group['period'],
                    round((float) $group['amount'], 2),
                    $group['items']
                ),
                array_values($groups)
            ),
            (bool) $cart->isVirtualCart()
        );
    }
}
