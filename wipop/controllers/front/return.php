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

use PrestaShop\Module\Wipop\Log\Logger;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptRepository;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use PrestaShop\Module\Wipop\TranslationDomains;

class WipopReturnModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        $logger = new Logger();
        $cartId = $this->cartId();
        $pendingMessage = $this->translateMessage(
            'Your Wipop payment is being confirmed. '
            . 'Your order will be confirmed automatically when the payment is complete.'
        );

        if ($cartId === null) {
            $logger->warning('Wipop return endpoint reached without cart.');
            $this->redirectToCheckoutWithWarning($pendingMessage);

            return;
        }

        $logger->info('Wipop return endpoint reached.', [
            'cart_id' => $cartId,
        ]);

        $attempt = PaymentAttemptRepository::createDefault()->findLatestByCartId($cartId);
        if ($attempt !== null && $attempt->orderId !== null) {
            Tools::redirect($this->orderConfirmationUrl($attempt));

            return;
        }

        if ($attempt?->status === PaymentAttemptStatus::FAILED) {
            $this->redirectToCheckoutWithError(
                $this->translateMessage('The Wipop payment was not completed. Please return to checkout and try again.')
            );

            return;
        }

        $this->redirectToCheckoutWithWarning($pendingMessage);
    }

    private function redirectToCheckoutWithError(string $message): void
    {
        $this->errors[] = $message;

        $this->redirectWithNotifications($this->checkoutUrl());
    }

    private function redirectToCheckoutWithWarning(string $message): void
    {
        $this->warning[] = $message;

        $this->redirectWithNotifications($this->checkoutUrl());
    }

    private function checkoutUrl(): string
    {
        return $this->context->link->getPageLink('order', true, null, 'step=3');
    }

    private function cartId(): ?int
    {
        if (!isset($this->context->cart)) {
            return null;
        }

        if (!Validate::isLoadedObject($this->context->cart)) {
            return null;
        }

        $cartId = (int) $this->context->cart->id;

        return $cartId > 0 ? $cartId : null;
    }

    private function orderConfirmationUrl(PaymentAttempt $attempt): string
    {
        $order = new Order((int) $attempt->orderId);

        return $this->context->link->getPageLink(
            'order-confirmation',
            true,
            null,
            [
                'id_cart' => $attempt->cartId,
                'id_module' => (int) $this->module->id,
                'id_order' => (int) $attempt->orderId,
                'key' => (string) $order->secure_key,
            ]
        );
    }

    private function translateMessage(string $message, array $parameters = []): string
    {
        return $this->context->getTranslator()->trans($message, $parameters, TranslationDomains::SHOP);
    }
}
