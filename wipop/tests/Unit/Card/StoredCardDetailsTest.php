<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Card;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Card\StoredCardDetails;
use Wipop\Domain\Card;

final class StoredCardDetailsTest extends TestCase
{
    public function testItBuildsStoredCardDetailsFromWipopCard(): void
    {
        $card = new Card();
        $card->id = 'card_123';
        $card->brand = 'Visa';
        $card->cardNumber = '411111******1111';
        $card->expirationMonth = '7';
        $card->expirationYear = '27';

        $details = StoredCardDetails::fromWipopCard($card);

        self::assertNotNull($details);
        self::assertSame('card_123', $details->sourceId);
        self::assertSame('Visa', $details->brand);
        self::assertSame('1111', $details->last4);
        self::assertSame('07', $details->expirationMonth);
        self::assertSame('2027', $details->expirationYear);
    }

    public function testItReturnsNullWithoutSourceId(): void
    {
        $card = new Card();

        self::assertNull(StoredCardDetails::fromWipopCard($card));
    }
}
