<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Card;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Card\StoredCardPresenter;
use PrestaShop\Module\Wipop\Card\StoredCardSummary;

final class StoredCardPresenterTest extends TestCase
{
    public function testItPresentsStoredCardSummaries(): void
    {
        $card = new StoredCardSummary(
            id: 7,
            brand: 'visa',
            last4: '1111',
            expirationMonth: '07',
            expirationYear: '2027',
        );

        $presented = (new StoredCardPresenter())->present($card);

        self::assertSame([
            'id' => 7,
            'brand' => 'VISA',
            'last4' => '1111',
            'expiry' => '07/2027',
            'label' => 'VISA •••• 1111',
        ], $presented);
    }
}
