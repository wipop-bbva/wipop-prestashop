<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Card;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Card\CardCofSelection;
use PrestaShop\Module\Wipop\Card\StoredCard;

final class CardCofSelectionTest extends TestCase
{
    public function testOnlyStoredCardPaymentsCanSkipTheRedirectUrl(): void
    {
        self::assertTrue(CardCofSelection::none()->requiresRedirectUrl());
        self::assertTrue(CardCofSelection::save()->requiresRedirectUrl());
        self::assertFalse(CardCofSelection::useExisting($this->storedCard())->requiresRedirectUrl());
    }

    private function storedCard(): StoredCard
    {
        return new StoredCard(
            id: 7,
            customerId: 10,
            shopId: 1,
            merchantId: 'merchant_123',
            isLive: false,
            terminalId: 1,
            sourceId: 'src_123',
            wipopCustomerId: 'cust_123',
            brand: 'visa',
            last4: '1111',
            expirationMonth: '12',
            expirationYear: '2027',
        );
    }
}
