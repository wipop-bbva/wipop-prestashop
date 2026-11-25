<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Refund;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Refund\ManualRefundException;
use PrestaShop\Module\Wipop\Refund\WipopRefundResponseMapper;
use Wipop\Domain\Charge;
use Wipop\Domain\Refund;
use Wipop\Domain\TransactionStatus;

final class WipopRefundResponseMapperTest extends TestCase
{
    public function testItMapsRefundIdFromSdkCharge(): void
    {
        $result = (new WipopRefundResponseMapper())->map(
            'tx_charge',
            $this->chargeWithRefund(refundId: 'tx_refund')
        );

        self::assertSame('tx_refund', $result->refundTransactionId);
        self::assertSame('tx_charge', $result->parentTransactionId);
        self::assertSame('WP1234567890', $result->wipopOrderId);
        self::assertSame('COMPLETED', $result->status);
        self::assertSame(12.1, $result->amount);
        self::assertSame('EUR', $result->currencyIso);
        self::assertSame('refund', $result->operationType);
    }

    public function testItUsesChargeIdWhenSdkDoesNotHydrateRefundId(): void
    {
        $result = (new WipopRefundResponseMapper())->map(
            'tx_charge',
            $this->chargeWithRefund(chargeId: 'tx_refund_from_charge', refundId: null)
        );

        self::assertSame('tx_refund_from_charge', $result->refundTransactionId);
    }

    public function testItUsesRequestedAmountWhenImmediateResponseEchoesChargeAmount(): void
    {
        $result = (new WipopRefundResponseMapper())->map(
            'tx_charge',
            $this->chargeWithRefund(refundId: 'tx_refund', amount: 9.0),
            3.0
        );

        self::assertSame(3.0, $result->amount);
    }

    public function testItRejectsResponseWithoutRefundTransactionIdentifier(): void
    {
        $this->expectException(ManualRefundException::class);

        (new WipopRefundResponseMapper())->map(
            'tx_charge',
            $this->chargeWithRefund(chargeId: null, refundId: null)
        );
    }

    public function testItRejectsOriginalChargeIdAsRefundIdentifier(): void
    {
        $this->expectException(ManualRefundException::class);

        (new WipopRefundResponseMapper())->map(
            'tx_charge',
            $this->chargeWithRefund(chargeId: 'tx_charge', refundId: null)
        );
    }

    public function testItRejectsResponseWithoutRefundDetails(): void
    {
        $this->expectException(ManualRefundException::class);

        (new WipopRefundResponseMapper())->map(
            'tx_charge',
            new Charge()
        );
    }

    public function testItRejectsResponseWithoutRequiredRefundConfirmationFields(): void
    {
        foreach (['status', 'amount', 'currency'] as $field) {
            $charge = $this->chargeWithRefund(refundId: 'tx_refund');
            $refund = $charge->refund;
            self::assertInstanceOf(Refund::class, $refund);

            match ($field) {
                'status' => $refund->status = null,
                'amount' => $refund->amount = null,
                'currency' => $refund->currency = null,
            };

            try {
                (new WipopRefundResponseMapper())->map('tx_charge', $charge);
                self::fail(sprintf('The mapper should reject missing refund %s.', $field));
            } catch (ManualRefundException $exception) {
                self::assertStringContainsString('refund', $exception->getMessage());
            }
        }
    }

    private function chargeWithRefund(
        ?string $chargeId = 'tx_charge',
        ?string $refundId = 'tx_refund',
        float $amount = 12.1,
    ): Charge {
        $charge = new Charge();
        $charge->id = $chargeId;
        $charge->orderId = 'WP1234567890';

        $refund = new Refund();
        $refund->id = $refundId;
        $refund->amount = $amount;
        $refund->status = TransactionStatus::COMPLETED;
        $refund->currency = 'EUR';
        $refund->operationType = 'refund';
        $refund->orderId = 'WP1234567890';

        $charge->refund = $refund;

        return $charge;
    }
}
