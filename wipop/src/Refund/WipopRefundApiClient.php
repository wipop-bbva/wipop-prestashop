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

namespace PrestaShop\Module\Wipop\Refund;

use PrestaShop\Module\Wipop\Api\ClientFactory;
use PrestaShop\Module\Wipop\Api\SdkCaller;
use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use PrestaShop\Module\Wipop\Exception\ApiCallException;
use PrestaShop\Module\Wipop\Exception\ClientConfigurationException;
use Wipop\Domain\Charge;
use Wipop\Operations\Charge\Params\RefundParams;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class WipopRefundApiClient implements WipopRefundClientInterface
{
    public function __construct(
        private SettingsRepository $settingsRepository,
        private ClientFactory $clientFactory,
        private SdkCaller $sdkCaller,
        private WipopRefundResponseMapper $responseMapper,
    ) {
    }

    /**
     * @throws ApiCallException
     * @throws ClientConfigurationException
     * @throws ManualRefundException
     */
    public function refundCharge(string $chargeTransactionId, float $amount): WipopRefundResult
    {
        $client = $this->clientFactory->create($this->settingsRepository->get());
        $params = (new RefundParams())->amount($amount);

        /** @var Charge $charge */
        $charge = $this->sdkCaller->call(
            'charge.refund',
            fn () => $client->chargeOperation()->refund($chargeTransactionId, $params),
        );

        return $this->responseMapper->map($chargeTransactionId, $charge);
    }
}
