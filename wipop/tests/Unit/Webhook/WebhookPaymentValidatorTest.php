<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Webhook;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Exception\WebhookException;
use PrestaShop\Module\Wipop\Payment\PaymentAttempt;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptStatus;
use PrestaShop\Module\Wipop\Payment\PaymentEventData;
use PrestaShop\Module\Wipop\Webhook\WebhookPaymentValidator;

final class WebhookPaymentValidatorTest extends TestCase
{
    public function testItAcceptsACompletedRefundMatchingTheAttempt(): void
    {
        $this->expectNotToPerformAssertions();

        (new WebhookPaymentValidator())->assertRefundMatches(
            $this->attempt(),
            $this->refund(6.1, 'eur')
        );
    }

    public function testItRejectsARefundWithADifferentCurrency(): void
    {
        $this->expectException(WebhookException::class);

        (new WebhookPaymentValidator())->assertRefundMatches(
            $this->attempt(),
            $this->refund(6.1, 'USD')
        );
    }

    public function testItRejectsARefundWithoutPositiveAmount(): void
    {
        $this->expectException(WebhookException::class);

        (new WebhookPaymentValidator())->assertRefundMatches(
            $this->attempt(),
            $this->refund(0.0, 'EUR')
        );
    }

    private function attempt(): PaymentAttempt
    {
        return new PaymentAttempt(
            12,
            34,
            56,
            PaymentAttemptStatus::PAID,
            49.9,
            'EUR',
        );
    }

    private function refund(?float $amount, ?string $currency): PaymentEventData
    {
        return new PaymentEventData(
            'tx_refund',
            'tx_charge',
            'order_123',
            PaymentEventData::TYPE_REFUND,
            'refund',
            'COMPLETED',
            $amount,
            $currency,
            null,
            null,
        );
    }
}
