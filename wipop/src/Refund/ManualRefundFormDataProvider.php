<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Refund;

use Currency;
use Order as LegacyOrder;
use OrderSlip;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderForViewing;
use PrestaShop\PrestaShop\Core\Domain\Order\QueryResult\OrderForViewing;

final class ManualRefundFormDataProvider
{
    public function __construct(
        private CommandBusInterface $queryBus,
        private RefundableShippingCalculator $shippingCalculator,
    ) {
    }

    public function getForOrder(int $orderId): ManualRefundFormData
    {
        /** @var OrderForViewing $order */
        $order = $this->queryBus->handle(new GetOrderForViewing($orderId));
        $legacyOrder = new LegacyOrder($orderId);
        $currency = new Currency($order->getCurrencyId());

        $lines = [];
        foreach ($order->getProducts()->getProducts() as $product) {
            if (!$product->isRefundable()) {
                continue;
            }

            $orderDetailId = $product->getOrderDetailId();
            if ($orderDetailId === null || $orderDetailId <= 0) {
                continue;
            }

            $unitAmount = $order->isTaxIncluded()
                ? (float) (string) $product->getUnitPriceTaxInclRaw()
                : (float) (string) $product->getUnitPriceTaxExclRaw();

            $lines[] = new ManualRefundFormLine(
                $orderDetailId,
                $product->getName(),
                $product->getReference(),
                $product->getQuantityRefundable(),
                $unitAmount,
                (float) (string) $product->getUnitPriceTaxInclRaw(),
                (float) (string) $product->getAmountRefundableRaw(),
                $product->getAmountRefundable(),
            );
        }

        return new ManualRefundFormData(
            $lines,
            (float) (string) $order->getPrices()->getShippingRefundableAmountRaw(),
            $this->remainingShippingTaxIncludedAmount($legacyOrder),
            $order->getPrices()->getShippingRefundableAmountFormatted() ?? '-',
            (string) $currency->iso_code,
            $order->getTaxMethod(),
            $order->isTaxIncluded(),
        );
    }

    private function remainingShippingTaxIncludedAmount(LegacyOrder $order): float
    {
        /** @var array<int, array<string, mixed>>|false $slips */
        $slips = OrderSlip::getOrdersSlip((int) $order->id_customer, (int) $order->id);

        return $this->shippingCalculator->remainingTaxIncludedAmount(
            (float) $order->total_shipping_tax_incl,
            is_array($slips) ? $slips : []
        );
    }
}
