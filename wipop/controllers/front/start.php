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

use PrestaShop\Module\Wipop\Checkout\CartOrderIdFactory;
use PrestaShop\Module\Wipop\Checkout\PaymentOptionFactory;
use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use PrestaShop\Module\Wipop\Log\Logger;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptRepository;

class WipopStartModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $paymentMethod = trim((string) Tools::getValue('method'));
        if (!PaymentOptionFactory::supportsChargeMethod($paymentMethod)) {
            $this->logger()->warning('Unsupported payment method received on start controller.');
            $this->renderUnavailable($this->translateMessage('Unsupported Wipöp payment method.'));

            return;
        }

        $settings = $this->settingsRepository()->get();
        if (!$settings->isConfigured()) {
            $this->logger()->warning('Wipöp start controller reached without complete credentials.', [
                'payment_method' => $paymentMethod,
            ]);
            $this->renderUnavailable($this->translateMessage('Configuration required.'));

            return;
        }

        if (!Validate::isLoadedObject($this->context->cart) || (int) $this->context->cart->id <= 0) {
            $this->logger()->error('Wipöp start controller reached without cart.', [
                'payment_method' => $paymentMethod,
            ]);
            $this->renderUnavailable($this->translateMessage('A valid cart is required.'));

            return;
        }

        $wipopOrderId = (new CartOrderIdFactory())->fromCart($this->context->cart);
        $attemptId = PaymentAttemptRepository::createDefault()->createForCart(
            $this->context->cart,
            $paymentMethod,
            $settings,
            $wipopOrderId
        );

        $this->logger()->info('Wipöp payment attempt init.', [
            'attempt_id' => $attemptId,
            'cart_id' => (int) $this->context->cart->id,
            'payment_method' => $paymentMethod,
            'wipop_order_id' => $wipopOrderId,
        ]);

        $this->renderUnavailable($this->translateMessage('WIP.'));
    }

    private function renderUnavailable(string $message): void
    {
        $this->context->smarty->assign([
            'checkout_url' => $this->context->link->getPageLink('order', true, null, 'step=3'),
            'message' => $message,
            'title' => $this->translateMessage('WIP'),
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

    private function translateMessage(string $message, array $parameters = []): string
    {
        return $this->module->trans($message, $parameters, Wipop::TRANSLATION_DOMAIN_SHOP);
    }
}
