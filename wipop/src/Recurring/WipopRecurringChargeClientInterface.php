<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

use Cart;
use PrestaShop\Module\Wipop\Card\StoredCard;
use PrestaShop\Module\Wipop\Configuration\Settings;
use PrestaShop\Module\Wipop\Exception\ApiCallException;
use PrestaShop\Module\Wipop\Exception\ClientConfigurationException;
use Wipop\Domain\Charge;

interface WipopRecurringChargeClientInterface
{
    /**
     * @throws ApiCallException
     * @throws ClientConfigurationException
     */
    public function create(Cart $cart, Settings $settings, string $wipopOrderId, StoredCard $storedCard): Charge;
}
