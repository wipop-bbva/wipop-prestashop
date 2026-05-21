<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Payment\TransactionType;

final class TransactionTypeTest extends TestCase
{
    public function testItNormalizesTransactionTypes(): void
    {
        self::assertSame('REFUND', TransactionType::normalize(' refund '));
    }

    public function testItDetectsKnownTransactionTypes(): void
    {
        self::assertTrue(TransactionType::isCharge('charge'));
        self::assertTrue(TransactionType::isRefund('refund'));
        self::assertFalse(TransactionType::isRefund('charge'));
    }

    public function testItAllowsMissingTransactionTypeOnlyForChargeSync(): void
    {
        self::assertTrue(TransactionType::isChargeOrMissing(null));
        self::assertTrue(TransactionType::isChargeOrMissing('CHARGE'));
        self::assertFalse(TransactionType::isCharge(null));
        self::assertFalse(TransactionType::isChargeOrMissing('REFUND'));
    }
}
