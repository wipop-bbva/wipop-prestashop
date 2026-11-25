<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Refund;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Refund\ManualRefundException;
use PrestaShop\Module\Wipop\Refund\ManualRefundFormData;
use PrestaShop\Module\Wipop\Refund\ManualRefundFormLine;
use PrestaShop\Module\Wipop\Refund\ManualRefundLine;
use PrestaShop\Module\Wipop\Refund\ManualRefundRequest;
use PrestaShop\Module\Wipop\Refund\WipopRefundTaxIncludedAmountCalculator;

final class WipopRefundTaxIncludedAmountCalculatorTest extends TestCase
{
    public function testItUsesSubmittedAmountWhenOrderUsesTaxIncludedAmounts(): void
    {
        $amountCents = (new WipopRefundTaxIncludedAmountCalculator())->amountCentsForWipop(
            $this->request(productAmount: 10.0, shippingAmount: 2.5),
            $this->formData(isTaxIncluded: true)
        );

        self::assertSame(1250, $amountCents);
    }

    public function testItConvertsTaxExcludedAmountsToTaxIncludedWipopAmount(): void
    {
        $amountCents = (new WipopRefundTaxIncludedAmountCalculator())->amountCentsForWipop(
            $this->request(productAmount: 10.0, shippingAmount: 2.5),
            $this->formData(isTaxIncluded: false)
        );

        self::assertSame(1513, $amountCents);
    }

    public function testItRejectsUnknownOrderLines(): void
    {
        $this->expectException(ManualRefundException::class);

        (new WipopRefundTaxIncludedAmountCalculator())->amountCentsForWipop(
            new ManualRefundRequest(
                lines: [
                    new ManualRefundLine(
                        orderDetailId: 91,
                        quantity: 1,
                        amount: 10.0,
                    ),
                ],
                shippingAmount: 0.0,
                restock: false,
            ),
            $this->formData(isTaxIncluded: false)
        );
    }

    private function request(float $productAmount, float $shippingAmount): ManualRefundRequest
    {
        return new ManualRefundRequest(
            lines: [
                new ManualRefundLine(
                    orderDetailId: 90,
                    quantity: 1,
                    amount: $productAmount,
                ),
            ],
            shippingAmount: $shippingAmount,
            restock: false,
        );
    }

    private function formData(bool $isTaxIncluded): ManualRefundFormData
    {
        return new ManualRefundFormData(
            lines: [
                new ManualRefundFormLine(
                    orderDetailId: 90,
                    name: 'Product',
                    reference: 'REF',
                    quantityRefundable: 2,
                    unitAmount: 10.0,
                    unitAmountTaxIncluded: 12.1,
                    amountRefundable: 20.0,
                    amountRefundableFormatted: '20.00 EUR',
                ),
            ],
            shippingRefundableAmount: 5.0,
            shippingRefundableAmountTaxIncluded: 6.05,
            shippingRefundableAmountFormatted: '5.00 EUR',
            currencyIso: 'EUR',
            taxMethod: $isTaxIncluded ? 'tax incl.' : 'tax excl.',
            isTaxIncluded: $isTaxIncluded,
        );
    }
}
