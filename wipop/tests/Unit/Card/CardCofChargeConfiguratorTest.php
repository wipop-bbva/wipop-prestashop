<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Card;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Card\CardCofChargeConfigurator;
use PrestaShop\Module\Wipop\Card\CardCofSelection;
use PrestaShop\Module\Wipop\Card\StoredCard;
use Wipop\Domain\ChargeMethod;
use Wipop\Domain\Value\Terminal;
use Wipop\Operations\Charge\Params\CreateChargeParams;

final class CardCofChargeConfiguratorTest extends TestCase
{
    public function testItDoesNotChangeRegularCardCharges(): void
    {
        $params = $this->baseParams();

        (new CardCofChargeConfigurator())->apply($params, CardCofSelection::none());

        $payload = $params->toArray();
        self::assertArrayNotHasKey('use_cof', $payload);
        self::assertArrayNotHasKey('source_id', $payload);
    }

    public function testItRequestsCofWhenSavingANewCard(): void
    {
        $params = $this->baseParams();

        (new CardCofChargeConfigurator())->apply($params, CardCofSelection::save());

        $payload = $params->toArray();
        self::assertTrue($payload['use_cof']);
        self::assertArrayNotHasKey('source_id', $payload);
    }

    public function testItUsesStoredSourceIdForOneClickPayments(): void
    {
        $params = $this->baseParams();
        $storedCard = new StoredCard(
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

        (new CardCofChargeConfigurator())->apply($params, CardCofSelection::useExisting($storedCard));

        $payload = $params->toArray();
        self::assertTrue($payload['use_cof']);
        self::assertSame('src_123', $payload['source_id']);
        self::assertArrayNotHasKey('post_type', $payload);
    }

    private function baseParams(): CreateChargeParams
    {
        return (new CreateChargeParams())
            ->amount(12.34)
            ->method(ChargeMethod::CARD)
            ->terminal(new Terminal(1));
    }
}
