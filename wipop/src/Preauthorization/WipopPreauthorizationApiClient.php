<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Preauthorization;

use PrestaShop\Module\Wipop\Api\ClientFactory;
use PrestaShop\Module\Wipop\Api\SdkCaller;
use PrestaShop\Module\Wipop\Configuration\SettingsRepository;
use PrestaShop\Module\Wipop\Exception\ApiCallException;
use PrestaShop\Module\Wipop\Exception\ClientConfigurationException;
use Wipop\Domain\Charge;
use Wipop\Operations\Charge\Params\CaptureParams;
use Wipop\Operations\Charge\Params\ReversalParams;

final class WipopPreauthorizationApiClient implements WipopPreauthorizationClientInterface
{
    public function __construct(
        private SettingsRepository $settingsRepository,
        private ClientFactory $clientFactory,
        private SdkCaller $sdkCaller,
        private WipopPreauthorizationResponseMapper $responseMapper,
    ) {
    }

    /**
     * @throws ApiCallException
     * @throws ClientConfigurationException
     * @throws PreauthorizationException
     */
    public function capture(string $authorizationTransactionId, float $amount): PreauthorizationResult
    {
        $client = $this->clientFactory->create($this->settingsRepository->get());
        $params = (new CaptureParams())->amount($amount);

        /** @var Charge $charge */
        $charge = $this->sdkCaller->call(
            'charge.capture',
            fn () => $client->chargeOperation()->capture($authorizationTransactionId, $params),
        );

        return $this->responseMapper->map($authorizationTransactionId, $charge);
    }

    /**
     * @throws ApiCallException
     * @throws ClientConfigurationException
     * @throws PreauthorizationException
     */
    public function reverse(string $authorizationTransactionId): PreauthorizationResult
    {
        $client = $this->clientFactory->create($this->settingsRepository->get());

        /** @var Charge $charge */
        $charge = $this->sdkCaller->call(
            'charge.reversal',
            fn () => $client->chargeOperation()->reversal($authorizationTransactionId, new ReversalParams()),
        );

        return $this->responseMapper->map($authorizationTransactionId, $charge);
    }
}
