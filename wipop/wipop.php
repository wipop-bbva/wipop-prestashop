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
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/autoload.php';

use PrestaShop\Module\Wipop\Api\ClientFactory;
use PrestaShop\Module\Wipop\Api\MerchantOperationsService;
use PrestaShop\Module\Wipop\Api\SdkCaller;
use PrestaShop\Module\Wipop\Card\StoredCardRepository;
use PrestaShop\Module\Wipop\Checkout\PaymentOptionFactory;
use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use PrestaShop\Module\Wipop\Install\ModuleInstaller;
use PrestaShop\Module\Wipop\Install\ModuleUninstaller;
use PrestaShop\Module\Wipop\Log\Logger;
use PrestaShop\Module\Wipop\Order\OrderWipopPaymentPresenter;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptRepository;
use PrestaShop\Module\Wipop\Payment\PaymentEventRepository;
use PrestaShop\Module\Wipop\Refund\ManualRefundEligibilityChecker;
use PrestaShop\Module\Wipop\TranslationDomains;
use PrestaShop\Module\Wipop\Webhook\WebhookSettingsRepository;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use PrestaShop\PrestaShop\Core\Action\ActionsBarButton;
use PrestaShop\PrestaShop\Core\Action\ActionsBarButtonsCollection;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class Wipop extends PaymentModule
{
    private const WEBHOOK_ROUTE = 'wipop_bbva';

    public function __construct()
    {
        $this->name = 'wipop';
        $this->tab = 'payments_gateways';
        $this->version = '0.3.0';
        $this->author = 'BBVA';
        $this->need_instance = 1;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Wipop', [], TranslationDomains::MAIN);
        $this->description = $this->trans(
            'PrestaShop module that integrates the Wipop payment gateway',
            [],
            TranslationDomains::MAIN
        );

        $this->confirmUninstall = $this->trans(
            'Are you sure you want to uninstall Wipop?',
            [],
            TranslationDomains::MAIN
        );

        $this->ps_versions_compliancy = ['min' => '8.1.0', 'max' => '9.0.0'];
    }

    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->trans(
                'You have to enable the cURL extension on your server to install this module',
                [],
                TranslationDomains::MAIN
            );

            return false;
        }

        return parent::install()
            && (new ModuleInstaller($this->settingsRepository()))->run()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('moduleRoutes')
            && $this->registerHook('displayPaymentReturn')
            && $this->registerHook('displayAdminOrderSide')
            && $this->registerHook('actionGetAdminOrderButtons');
    }

    public function uninstall()
    {
        return (new ModuleUninstaller($this->settingsRepository(), new WebhookSettingsRepository()))->run()
            && parent::uninstall();
    }

    public function getContent()
    {
        Tools::redirectAdmin(
            SymfonyContainer::getInstance()->get('router')->generate('wipop_configuration')
        );

        return '';
    }

    public function hookPaymentOptions(array $params): array
    {
        $cart = $params['cart'] ?? null;

        if (!$cart instanceof Cart) {
            return [];
        }

        return $this->paymentOptionFactory()->buildOptions($this, $cart);
    }

    public function hookDisplayPaymentReturn(array $params): string
    {
        $order = $params['order'] ?? null;
        if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
            return '';
        }

        $summary = $this->paymentAttemptRepository()->findSummaryByOrderId((int) $order->id);
        if ($summary === null) {
            return '';
        }

        $transactionId = trim((string) $summary->wipopTransactionId);

        $this->context->smarty->assign([
            'wipopPaymentReturn' => [
                'amount' => $this->formatPaymentAmount($summary->amount, $summary->currencyIso),
                'order_reference' => (string) $order->reference,
                'transaction_id' => $transactionId === '' ? null : $transactionId,
            ],
        ]);

        return $this->fetch('module:wipop/views/templates/hook/payment_return.tpl');
    }

    public function hookModuleRoutes()
    {
        return [
            'module-wipop-webhook' => [
                'controller' => 'webhook',
                'rule' => self::WEBHOOK_ROUTE,
                'keywords' => [],
                'params' => [
                    'fc' => 'module',
                    'module' => $this->name,
                    'controller' => 'webhook',
                ],
            ],
        ];
    }

    public function hookDisplayAdminOrderSide(array $params): string
    {
        $orderId = $this->orderIdFromHookParams($params);
        if ($orderId === null) {
            return '';
        }

        $summary = $this->paymentAttemptRepository()->findSummaryByOrderId($orderId);
        if ($summary === null) {
            return '';
        }

        $payment = $this->orderWipopPaymentPresenter()->present(
            $summary,
            $this->paymentEventRepository()->findPreauthorizationEventsByAttemptId($summary->id)
        );
        $payment = $this->withPreauthorizationActions($orderId, $payment);

        $this->context->smarty->assign([
            'wipopPayment' => $payment,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/admin_order_wipop.tpl');
    }

    public function hookActionGetAdminOrderButtons(array $params): void
    {
        $orderId = $this->orderIdFromHookParams($params);
        $buttons = $params['actions_bar_buttons_collection'] ?? null;
        if ($orderId === null || !$buttons instanceof ActionsBarButtonsCollection) {
            return;
        }

        $summary = $this->paymentAttemptRepository()->findSummaryByOrderId($orderId);
        if ($summary === null || !$this->manualRefundEligibility()->canRefund($summary)) {
            return;
        }

        $url = SymfonyContainer::getInstance()->get('router')->generate('wipop_order_refund', [
            'orderId' => $orderId,
        ]);

        $title = $this->translatedAdminLabel('Refund with Wipop');
        $label = $this->translatedAdminLabel('Wipop refund');

        $buttons->add(new ActionsBarButton(
            'btn-outline-secondary',
            [
                'href' => $url,
                'title' => $title,
            ],
            '<i class="material-icons">undo</i> ' . $label,
        ));
    }

    private function orderIdFromHookParams(array $params): ?int
    {
        $orderId = $params['id_order'] ?? null;
        if (!is_numeric($orderId)) {
            return null;
        }

        $orderId = (int) $orderId;

        return $orderId > 0 ? $orderId : null;
    }

    private function translatedAdminLabel(string $label): string
    {
        return $this->trans($label, [], TranslationDomains::ADMIN);
    }

    private function formatPaymentAmount(float $amount, string $currencyIso): string
    {
        return sprintf('%s %s', number_format($amount, 2, '.', ''), strtoupper($currencyIso));
    }

    private function paymentOptionFactory(): PaymentOptionFactory
    {
        return new PaymentOptionFactory(
            $this->merchantOperationsService(),
            $this->settingsRepository(),
            StoredCardRepository::createDefault(),
            $this->getTranslator(),
        );
    }

    private function settingsRepository(): SettingsRepository
    {
        return new SettingsRepository();
    }

    private function paymentAttemptRepository(): PaymentAttemptRepository
    {
        return PaymentAttemptRepository::createDefault();
    }

    private function paymentEventRepository(): PaymentEventRepository
    {
        return PaymentEventRepository::createDefault();
    }

    private function orderWipopPaymentPresenter(): OrderWipopPaymentPresenter
    {
        return new OrderWipopPaymentPresenter();
    }

    private function manualRefundEligibility(): ManualRefundEligibilityChecker
    {
        return new ManualRefundEligibilityChecker();
    }

    private function merchantOperationsService(): MerchantOperationsService
    {
        return new MerchantOperationsService(
            $this->settingsRepository(),
            new ClientFactory(),
            new SdkCaller(new Logger()),
        );
    }

    /**
     * @param array<string, bool|string|array<int, array<string, string|bool>>> $payment
     *
     * @return array<string, bool|string|array<int, array<string, string|bool>>>
     */
    private function withPreauthorizationActions(int $orderId, array $payment): array
    {
        if (!$payment['can_manage_preauthorization']) {
            return $payment;
        }

        $router = SymfonyContainer::getInstance()->get('router');
        /** @var CsrfTokenManagerInterface $tokenManager */
        $tokenManager = SymfonyContainer::getInstance()->get('security.csrf.token_manager');

        $payment['preauthorization_capture_url'] = $router->generate('wipop_order_preauthorization_capture', [
            'orderId' => $orderId,
        ]);
        $payment['preauthorization_reverse_url'] = $router->generate('wipop_order_preauthorization_reverse', [
            'orderId' => $orderId,
        ]);
        $payment['preauthorization_csrf_token'] = (string) $tokenManager->getToken(
            sprintf('wipop_order_preauthorization_%d', $orderId)
        );

        return $payment;
    }
}
