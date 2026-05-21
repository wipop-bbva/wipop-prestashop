<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Webhook;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Payment\PaymentEventData;
use PrestaShop\Module\Wipop\Webhook\WebhookRefundExtractor;
use Wipop\Domain\Transaction;
use Wipop\Domain\TransactionStatus;

final class WebhookRefundExtractorTest extends TestCase
{
    public function testItExtractsARefundTransaction(): void
    {
        $transaction = new Transaction();
        $transaction->id = 'tx_refund';
        $transaction->orderId = 'order_123';
        $transaction->transactionType = 'refund';
        $transaction->operationType = 'refund';
        $transaction->status = TransactionStatus::COMPLETED;
        $transaction->amount = 6.1;
        $transaction->currency = 'eur';

        $refund = (new WebhookRefundExtractor())->extract($transaction, []);

        self::assertInstanceOf(PaymentEventData::class, $refund);
        self::assertSame('tx_refund', $refund->remoteTransactionId);
        self::assertNull($refund->parentTransactionId);
        self::assertSame('order_123', $refund->wipopOrderId);
        self::assertSame(PaymentEventData::TYPE_REFUND, $refund->transactionType);
        self::assertSame('COMPLETED', $refund->status);
        self::assertSame(6.1, $refund->amount);
        self::assertSame('eur', $refund->currencyIso);
    }

    public function testItExtractsADocumentedNestedRefundPayload(): void
    {
        $transaction = new Transaction();
        $transaction->id = 'tx_charge';
        $transaction->orderId = 'order_123';

        $refund = (new WebhookRefundExtractor())->extract($transaction, [
            'refund' => [
                'public_id' => 'tx_refund',
                'order_id' => 'order_123',
                'status' => 'COMPLETED',
                'amount' => 6.1,
                'currency' => 'EUR',
            ],
        ]);

        self::assertInstanceOf(PaymentEventData::class, $refund);
        self::assertSame('tx_refund', $refund->remoteTransactionId);
        self::assertSame('tx_charge', $refund->parentTransactionId);
        self::assertSame('order_123', $refund->wipopOrderId);
        self::assertSame('COMPLETED', $refund->status);
        self::assertSame(6.1, $refund->amount);
        self::assertSame('EUR', $refund->currencyIso);
    }

    public function testItIgnoresChargeTransactions(): void
    {
        $transaction = new Transaction();
        $transaction->id = 'tx_charge';
        $transaction->transactionType = 'CHARGE';

        self::assertNull((new WebhookRefundExtractor())->extract($transaction, []));
    }
}
