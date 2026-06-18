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
use Context;
use Currency;
use Customer;
use Language;
use PrestaShop\Module\Wipop\Payment\PaymentAmount;
use RuntimeException;
use Shop;
use Validate;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class RecurringCartFactory
{
    public function create(RecurringSubscription $subscription): Cart
    {
        if ($subscription->items === []) {
            throw new RuntimeException('Recurring subscription has no products.');
        }

        $customer = new Customer($subscription->customerId);
        if (!Validate::isLoadedObject($customer)) {
            throw new RuntimeException('Recurring subscription customer could not be loaded.');
        }

        $shop = new Shop($subscription->shopId);
        if (!Validate::isLoadedObject($shop)) {
            throw new RuntimeException('Recurring subscription shop could not be loaded.');
        }

        $this->configureContext($subscription, $customer, $shop);

        $cart = new Cart();
        $cart->id_customer = $subscription->customerId;
        $cart->id_shop = $subscription->shopId;
        $cart->id_shop_group = (int) Shop::getGroupFromShop($subscription->shopId);
        $cart->id_currency = $subscription->currencyId;
        $cart->id_lang = $subscription->languageId;
        $cart->id_address_invoice = $subscription->invoiceAddressId;
        $cart->id_address_delivery = $subscription->deliveryAddressId;
        $cart->secure_key = (string) $customer->secure_key;

        if (!$cart->add()) {
            throw new RuntimeException('Recurring renewal cart could not be created.');
        }
        Context::getContext()->cart = $cart;

        foreach ($subscription->items as $item) {
            $updated = $cart->updateQty(
                $item->quantity,
                $item->productId,
                $item->productAttributeId,
                false,
                'up',
                $subscription->deliveryAddressId,
                $shop,
                false,
                false
            );

            if (!$updated) {
                throw new RuntimeException('Recurring renewal product could not be added to the cart.');
            }
        }

        if (!(bool) $cart->isVirtualCart()) {
            throw new RuntimeException('Recurring renewal cart is no longer virtual.');
        }

        if (!PaymentAmount::matches($subscription->amount, (float) $cart->getOrderTotal(true, Cart::BOTH))) {
            throw new RuntimeException('Recurring renewal cart amount no longer matches the subscription.');
        }

        return $cart;
    }

    private function configureContext(RecurringSubscription $subscription, Customer $customer, Shop $shop): void
    {
        $context = Context::getContext();
        $context->customer = $customer;
        $context->shop = $shop;
        $context->currency = new Currency($subscription->currencyId);
        $context->language = new Language($subscription->languageId);
    }
}
