<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Refund;

use Db;
use Order as LegacyOrder;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssuePartialRefundCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\VoucherRefundType;
use Validate;

final class PrestaShopOrderSlipIssuer implements OrderSlipIssuerInterface
{
    private Db $db;

    public function __construct(
        private CommandBusInterface $commandBus,
        ?Db $db = null,
    ) {
        $this->db = $db ?? Db::getInstance();
    }

    public function assertCanIssueOrderSlip(int $orderId, ManualRefundRequest $request): void
    {
        $this->createCommand($orderId, $request);
        $order = new LegacyOrder($orderId);

        if (!Validate::isLoadedObject($order) || (!$order->hasBeenPaid() && !$order->hasPayments())) {
            throw new ManualRefundException('PrestaShop order cannot be refunded.');
        }
    }

    public function issue(int $orderId, ManualRefundRequest $request): int
    {
        $previousOrderSlipId = $this->latestOrderSlipId($orderId);

        $this->commandBus->handle($this->createCommand($orderId, $request));

        $orderSlipId = $this->latestOrderSlipId($orderId);
        if ($orderSlipId <= $previousOrderSlipId) {
            throw new ManualRefundException('PrestaShop did not create a credit slip.');
        }

        return $orderSlipId;
    }

    private function createCommand(int $orderId, ManualRefundRequest $request): IssuePartialRefundCommand
    {
        return new IssuePartialRefundCommand(
            $orderId,
            $request->orderDetailRefundsForCommand(),
            $request->shippingAmountForCommand(),
            $request->restock,
            true,
            false,
            VoucherRefundType::PRODUCT_PRICES_REFUND,
        );
    }

    private function latestOrderSlipId(int $orderId): int
    {
        $sql = sprintf(
            'SELECT COALESCE(MAX(`id_order_slip`), 0)
            FROM `%sorder_slip`
            WHERE `id_order` = %d',
            _DB_PREFIX_,
            $orderId
        );

        $orderSlipId = $this->db->getValue($sql);
        if ($orderSlipId === false || $orderSlipId === null) {
            throw new ManualRefundException('Unable to read latest PrestaShop credit slip.');
        }

        return (int) $orderSlipId;
    }
}
