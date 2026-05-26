<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Refund;

use PrestaShop\Module\Wipop\Api\ClientFactory;
use PrestaShop\Module\Wipop\Api\SdkCaller;
use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use PrestaShop\Module\Wipop\Exception\ApiCallException;
use PrestaShop\Module\Wipop\Exception\ClientConfigurationException;
use Wipop\Domain\Charge;
use Wipop\Operations\Charge\Params\RefundParams;

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
