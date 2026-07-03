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
if (!defined('_PS_VERSION_')) {
    exit;
}

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
use PrestaShop\Module\Wipop\Recurring\ProductRecurringSettingsRepository;
use PrestaShop\Module\Wipop\Recurring\RecurringCartInspector;
use PrestaShop\Module\Wipop\Recurring\RecurringCheckoutPolicy;
use PrestaShop\Module\Wipop\Recurring\RecurringSubscriptionRepository;
use PrestaShop\Module\Wipop\TranslationDomains;

class WipopStartModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        $logger = new Logger();
        $method = Tools::getValue('method', '');
        $paymentMethod = is_string($method) ? strtoupper(trim($method)) : '';

        if (!$this->isTokenValid()) {
            $logger->warning('Invalid token received on Wipop start controller.', [
                'payment_method' => $paymentMethod,
            ]);
            $this->redirectToCheckoutWithError($this->translateMessage('Invalid checkout session.'));

            return;
        }

        if (!MerchantOperationsService::supportsGateway($paymentMethod)) {
            $logger->warning('Unsupported payment method received on start controller.');
            $this->redirectToCheckoutWithError($this->translateMessage('Unsupported Wipop payment method.'));

            return;
        }

        $settingsRepository = new SettingsRepository();
        try {
            $settings = $settingsRepository->get();
        } catch (SettingsReadException $exception) {
            $logger->error('Wipop settings storage error: ' . $exception->getMessage(), [
                'payment_method' => $paymentMethod,
                'error_class' => $exception::class,
            ]);
            $this->redirectToCheckoutWithError($this->translateMessage('Configuration required.'));

            return;
        }

        if (!$settings->isConfigured()) {
            $logger->warning('Wipop start controller reached without complete credentials.', [
                'payment_method' => $paymentMethod,
            ]);
            $this->redirectToCheckoutWithError($this->translateMessage('Configuration required.'));

            return;
        }

        $cart = $this->context->cart;
        if (!Validate::isLoadedObject($cart) || (int) $cart->id <= 0) {
            $logger->error('Wipop start controller reached without cart.', [
                'payment_method' => $paymentMethod,
            ]);
            $this->redirectToCheckoutWithError($this->translateMessage('A valid cart is required.'));

            return;
        }

        $recurringCart = (new RecurringCartInspector(
            ProductRecurringSettingsRepository::createDefault()
        ))->inspect($cart);
        $recurringPolicy = new RecurringCheckoutPolicy();
        $startError = $recurringPolicy->startError($recurringCart, $paymentMethod);

        if ($startError !== null) {
            $this->redirectToCheckoutWithError($this->translateMessage($startError));

            return;
        }

        $merchantOperationsService = new MerchantOperationsService(
            $settingsRepository,
            new ClientFactory(),
            new SdkCaller($logger)
        );

        try {
            if (!in_array($paymentMethod, $merchantOperationsService->getCheckoutGatewaysFor($settings), true)) {
                $logger->warning('Unavailable payment method received on start controller.', [
                    'payment_method' => $paymentMethod,
                ]);
                $this->redirectToCheckoutWithError($this->translateMessage('Unsupported Wipop payment method.'));

                return;
            }
        } catch (ClientConfigurationException | ApiCallException) {
            $logger->warning('Wipop start controller could not verify available payment methods.', [
                'payment_method' => $paymentMethod,
            ]);
            $this->redirectToCheckoutWithError($this->translateMessage(
                'Unable to verify the available payment methods.'
            ));

            return;
        }

        try {
            $cofSelection = (new CardCofSelectionResolver(StoredCardRepository::createDefault()))->resolve(
                $paymentMethod,
                (new LoggedCartCustomerResolver())->resolveIdForCart($cart),
                (int) $cart->id_shop,
                $settings->merchantId,
                $settings->liveMode,
                $settings->terminalId,
                $this->selectedStoredCardId(),
                Tools::getValue('wipop_save_card') === '1',
                $recurringPolicy->requiresReusableCard($recurringCart)
            );
            $paymentAttemptRepository = PaymentAttemptRepository::createDefault();
            $attemptId = $paymentAttemptRepository->createForCart(
                $cart,
                $paymentMethod,
                $settings,
                $cofSelection,
                $recurringPolicy->captureModeOverride($recurringCart),
            );
            $wipopOrderId = (new CartOrderIdFactory())->fromCartAttempt($cart, $attemptId);
            $paymentAttemptRepository->assignWipopOrderId($attemptId, $wipopOrderId);
            if ($recurringPolicy->requiresReusableCard($recurringCart)) {
                RecurringSubscriptionRepository::createDefault()->createPendingFromCart(
                    $attemptId,
                    $cart,
                    $settings,
                    $cofSelection->storedCardId(),
                    $recurringCart->groups
                );
            }

            $logger->info('Wipop payment attempt init.', [
                'attempt_id' => $attemptId,
                'cart_id' => (int) $cart->id,
                'payment_method' => $paymentMethod,
                'wipop_order_id' => $wipopOrderId,
            ]);

            $redirectUrl = $this->chargeStarter($logger, $paymentAttemptRepository)->start(
                $cart,
                $paymentMethod,
                $this->returnUrl(),
                $settings,
                $wipopOrderId,
                $attemptId,
                $cofSelection,
                $recurringPolicy->shouldCaptureImmediately($recurringCart, $settings, $paymentMethod),
                $recurringPolicy->usesRecurringPostType($recurringCart),
            );

            Tools::redirect($redirectUrl);

            return;
        } catch (CardCofException $exception) {
            $logger->error('Wipop checkout start failed.', [
                'payment_method' => $paymentMethod,
                'error_class' => $exception::class,
            ]);
            $this->redirectToCheckoutWithError($this->translateMessage($exception->getMessage()));

            return;
        } catch (ClientConfigurationException | ApiCallException $exception) {
            $logger->error('Wipop checkout start failed.', [
                'payment_method' => $paymentMethod,
                'error_class' => $exception::class,
            ]);
            $this->redirectToCheckoutWithError($this->translateMessage(
                'We could not start the Wipop payment. '
                    . 'Please return to checkout and try again or use another payment method.'
            ));

            return;
        } catch (Throwable $throwable) {
            $logger->error('Unexpected Wipop checkout start failure.', [
                'payment_method' => $paymentMethod,
                'error_class' => $throwable::class,
            ]);
            $this->redirectToCheckoutWithError($this->translateMessage('Unable to start the Wipop checkout.'));
        }
    }

    private function redirectToCheckoutWithError(string $message): void
    {
        $this->errors[] = $message;

        $this->redirectWithNotifications($this->context->link->getPageLink('order', true, null, 'step=3'));
    }

    private function chargeStarter(Logger $logger, PaymentAttemptRepository $paymentAttemptRepository): ChargeStarter
    {
        return new ChargeStarter(
            new ClientFactory(),
            new SdkCaller($logger),
            new ChargeRequestFactory(),
            $paymentAttemptRepository,
            $logger,
        );
    }

    private function returnUrl(): string
    {
        return $this->context->link->getModuleLink($this->module->name, 'return', [], true);
    }

    private function selectedStoredCardId(): ?int
    {
        $selectedCardId = Tools::getValue('wipop_saved_card_id');
        if (!is_numeric($selectedCardId)) {
            return null;
        }

        $selectedCardId = (int) $selectedCardId;

        return $selectedCardId > 0 ? $selectedCardId : null;
    }

    private function translateMessage(string $message, array $parameters = []): string
    {
        return $this->context->getTranslator()->trans($message, $parameters, TranslationDomains::SHOP);
    }
}
