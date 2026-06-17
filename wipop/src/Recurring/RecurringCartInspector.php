<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

use Cart;

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
