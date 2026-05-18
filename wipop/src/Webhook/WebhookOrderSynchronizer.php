<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Webhook;

use Cart;
use Configuration;
use Order;
use PaymentModule;
use PrestaShop\Module\Wipop\Exception\WebhookException;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptRepository;
use Validate;
use Wipop\Domain\Transaction;
use Wipop\Domain\TransactionStatus;

final class WebhookOrderSynchronizer
{
    private const TRANSACTION_TYPE_CHARGE = 'CHARGE';

    public function __construct(
        private PaymentAttemptRepository $paymentAttemptRepository,
        private PaymentModule $module,
    ) {
    }

    /**
     * @throws WebhookException
     */
    public function sync(PaymentAttempt $attempt, Transaction $transaction): void
    {
        if (!$this->shouldCreateOrLinkOrder($transaction)) {
            return;
        }

        if ($attempt->orderId !== null) {
            return;
        }

        $existingOrderId = $this->existingOrderId($attempt->cartId);
        if ($existingOrderId !== null) {
            $this->paymentAttemptRepository->assignOrderId($attempt->id, $existingOrderId);

            return;
        }

        $this->createOrder($attempt, $transaction);
    }

    private function shouldCreateOrLinkOrder(Transaction $transaction): bool
    {
        return $transaction->status === TransactionStatus::COMPLETED
            && strtoupper(trim((string) $transaction->transactionType)) === self::TRANSACTION_TYPE_CHARGE;
    }

    private function existingOrderId(int $cartId): ?int
    {
        $orderId = (int) Order::getIdByCartId($cartId);

        return $orderId > 0 ? $orderId : null;
    }

    /**
     * @throws WebhookException
     */
    private function createOrder(PaymentAttempt $attempt, Transaction $transaction): void
    {
        $transactionId = trim((string) $transaction->id);
        if ($transactionId === '') {
            throw new WebhookException('Missing transaction id for completed payment.', 400);
        }

        $cart = new Cart($attempt->cartId);
        if (!Validate::isLoadedObject($cart)) {
            throw new WebhookException('Could not load the cart for the Wipop payment attempt.', 404);
        }

        $secureKey = trim((string) $cart->secure_key);
        if ($secureKey === '') {
            throw new WebhookException('Missing cart secure key for the Wipop payment attempt.', 400);
        }

        $paymentStatusId = (int) Configuration::get('PS_OS_PAYMENT');
        if ($paymentStatusId <= 0) {
            throw new \RuntimeException('Unable to resolve the paid order status.');
        }

        $created = $this->module->validateOrder(
            (int) $cart->id,
            $paymentStatusId,
            $attempt->amount,
            (string) $this->module->displayName,
            null,
            ['transaction_id' => $transactionId],
            (int) $cart->id_currency,
            false,
            $secureKey
        );

        if (!$created) {
            throw new \RuntimeException('Unable to create the PrestaShop order from the Wipop webhook.');
        }

        $orderId = (int) $this->module->currentOrder;
        if ($orderId <= 0) {
            $orderId = (int) Order::getIdByCartId((int) $cart->id);
        }

        if ($orderId <= 0) {
            throw new \RuntimeException('Unable to resolve the created PrestaShop order id.');
        }

        $this->paymentAttemptRepository->assignOrderId($attempt->id, $orderId);
    }
}
