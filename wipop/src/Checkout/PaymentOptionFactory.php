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

use Cart;
use Context;
use PrestaShop\Module\Wipop\Api\MerchantOperationsService;
use PrestaShop\Module\Wipop\Card\StoredCardPresenter;
use PrestaShop\Module\Wipop\Card\StoredCardRepository;
use PrestaShop\Module\Wipop\Configuration\Settings;
use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use PrestaShop\Module\Wipop\Customer\LoggedCartCustomerResolver;
use PrestaShop\Module\Wipop\Exception\ApiCallException;
use PrestaShop\Module\Wipop\Exception\ClientConfigurationException;
use PrestaShop\Module\Wipop\Exception\SettingsReadException;
use PrestaShop\Module\Wipop\Recurring\RecurringCartInspector;
use PrestaShop\Module\Wipop\Recurring\RecurringCartSummary;
use PrestaShop\Module\Wipop\Recurring\RecurringCheckoutPolicy;
use PrestaShop\Module\Wipop\TranslationDomains;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tools;
use Wipop\Domain\ChargeMethod;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PaymentOptionFactory
{
    public function __construct(
        private MerchantOperationsService $merchantOperationsService,
        private SettingsRepository $settingsRepository,
        private StoredCardRepository $storedCardRepository,
        private RecurringCartInspector $recurringCartInspector,
        private RecurringCheckoutPolicy $recurringCheckoutPolicy,
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
            $settings = $this->settingsRepository->get();
            $availableGateways = $this->merchantOperationsService->getCheckoutGatewaysFor($settings);
            $recurringCart = $this->recurringCartInspector->inspect($cart);
        } catch (SettingsReadException | ClientConfigurationException | ApiCallException) {
            return [];
        }

        $customerId = (new LoggedCartCustomerResolver())->resolveIdForCart($cart);
        $availableGateways = $this->recurringCheckoutPolicy->availableGateways($recurringCart, $availableGateways);

        return array_map(
            fn (string $method): PaymentOption => $this->buildOption(
                $module,
                $cart,
                $method,
                $settings,
                $recurringCart,
                $customerId
            ),
            $availableGateways,
        );
    }

    private function buildOption(
        \Wipop $module,
        Cart $cart,
        string $method,
        Settings $settings,
        RecurringCartSummary $recurringCart,
        ?int $customerId,
    ): PaymentOption {
        $actionUrl = Context::getContext()->link->getModuleLink(
            $module->name,
            'start',
            ['method' => $method],
            true
        );
        $option = new PaymentOption();
        $option->setCallToActionText($this->label($method))
            ->setAction($actionUrl);

        if ($method === ChargeMethod::CARD) {
            $option->setForm($this->cardForm($module, $cart, $settings, $actionUrl, $recurringCart, $customerId));

            return $option;
        }

        $option->setInputs([
            [
                'name' => 'token',
                'type' => 'hidden',
                'value' => Tools::getToken(false),
            ],
        ]);

        return $option;
    }

    private function cardForm(
        \Wipop $module,
        Cart $cart,
        Settings $settings,
        string $actionUrl,
        RecurringCartSummary $recurringCart,
        ?int $customerId,
    ): string {
        $cards = [];
        $isRecurring = $this->recurringCheckoutPolicy->requiresReusableCard($recurringCart);

        if ($customerId !== null) {
            $presenter = new StoredCardPresenter();
            $storedCards = $this->storedCardRepository->findActiveByCustomer(
                $customerId,
                (int) $cart->id_shop,
                $settings->merchantId,
                $settings->liveMode,
                $settings->terminalId
            );

            foreach ($storedCards as $card) {
                $cards[] = $presenter->present($card);
            }
        }

        Context::getContext()->smarty->assign([
            'wipopCardForm' => [
                'action' => $actionUrl,
                'token' => Tools::getToken(false),
                'is_logged' => $customerId !== null,
                'saved_cards' => $cards,
                'is_recurring' => $isRecurring,
                'requires_login' => $isRecurring && $customerId === null,
            ],
        ]);

        return $module->fetch('module:wipop/views/templates/front/checkout_card_form.tpl');
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
