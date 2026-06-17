<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

use Cart;
use Context;
use PrestaShop\Module\Wipop\Api\ClientFactory;
use PrestaShop\Module\Wipop\Api\SdkCaller;
use PrestaShop\Module\Wipop\Card\CardCofSelection;
use PrestaShop\Module\Wipop\Card\StoredCard;
use PrestaShop\Module\Wipop\Checkout\ChargeRequestFactory;
use PrestaShop\Module\Wipop\Configuration\Settings;
use Wipop\Domain\Charge;
use Wipop\Domain\ChargeMethod;
use Wipop\Domain\OriginChannel;
use Wipop\Domain\PostType;
use Wipop\Domain\PostTypeMode;

final class WipopRecurringChargeApiClient implements WipopRecurringChargeClientInterface
{
    public function __construct(
        private ClientFactory $clientFactory,
        private SdkCaller $sdkCaller,
        private ChargeRequestFactory $chargeRequestFactory,
    ) {
    }

    public function create(Cart $cart, Settings $settings, string $wipopOrderId, StoredCard $storedCard): Charge
    {
        $client = $this->clientFactory->create($settings);
        $redirectUrl = Context::getContext()->link->getModuleLink('wipop', 'return', [], true);
        $params = $this->chargeRequestFactory->build(
            $cart,
            ChargeMethod::CARD,
            $redirectUrl,
            $settings,
            $wipopOrderId,
            true,
            CardCofSelection::useExisting($storedCard),
        );
        $params
            ->originChannel(OriginChannel::API)
            ->sendEmail(false)
            ->postType(new PostType(PostTypeMode::RECURRENT));

        return $this->sdkCaller->call(
            'recurring charge creation',
            static fn (): Charge => $client->chargeOperation()->create($params, $storedCard->wipopCustomerId),
        );
    }
}
