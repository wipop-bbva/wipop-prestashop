<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Webhook;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Exception\WebhookException;
use PrestaShop\Module\Wipop\Webhook\WebhookTransactionHydrator;
use Wipop\Domain\TransactionStatus;

final class WebhookTransactionHydratorTest extends TestCase
{
    public function testItHydratesTransactionPayload(): void
    {
        $transaction = (new WebhookTransactionHydrator())->hydrate([
            'id' => 'tx_123',
            'method' => 'CARD',
            'amount' => '10.50',
            'creation_date' => '2026-06-10T10:00:00+00:00',
            'operation_date' => '2026-06-10T10:01:00+00:00',
            'status' => 'COMPLETED',
            'transaction_type' => 'CHARGE',
            'operation_type' => 'IN',
            'order_id' => 'order_123',
            'customer_id' => 'customer_123',
            'currency' => 'eur',
            'card' => [
                'id' => 'src_123',
                'brand' => 'VISA',
                'card_number' => '************4242',
                'expiration_month' => '12',
                'expiration_year' => '2030',
            ],
        ]);

        self::assertSame('tx_123', $transaction->id);
        self::assertSame('CARD', $transaction->method);
        self::assertSame(10.50, $transaction->amount);
        self::assertInstanceOf(DateTimeImmutable::class, $transaction->creationDate);
        self::assertSame(TransactionStatus::COMPLETED, $transaction->status);
        self::assertSame('CHARGE', $transaction->transactionType);
        self::assertSame('IN', $transaction->operationType);
        self::assertSame('order_123', $transaction->orderId);
        self::assertSame('customer_123', $transaction->customerId);
        self::assertSame('eur', $transaction->currency);
        self::assertSame('src_123', $transaction->card?->id);
        self::assertSame('VISA', $transaction->card?->brand);
        self::assertSame('4242', substr((string) $transaction->card?->cardNumber, -4));
    }

    public function testItHydratesCancelledTransactionStatus(): void
    {
        $transaction = (new WebhookTransactionHydrator())->hydrate([
            'id' => 'tx_cancelled',
            'status' => TransactionStatus::CANCELLED->value,
        ]);

        self::assertSame(TransactionStatus::CANCELLED, $transaction->status);
    }

    public function testItRejectsUnknownTransactionStatus(): void
    {
        $this->expectException(WebhookException::class);

        (new WebhookTransactionHydrator())->hydrate([
            'id' => 'tx_123',
            'status' => 'UNKNOWN',
        ]);
    }
}
