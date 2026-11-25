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

namespace PrestaShop\Module\Wipop\Webhook;

use Cart;
use Order;
use PaymentModule;
use PrestaShop\Module\Wipop\Exception\WebhookException;
use PrestaShop\Module\Wipop\Order\OrderPrivateNoteWriter;
use PrestaShop\Module\Wipop\Payment\PaymentAmount;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptRepository;
use PrestaShop\Module\Wipop\Payment\TransactionType;
use RuntimeException;
use Throwable;
use Validate;
use Wipop\Domain\Transaction;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class WebhookOrderSynchronizer
{
    public function __construct(
        private PaymentAttemptRepository $paymentAttemptRepository,
        private PaymentModule $module,
        private WebhookPaymentValidator $paymentValidator,
        private OrderPrivateNoteWriter $orderNotes,
    ) {
    }

    /**
     * @throws WebhookException
     */
    public function sync(PaymentAttempt $attempt, Transaction $transaction, int $orderStateId): void
    {
        if (!TransactionType::isChargeOrMissing($transaction->transactionType)) {
            return;
        }

        $this->paymentValidator->assertTransactionMatches($attempt, $transaction);

        if ($attempt->orderId !== null) {
            return;
        }

        /* Webhook retries may arrive after PrestaShop created the order but before the attempt was linked. */
        $existingOrderId = $this->existingOrderId($attempt->cartId);
        if ($existingOrderId !== null) {
            $this->paymentAttemptRepository->assignOrderId($attempt->id, $existingOrderId);
            $this->orderNotes->addPaymentConfirmed($existingOrderId, $this->transactionId($transaction));

            return;
        }

        $cart = $this->loadCart($attempt->cartId);
        $this->paymentValidator->assertCartMatches($attempt, $cart);

        $this->createOrder($attempt, $transaction, $cart, $orderStateId);
    }

    private function existingOrderId(int $cartId): ?int
    {
        $orderId = (int) Order::getIdByCartId($cartId);

        return $orderId > 0 ? $orderId : null;
    }

    /**
     * @throws WebhookException
     */
    private function loadCart(int $cartId): Cart
    {
        $cart = new Cart($cartId);
        if (!Validate::isLoadedObject($cart)) {
            throw new WebhookException('Could not load the cart for the Wipop payment attempt.', 404);
        }

        return $cart;
    }

    /**
     * @throws WebhookException
     */
    private function createOrder(PaymentAttempt $attempt, Transaction $transaction, Cart $cart, int $orderStateId): void
    {
        $transactionId = $this->transactionId($transaction);

        $secureKey = trim((string) $cart->secure_key);
        if ($secureKey === '') {
            throw new WebhookException('Missing cart secure key for the Wipop payment attempt.', 400);
        }

        if ($orderStateId <= 0) {
            throw new RuntimeException('Unable to resolve the target order status.');
        }

        try {
            $created = $this->module->validateOrder(
                (int) $cart->id,
                $orderStateId,
                $attempt->amount,
                (string) $this->module->displayName,
                null,
                ['transaction_id' => $transactionId],
                (int) $cart->id_currency,
                false,
                $secureKey
            );
        } catch (Throwable $throwable) {
            $orderId = $this->existingOrderId((int) $cart->id);
            if ($orderId === null || !$this->isExpectedCreatedOrder($orderId, $cart, $attempt, $orderStateId)) {
                throw $throwable;
            }

            $this->paymentAttemptRepository->assignOrderId($attempt->id, $orderId);
            $this->orderNotes->addPaymentConfirmed($orderId, $transactionId);

            return;
        }

        if (!$created) {
            throw new RuntimeException('Unable to create the PrestaShop order from the Wipop webhook.');
        }

        $orderId = (int) $this->module->currentOrder;
        if ($orderId <= 0) {
            $orderId = (int) Order::getIdByCartId((int) $cart->id);
        }

        if ($orderId <= 0) {
            throw new RuntimeException('Unable to resolve the created PrestaShop order id.');
        }

        $this->paymentAttemptRepository->assignOrderId($attempt->id, $orderId);
        $this->orderNotes->addPaymentConfirmed($orderId, $transactionId);
    }

    private function isExpectedCreatedOrder(int $orderId, Cart $cart, PaymentAttempt $attempt, int $orderStateId): bool
    {
        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            return false;
        }

        return (int) $order->id_cart === (int) $cart->id
            && (int) $order->id_currency === (int) $cart->id_currency
            && (int) $order->current_state === $orderStateId
            && PaymentAmount::matches($attempt->amount, (float) $order->total_paid_tax_incl);
    }

    /**
     * @throws WebhookException
     */
    private function transactionId(Transaction $transaction): string
    {
        $transactionId = trim((string) $transaction->id);
        if ($transactionId === '') {
            throw new WebhookException('Missing transaction id for the Wipop payment.', 400);
        }

        return $transactionId;
    }
}
