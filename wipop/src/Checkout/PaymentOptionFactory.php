<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Checkout;

use Cart;
use Context;
use PrestaShop\Module\Wipop\Api\MerchantOperationsService;
use PrestaShop\Module\Wipop\Exception\ApiCallException;
use PrestaShop\Module\Wipop\Exception\ClientConfigurationException;
use PrestaShop\Module\Wipop\TranslationDomains;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tools;
use Wipop\Domain\ChargeMethod;

final class PaymentOptionFactory
{
    public function __construct(
        private MerchantOperationsService $merchantOperationsService,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return PaymentOption[]
     */
    public function buildOptions(\Wipop $module, Cart $cart): array
    {
        if (!$module->active) {
            return [];
        }

        if (!$this->supportsCurrency($module, $cart)) {
            return [];
        }

        try {
            $availableGateways = $this->merchantOperationsService->getAvailableGateways();
        } catch (ClientConfigurationException | ApiCallException) {
            return [];
        }

        return array_map(
            fn (string $method): PaymentOption => $this->buildOption($module, $method),
            $availableGateways,
        );
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
            ))
            ->setInputs([
                [
                    'name' => 'token',
                    'type' => 'hidden',
                    'value' => Tools::getToken(false),
                ],
            ]);

        return $option;
    }

    private function label(string $method): string
    {
        return match ($method) {
            ChargeMethod::CARD => $this->translator->trans('Pay by card with Wipop', [], TranslationDomains::SHOP),
            ChargeMethod::BIZUM => $this->translator->trans('Pay with Bizum via Wipop', [], TranslationDomains::SHOP),
            default => $this->translator->trans('Pay with Wipop', [], TranslationDomains::SHOP),
        };
    }

    private function supportsCurrency(\Wipop $module, Cart $cart): bool
    {
        $currencyId = (int) $cart->id_currency;
        $currenciesModule = $module->getCurrency((int) $cart->id_currency);

        if (!is_array($currenciesModule)) {
            return false;
        }

        foreach ($currenciesModule as $currencyModule) {
            if (!$this->matchesCurrency($currencyId, $currencyModule)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param mixed $currencyModule
     */
    private function matchesCurrency(int $currencyId, mixed $currencyModule): bool
    {
        if (!is_array($currencyModule)) {
            return false;
        }

        if (!isset($currencyModule['id_currency'])) {
            return false;
        }

        return $currencyId === (int) $currencyModule['id_currency'];
    }
}
