<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Refund;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptSummary;
use PrestaShop\Module\Wipop\Payment\PaymentRefundSummary;
use PrestaShop\Module\Wipop\Refund\ManualRefundException;
use PrestaShop\Module\Wipop\Refund\ManualRefundFormData;
use PrestaShop\Module\Wipop\Refund\ManualRefundFormLine;
use PrestaShop\Module\Wipop\Refund\ManualRefundLine;
use PrestaShop\Module\Wipop\Refund\ManualRefundRequest;
use PrestaShop\Module\Wipop\Refund\ManualRefundValidator;
use PrestaShop\Module\Wipop\Refund\WipopRefundTaxIncludedAmountCalculator;

final class ManualRefundValidatorTest extends TestCase
{
    public function testItAcceptsAValidPartialRefund(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator()->assertRefundable(
            $this->summary(),
            $this->request(shippingAmount: 2.5),
            $this->formData(),
        );
    }

    public function testItRejectsRefundsAboveWipopRemainingAmount(): void
    {
        $this->expectException(ManualRefundException::class);

        $this->validator()->assertRefundable(
            $this->summary(refundedAmount: 40.0),
            $this->request(),
            $this->formData(),
        );
    }

    public function testItRejectsCurrencyMismatch(): void
    {
        $this->expectException(ManualRefundException::class);

        $this->validator()->assertRefundable(
            $this->summary(),
            $this->request(),
            $this->formData(currencyIso: 'USD', shippingAmountFormatted: '5.00 USD'),
        );
    }

    public function testItRejectsProductAmountsAbovePrestaShopRefundableAmount(): void
    {
        $this->expectException(ManualRefundException::class);

        $this->validator()->assertRefundable(
            $this->summary(),
            $this->request(amount: 31.0),
            $this->formData(),
        );
    }

    public function testItRejectsProductAmountsAboveSelectedQuantityValue(): void
    {
        $this->expectException(ManualRefundException::class);

        $this->validator()->assertRefundable(
            $this->summary(),
            $this->request(amount: 16.0),
            $this->formData(),
        );
    }

    public function testItRejectsShippingAbovePrestaShopRefundableAmount(): void
    {
        $this->expectException(ManualRefundException::class);

        $this->validator()->assertRefundable(
            $this->summary(),
            $this->request(lines: [], shippingAmount: 6.0),
            $this->formData(),
        );
    }

    private function validator(): ManualRefundValidator
    {
        return new ManualRefundValidator(new WipopRefundTaxIncludedAmountCalculator());
    }

    private function summary(float $refundedAmount = 0.0): PaymentAttemptSummary
    {
        return new PaymentAttemptSummary(
            id: 12,
            cartId: 34,
            paymentMethod: 'card',
            status: PaymentAttemptStatus::PAID,
            amount: 49.9,
            currencyIso: 'EUR',
            wipopOrderId: 'WP1234567890',
            wipopTransactionId: 'tx_charge',
            remoteStatus: 'COMPLETED',
            remoteErrorCode: null,
            remoteErrorMessage: null,
            webhookReceivedAt: '2026-05-19 10:11:12',
            refundSummary: $this->refundSummary(refundedAmount: $refundedAmount),
        );
    }

    /**
     * @param ManualRefundLine[] $lines
     */
    private function request(
        ?array $lines = null,
        float $amount = 10.0,
        float $shippingAmount = 0.0,
    ): ManualRefundRequest {
        $lines ??= [new ManualRefundLine(90, 1, $amount)];

        return new ManualRefundRequest($lines, $shippingAmount, false);
    }

    private function refundSummary(float $refundedAmount): ?PaymentRefundSummary
    {
        if ($refundedAmount <= 0.0) {
            return null;
        }

        return new PaymentRefundSummary(
            refundedAmount: $refundedAmount,
            currencyIso: 'EUR',
            completedRefundCount: 1,
            lastRefundTransactionId: 'tx_refund',
            lastRefundStatus: 'COMPLETED',
            lastRefundAmount: $refundedAmount,
            lastRefundCurrencyIso: 'EUR',
            lastRefundReceivedAt: null,
            lastRefundErrorCode: null,
            lastRefundErrorMessage: null,
        );
    }

    private function formData(
        string $currencyIso = 'EUR',
        string $shippingAmountFormatted = '5.00 EUR',
    ): ManualRefundFormData {
        return new ManualRefundFormData(
            [
                new ManualRefundFormLine(
                    orderDetailId: 90,
                    name: 'Product',
                    reference: 'REF',
                    quantityRefundable: 2,
                    unitAmount: 15.0,
                    unitAmountTaxIncluded: 15.0,
                    amountRefundable: 30.0,
                    amountRefundableFormatted: '30.00 EUR',
                ),
            ],
            shippingRefundableAmount: 5.0,
            shippingRefundableAmountTaxIncluded: 5.0,
            shippingRefundableAmountFormatted: $shippingAmountFormatted,
            currencyIso: $currencyIso,
            taxMethod: 'tax incl.',
            isTaxIncluded: true,
        );
    }
}
