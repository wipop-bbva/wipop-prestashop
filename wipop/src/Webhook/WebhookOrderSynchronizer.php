<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Webhook;

use Cart;
use Order;
use PaymentModule;
use PrestaShop\Module\Wipop\Exception\WebhookException;
use PrestaShop\Module\Wipop\Order\OrderPrivateNoteWriter;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptRepository;
use PrestaShop\Module\Wipop\Payment\TransactionType;
use RuntimeException;
use Validate;
use Wipop\Domain\Transaction;

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
        if (!TransactionType::isCharge($transaction->transactionType)) {
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
