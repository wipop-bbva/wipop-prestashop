<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Checkout;

use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

final class PaymentOptionFactory
{
    public function __construct(private SettingsRepository $settingsRepository)
    {
    }

    /**
     * @return PaymentOption[]
     */
    public function buildOptions(\Wipop $module, \Cart $cart): array
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

        foreach (PaymentMethod::checkoutMethods() as $method) {
            $options[] = $this->buildOption($module, $method);
        }

        return $options;
    }

    private function buildOption(\Wipop $module, PaymentMethod $method): PaymentOption
    {
        $option = new PaymentOption();
        $option->setCallToActionText($this->label($module, $method))
            ->setAction(\Context::getContext()->link->getModuleLink(
                $module->name,
                'start',
                ['method' => $method->value],
                true
            ));

        return $option;
    }

    private function label(\Wipop $module, PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::CARD => $module->l('Pay by card with Wipöp'),
            PaymentMethod::BIZUM => $module->l('Pay with Bizum via Wipöp'),
        };
    }

    private function supportsCurrency(\Wipop $module, \Cart $cart): bool
    {
        $currencyOrder = new \Currency((int) $cart->id_currency);
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
    private function matchesCurrency(\Currency $currencyOrder, mixed $currencyModule): bool
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
