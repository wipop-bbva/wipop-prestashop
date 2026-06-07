<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Card;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Card\CardCofException;
use PrestaShop\Module\Wipop\Card\CardCofIntent;
use PrestaShop\Module\Wipop\Card\CardCofSelectionResolver;
use PrestaShop\Module\Wipop\Card\StoredCard;
use Wipop\Domain\ChargeMethod;

final class CardCofSelectionResolverTest extends TestCase
{
    public function testItIgnoresTokenizationForNonCardPayments(): void
    {
        $resolver = new CardCofSelectionResolver(new InMemoryStoredCards([]));

        $selection = $resolver->resolve(
            paymentMethod: ChargeMethod::BIZUM,
            customerId: 10,
            shopId: 1,
            merchantId: 'merchant_123',
            isLive: false,
            terminalId: 1,
            selectedCardId: null,
            saveCard: true,
        );

        self::assertSame(CardCofIntent::NONE, $selection->intent);
    }

    public function testItIgnoresTokenizationWhenCustomerIsNotLoggedIn(): void
    {
        $resolver = new CardCofSelectionResolver(new InMemoryStoredCards([]));

        $selection = $resolver->resolve(
            paymentMethod: ChargeMethod::CARD,
            customerId: null,
            shopId: 1,
            merchantId: 'merchant_123',
            isLive: false,
            terminalId: 1,
            selectedCardId: null,
            saveCard: true,
        );

        self::assertSame(CardCofIntent::NONE, $selection->intent);
    }

    public function testItResolvesSaveIntentForLoggedCardPayments(): void
    {
        $resolver = new CardCofSelectionResolver(new InMemoryStoredCards([]));

        $selection = $resolver->resolve(
            paymentMethod: ChargeMethod::CARD,
            customerId: 10,
            shopId: 1,
            merchantId: 'merchant_123',
            isLive: false,
            terminalId: 1,
            selectedCardId: null,
            saveCard: true,
        );

        self::assertSame(CardCofIntent::SAVE, $selection->intent);
    }

    public function testItResolvesSelectedStoredCard(): void
    {
        $card = self::storedCard(7);
        $resolver = new CardCofSelectionResolver(new InMemoryStoredCards([$card]));

        $selection = $resolver->resolve(
            paymentMethod: ChargeMethod::CARD,
            customerId: 10,
            shopId: 1,
            merchantId: 'merchant_123',
            isLive: false,
            terminalId: 1,
            selectedCardId: 7,
            saveCard: true,
        );

        self::assertSame(CardCofIntent::USE_EXISTING, $selection->intent);
        self::assertSame($card, $selection->storedCard);
        self::assertSame(7, $selection->storedCardId());
    }

    public function testItRejectsSelectedCardsOwnedByAnotherCustomerContext(): void
    {
        $resolver = new CardCofSelectionResolver(new InMemoryStoredCards([self::storedCard(7)]));

        $this->expectException(CardCofException::class);

        $resolver->resolve(
            paymentMethod: ChargeMethod::CARD,
            customerId: 11,
            shopId: 1,
            merchantId: 'merchant_123',
            isLive: false,
            terminalId: 1,
            selectedCardId: 7,
            saveCard: false,
        );
    }

    public function testItRejectsSelectedCardsFromAnotherMerchant(): void
    {
        $resolver = new CardCofSelectionResolver(new InMemoryStoredCards([self::storedCard(7)]));

        $this->expectException(CardCofException::class);

        $resolver->resolve(
            paymentMethod: ChargeMethod::CARD,
            customerId: 10,
            shopId: 1,
            merchantId: 'merchant_456',
            isLive: false,
            terminalId: 1,
            selectedCardId: 7,
            saveCard: false,
        );
    }

    private static function storedCard(int $id): StoredCard
    {
        return new StoredCard(
            id: $id,
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
