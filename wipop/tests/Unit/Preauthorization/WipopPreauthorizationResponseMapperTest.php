<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Preauthorization;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Preauthorization\PreauthorizationException;
use PrestaShop\Module\Wipop\Preauthorization\WipopPreauthorizationResponseMapper;
use Wipop\Domain\Charge;
use Wipop\Domain\TransactionStatus;

final class WipopPreauthorizationResponseMapperTest extends TestCase
{
    public function testItMapsCaptureOrReversalChargeResponse(): void
    {
        $charge = new Charge();
        $charge->id = 'tx_capture';
        $charge->orderId = 'WP1234567890';
        $charge->status = TransactionStatus::COMPLETED;
        $charge->amount = 49.9;
        $charge->currency = 'EUR';
        $charge->operationType = 'capture';

        $result = (new WipopPreauthorizationResponseMapper())->map('tx_auth', $charge);

        self::assertSame('tx_capture', $result->remoteTransactionId);
        self::assertSame('tx_auth', $result->parentTransactionId);
        self::assertSame('COMPLETED', $result->status);
        self::assertSame(49.9, $result->amount);
        self::assertSame('EUR', $result->currencyIso);
    }

    public function testItRejectsResponseWithoutTransactionId(): void
    {
        $charge = new Charge();
        $charge->status = TransactionStatus::COMPLETED;
        $charge->amount = 49.9;
        $charge->currency = 'EUR';

        $this->expectException(PreauthorizationException::class);

        (new WipopPreauthorizationResponseMapper())->map('tx_auth', $charge);
    }
}
