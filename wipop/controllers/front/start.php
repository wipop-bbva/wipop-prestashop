<?php

/**
 * 2007-2025 PrestaShop
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
 *  @copyright 2007-2025 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

require_once dirname(__DIR__, 2) . '/autoload.php';

use PrestaShop\Module\Wipop\Api\ClientFactory;
use PrestaShop\Module\Wipop\Api\MerchantOperationsService;
use PrestaShop\Module\Wipop\Api\SdkCaller;
use PrestaShop\Module\Wipop\Card\CardCofException;
use PrestaShop\Module\Wipop\Card\CardCofSelectionResolver;
use PrestaShop\Module\Wipop\Card\StoredCardRepository;
use PrestaShop\Module\Wipop\Checkout\CartOrderIdFactory;
use PrestaShop\Module\Wipop\Checkout\ChargeRequestFactory;
use PrestaShop\Module\Wipop\Checkout\ChargeStarter;
use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use PrestaShop\Module\Wipop\Customer\LoggedCartCustomerResolver;
use PrestaShop\Module\Wipop\Exception\ApiCallException;
use PrestaShop\Module\Wipop\Exception\ClientConfigurationException;
use PrestaShop\Module\Wipop\Exception\SettingsReadException;
use PrestaShop\Module\Wipop\Log\Logger;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptRepository;
use PrestaShop\Module\Wipop\TranslationDomains;

class WipopStartModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        $logger = $this->logger();
        $paymentMethod = strtoupper(trim((string) Tools::getValue('method')));

        if (!$this->isTokenValid()) {
            $logger->warning('Invalid token received on Wipop start controller.', [
                'payment_method' => $paymentMethod,
            ]);
            $this->renderUnavailable($this->translateMessage('Invalid checkout session.'));

            return;
        }

        if (!MerchantOperationsService::supportsGateway($paymentMethod)) {
            $logger->warning('Unsupported payment method received on start controller.');
            $this->renderUnavailable($this->translateMessage('Unsupported Wipop payment method.'));

            return;
        }

        try {
            $settings = $this->settingsRepository()->get();
        } catch (SettingsReadException $exception) {
            $logger->error('Wipop settings storage error: ' . $exception->getMessage(), [
                'payment_method' => $paymentMethod,
                'error_class' => $exception::class,
            ]);
            $this->renderUnavailable($this->translateMessage('Configuration required.'));

            return;
        }

        if (!$settings->isConfigured()) {
            $logger->warning('Wipop start controller reached without complete credentials.', [
                'payment_method' => $paymentMethod,
            ]);
            $this->renderUnavailable($this->translateMessage('Configuration required.'));

            return;
        }

        $cart = $this->context->cart;
        if (!Validate::isLoadedObject($cart) || (int) $cart->id <= 0) {
            $logger->error('Wipop start controller reached without cart.', [
                'payment_method' => $paymentMethod,
            ]);
            $this->renderUnavailable($this->translateMessage('A valid cart is required.'));

            return;
        }

        $merchantOperationsService = $this->merchantOperationsService();

        try {
            if (!in_array($paymentMethod, $merchantOperationsService->getCheckoutGatewaysFor($settings), true)) {
                $logger->warning('Unavailable payment method received on start controller.', [
                    'payment_method' => $paymentMethod,
                ]);
                $this->renderUnavailable($this->translateMessage('Unsupported Wipop payment method.'));

                return;
            }
        } catch (ClientConfigurationException | ApiCallException) {
            $logger->warning('Wipop start controller could not verify available payment methods.', [
                'payment_method' => $paymentMethod,
            ]);
            $this->renderUnavailable($this->translateMessage('Unable to verify the available payment methods.'));

            return;
        }

        try {
            $cofSelection = $this->cardCofSelectionResolver()->resolve(
                $paymentMethod,
                (new LoggedCartCustomerResolver())->resolveIdForCart($cart),
                (int) $cart->id_shop,
                $settings->merchantId,
                $settings->liveMode,
                $settings->terminalId,
                $this->selectedStoredCardId(),
                (bool) Tools::getValue('wipop_save_card')
            );
            $paymentAttemptRepository = $this->paymentAttemptRepository();
            $attemptId = $paymentAttemptRepository->createForCart(
                $cart,
                $paymentMethod,
                $settings,
                $cofSelection,
            );
            $wipopOrderId = (new CartOrderIdFactory())->fromCartAttempt($cart, $attemptId);
            $paymentAttemptRepository->assignWipopOrderId($attemptId, $wipopOrderId);

            $logger->info('Wipop payment attempt init.', [
                'attempt_id' => $attemptId,
                'cart_id' => (int) $cart->id,
                'payment_method' => $paymentMethod,
                'wipop_order_id' => $wipopOrderId,
            ]);

            $redirectUrl = $this->chargeStarter()->start(
                $cart,
                $paymentMethod,
                $this->returnUrl(),
                $settings,
                $wipopOrderId,
                $attemptId,
                $cofSelection,
            );

            Tools::redirect($redirectUrl);

            return;
        } catch (CardCofException | ClientConfigurationException | ApiCallException $exception) {
            $logger->error('Wipop checkout start failed.', [
                'payment_method' => $paymentMethod,
                'error_class' => $exception::class,
            ]);
            $this->renderUnavailable($exception->getMessage());

            return;
        } catch (Throwable $throwable) {
            $logger->error('Unexpected Wipop checkout start failure.', [
                'payment_method' => $paymentMethod,
                'error_class' => $throwable::class,
            ]);
            $this->renderUnavailable($this->translateMessage('Unable to start the Wipop checkout.'));
        }
    }

    private function renderUnavailable(string $message): void
    {
        $this->context->smarty->assign([
            'checkout_url' => $this->context->link->getPageLink('order', true, null, 'step=3'),
            'message' => $message,
            'title' => $this->translateMessage('Wipop unavailable'),
        ]);

        $this->setTemplate('module:wipop/views/templates/front/unavailable.tpl');
    }

    private function logger(): Logger
    {
        return new Logger();
    }

    private function settingsRepository(): SettingsRepository
    {
        return new SettingsRepository();
    }

    private function paymentAttemptRepository(): PaymentAttemptRepository
    {
        return PaymentAttemptRepository::createDefault();
    }

    private function chargeStarter(): ChargeStarter
    {
        $logger = $this->logger();

        return new ChargeStarter(
            new ClientFactory(),
            new SdkCaller($logger),
            new ChargeRequestFactory(),
            $this->paymentAttemptRepository(),
            $logger,
        );
    }

    private function cardCofSelectionResolver(): CardCofSelectionResolver
    {
        return new CardCofSelectionResolver(StoredCardRepository::createDefault());
    }

    private function merchantOperationsService(): MerchantOperationsService
    {
        return new MerchantOperationsService(
            $this->settingsRepository(),
            new ClientFactory(),
            new SdkCaller($this->logger()),
        );
    }

    private function returnUrl(): string
    {
        return $this->context->link->getModuleLink($this->module->name, 'return', [], true);
    }

    private function selectedStoredCardId(): ?int
    {
        $selectedCardId = (int) Tools::getValue('wipop_saved_card_id');

        return $selectedCardId > 0 ? $selectedCardId : null;
    }

    private function translateMessage(string $message, array $parameters = []): string
    {
        return $this->context->getTranslator()->trans($message, $parameters, TranslationDomains::SHOP);
    }
}
