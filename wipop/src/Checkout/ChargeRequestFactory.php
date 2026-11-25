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

use Address;
use Cart;
use Context;
use Currency;
use Customer;
use PrestaShop\Module\Wipop\Card\CardCofChargeConfigurator;
use PrestaShop\Module\Wipop\Card\CardCofSelection;
use PrestaShop\Module\Wipop\Configuration\Settings;
use Validate;
use Wipop\Domain\Input\Customer as WipopCustomer;
use Wipop\Domain\Input\OriginChannel;
use Wipop\Domain\ProductType;
use Wipop\Domain\Value\OrderId;
use Wipop\Domain\Value\Terminal;
use Wipop\Operations\Charge\Params\CreateChargeParams;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ChargeRequestFactory
{
    public function build(
        Cart $cart,
        string $method,
        string $redirectUrl,
        Settings $settings,
        string $wipopOrderId,
        bool $captureImmediately = true,
        ?CardCofSelection $cofSelection = null,
    ): CreateChargeParams {
        $currency = new Currency((int) $cart->id_currency);
        $params = (new CreateChargeParams())
            ->method($method)
            ->amount(round((float) $cart->getOrderTotal(true, Cart::BOTH), 2))
            ->currency((string) $currency->iso_code)
            ->description($this->buildDescription($cart))
            ->redirectUrl($redirectUrl)
            ->orderId(new OrderId($wipopOrderId))
            ->productType(ProductType::PAYMENT_GATEWAY)
            ->originChannel(OriginChannel::CHECKOUT)
            ->language(WipopLanguage::fromPrestashopLocale($this->resolveLocale())->value)
            ->terminal(new Terminal($settings->terminalId))
            ->capture($captureImmediately);

        $customer = $this->buildCustomer($cart);
        if ($customer !== null) {
            $params->customer($customer);
        }

        if ($cofSelection !== null) {
            (new CardCofChargeConfigurator())->apply($params, $cofSelection);
        }

        return $params;
    }

    private function buildDescription(Cart $cart): string
    {
        $shopName = trim((string) \Configuration::get('PS_SHOP_NAME'));

        if ($shopName === '') {
            return sprintf('Cart #%d', (int) $cart->id);
        }

        return sprintf('Cart #%1$d at %2$s', (int) $cart->id, $shopName);
    }

    private function buildCustomer(Cart $cart): ?WipopCustomer
    {
        $customer = new Customer((int) $cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            return null;
        }

        $email = trim((string) $customer->email);
        if ($email === '') {
            return null;
        }

        $firstName = trim((string) $customer->firstname);
        $lastName = trim((string) $customer->lastname);

        if ($firstName === '') {
            $firstName = 'Customer';
        }

        if ($lastName === '') {
            $lastName = 'PrestaShop';
        }

        $address = $this->resolveAddressObject($cart);
        $externalCustomerId = (int) $customer->id > 0 ? (string) (int) $customer->id : $email;

        return new WipopCustomer(
            $firstName,
            $lastName,
            $email,
            null,
            $externalCustomerId,
            $this->resolvePhone($address),
            null,
        );
    }

    private function resolvePhone(?Address $address): ?string
    {
        if ($address === null) {
            return null;
        }

        $phone = trim((string) ($address->phone_mobile ?: $address->phone));

        return $phone === '' ? null : $phone;
    }

    private function resolveAddressObject(Cart $cart): ?Address
    {
        $addressId = (int) ($cart->id_address_invoice ?: $cart->id_address_delivery);
        if ($addressId <= 0) {
            return null;
        }

        $address = new Address($addressId);
        if (!Validate::isLoadedObject($address)) {
            return null;
        }

        return $address;
    }

    private function resolveLocale(): string
    {
        $language = Context::getContext()->language;
        if ($language === null || !is_string($language->locale)) {
            return '';
        }

        return $language->locale;
    }
}
