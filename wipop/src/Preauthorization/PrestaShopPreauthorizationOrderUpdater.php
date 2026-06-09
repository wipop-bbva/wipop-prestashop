<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Preauthorization;

use Configuration;
use Currency;
use Order;
use OrderHistory;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\UpdateOrderStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Payment\Command\AddPaymentCommand;
use Validate;

final class PrestaShopPreauthorizationOrderUpdater implements PreauthorizationOrderUpdaterInterface
{
    private const PAYMENT_METHOD = 'Wipop';

    public function __construct(
        private ?CommandBusInterface $commandBus = null,
    ) {
    }

    /**
     * @throws PreauthorizationException
     */
    public function markCaptured(
        int $orderId,
        float $amount,
        string $currencyIso,
        string $transactionId,
        int $employeeId,
    ): void {
        $order = $this->loadOrder($orderId);
        $currency = new Currency((int) $order->id_currency);

        if (
            !Validate::isLoadedObject($currency)
            || strtoupper((string) $currency->iso_code) !== strtoupper($currencyIso)
        ) {
            throw new PreauthorizationException('The order currency does not match the Wipop capture currency.');
        }

        if (!$this->orderPaymentExists($order, $transactionId)) {
            $this->addOrderPayment($order, $currency, $amount, $transactionId, $employeeId);
        }

        $paidStateId = (int) Configuration::get('PS_OS_PAYMENT');
        if ($paidStateId <= 0) {
            throw new PreauthorizationException('Unable to resolve the paid order status.');
        }

        if ((int) $order->current_state !== $paidStateId) {
            $this->updateOrderStatus($orderId, $paidStateId);
        }
    }

    /**
     * @throws PreauthorizationException
     */
    public function markCancelled(int $orderId): void
    {
        $order = $this->loadOrder($orderId);
        $cancelledStateId = (int) Configuration::get('PS_OS_CANCELED');
        if ($cancelledStateId <= 0) {
            throw new PreauthorizationException('Unable to resolve the canceled order status.');
        }

        if ((int) $order->current_state !== $cancelledStateId) {
            $this->updateOrderStatus($orderId, $cancelledStateId);
        }
    }

    /**
     * @throws PreauthorizationException
     */
    private function addOrderPayment(
        Order $order,
        Currency $currency,
        float $amount,
        string $transactionId,
        int $employeeId,
    ): void {
        if ($this->commandBus !== null) {
            $this->commandBus->handle(new AddPaymentCommand(
                (int) $order->id,
                date('Y-m-d H:i:s'),
                self::PAYMENT_METHOD,
                number_format($amount, 6, '.', ''),
                (int) $order->id_currency,
                $employeeId,
                null,
                $transactionId
            ));

            return;
        }

        if (
            !$order->addOrderPayment(
                $amount,
                self::PAYMENT_METHOD,
                $transactionId,
                $currency,
                date('Y-m-d H:i:s'),
                null,
                $employeeId
            )
        ) {
            throw new PreauthorizationException('Unable to add the Wipop capture payment to the order.');
        }
    }

    /**
     * @throws PreauthorizationException
     */
    private function updateOrderStatus(int $orderId, int $stateId): void
    {
        if ($this->commandBus !== null) {
            $this->commandBus->handle(new UpdateOrderStatusCommand($orderId, $stateId));

            return;
        }

        $history = new OrderHistory();
        $history->id_order = $orderId;
        $history->changeIdOrderState($stateId, $orderId);

        if (!$history->add(false)) {
            throw new PreauthorizationException('Unable to update the Wipop preauthorization order status.');
        }
    }

    /**
     * @throws PreauthorizationException
     */
    private function loadOrder(int $orderId): Order
    {
        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            throw new PreauthorizationException('Could not load the PrestaShop order.');
        }

        return $order;
    }

    private function orderPaymentExists(Order $order, string $transactionId): bool
    {
        foreach ($order->getOrderPaymentCollection() as $payment) {
            /** @var \OrderPaymentCore $payment */
            /** @var string $paymentTransactionId */
            $paymentTransactionId = $payment->transaction_id;
            if (trim($paymentTransactionId) === $transactionId) {
                return true;
            }
        }

        return false;
    }
}
