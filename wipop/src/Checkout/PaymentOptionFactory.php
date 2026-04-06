<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Checkout;

use Cart;
use Context;
use Currency;
use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use PrestaShop\Module\Wipop\TranslationDomains;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use Wipop\Domain\ChargeMethod;

final class PaymentOptionFactory
{
    private const CHARGE_METHODS = [
        ChargeMethod::CARD,
        ChargeMethod::BIZUM,
    ]; // add GOOGLE_PAY when available

    public function __construct(private SettingsRepository $settingsRepository)
    {
    }

    public static function supportsChargeMethod(string $method): bool
    {
        return in_array($method, self::CHARGE_METHODS, true);
    }

    /**
     * @return PaymentOption[]
     */
    public function buildOptions(\Wipop $module, Cart $cart): array
    {
        if (!$module->active) {
            return [];
        }

        if (!$this->settingsRepository->get()->isConfigured()) {
            return [];
        }

        if (!$this->supportsCurrency($module, $cart)) {
            return [];
        }

        $options = [];

        foreach (self::CHARGE_METHODS as $method) {
            $options[] = $this->buildOption($module, $method);
        }

        return $options;
    }

    private function buildOption(\Wipop $module, string $method): PaymentOption
    {
        $option = new PaymentOption();
        $option->setCallToActionText($this->label($method))
            ->setAction(Context::getContext()->link->getModuleLink(
                $module->name,
                'start',
                ['method' => $method],
                true
            ));

        return $option;
    }

    private function label(string $method): string
    {
        $translator = Context::getContext()->getTranslator();

        return match ($method) {
            ChargeMethod::CARD => $translator->trans('Pay by card with Wipop', [], TranslationDomains::SHOP),
            ChargeMethod::BIZUM => $translator->trans('Pay with Bizum via Wipop', [], TranslationDomains::SHOP),
            default => $translator->trans('Pay with Wipop', [], TranslationDomains::SHOP),
        };
    }

    private function supportsCurrency(\Wipop $module, Cart $cart): bool
    {
        $currencyOrder = new Currency((int) $cart->id_currency);
        $currenciesModule = $module->getCurrency((int) $cart->id_currency);

        if (!is_array($currenciesModule)) {
            return false;
        }

        foreach ($currenciesModule as $currencyModule) {
            if (!$this->matchesCurrency($currencyOrder, $currencyModule)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param mixed $currencyModule
     */
    private function matchesCurrency(Currency $currencyOrder, mixed $currencyModule): bool
    {
        if (!is_array($currencyModule)) {
            return false;
        }

        if (!isset($currencyModule['id_currency'])) {
            return false;
        }

        return (int) $currencyOrder->id === (int) $currencyModule['id_currency'];
    }
}
