<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Checkout;

use Address;
use Cart;
use Context;
use Country;
use Currency;
use Customer;
use PrestaShop\Module\Wipop\Configuration\Settings;
use State;
use Validate;
use Wipop\Domain\Input\Address as WipopAddress;
use Wipop\Domain\Input\Customer as WipopCustomer;
use Wipop\Domain\Input\OriginChannel;
use Wipop\Domain\Language;
use Wipop\Domain\ProductType;
use Wipop\Domain\Value\OrderId;
use Wipop\Domain\Value\Terminal;
use Wipop\Operations\Charge\Params\CreateChargeParams;

final class ChargeRequestFactory
{
    public function build(
        Cart $cart,
        string $method,
        string $redirectUrl,
        Settings $settings,
        string $wipopOrderId,
        bool $captureImmediately = true,
    ): CreateChargeParams {
        $currency = new Currency((int) $cart->id_currency);
        $params = (new CreateChargeParams())
            ->method($method)
            ->amount((float) $cart->getOrderTotal(true, Cart::BOTH))
            ->currency((string) $currency->iso_code)
            ->description($this->buildDescription($cart))
            ->redirectUrl($redirectUrl)
            ->orderId(new OrderId($wipopOrderId))
            ->productType(ProductType::PAYMENT_GATEWAY)
            ->originChannel(OriginChannel::CHECKOUT)
            ->language($this->resolveLanguage())
            ->terminal(new Terminal($settings->terminalId))
            ->capture($captureImmediately);

        $customer = $this->buildCustomer($cart);
        if ($customer !== null) {
            $params->customer($customer);
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
            $this->buildAddress($address),
        );
    }

    private function buildAddress(?Address $address): ?WipopAddress
    {
        if ($address === null) {
            return null;
        }

        $addressParts = [
            trim((string) $address->address1),
            trim((string) $address->address2),
        ];
        $addressLine = implode(' ', array_filter($addressParts));
        $zipCode = trim((string) $address->postcode);
        $city = trim((string) $address->city);
        $state = $this->resolveState($address);
        $countryCode = $this->resolveCountryCode($address);

        if ($addressLine === '' && $zipCode === '' && $city === '' && $countryCode === '') {
            return null;
        }

        return new WipopAddress(
            $addressLine,
            $zipCode,
            $city,
            $state,
            $countryCode,
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

    private function resolveState(Address $address): string
    {
        if ((int) $address->id_state <= 0) {
            return '';
        }

        $state = new State((int) $address->id_state);
        if (!Validate::isLoadedObject($state)) {
            return '';
        }

        $name = trim((string) $state->name);
        if ($name !== '') {
            return $name;
        }

        return trim((string) $state->iso_code);
    }

    private function resolveCountryCode(Address $address): string
    {
        if ((int) $address->id_country <= 0) {
            return '';
        }

        $country = new Country((int) $address->id_country);
        if (!Validate::isLoadedObject($country)) {
            return '';
        }

        return trim((string) $country->iso_code);
    }

    private function resolveLanguage(): string
    {
        $language = Context::getContext()->language;
        if ($language === null || !is_string($language->locale)) {
            return Language::SPANISH;
        }

        $locale = trim($language->locale);
        if ($locale === '') {
            return Language::SPANISH;
        }

        return str_replace('_', '-', $locale);
    }
}
