<?php
/**
 * 2007-2026 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2026 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

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

if (!defined('_PS_VERSION_')) {
    exit;
}

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
