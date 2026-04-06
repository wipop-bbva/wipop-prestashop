<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Api;

use PrestaShop\Module\Wipop\Configuration\Settings;
use PrestaShop\Module\Wipop\Exception\ClientConfigurationException;
use Wipop\Client\Environment;
use Wipop\Client\WipopClient;
use Wipop\Client\WipopClientConfiguration;

final class ClientFactory
{
    /**
     * @throws ClientConfigurationException
     */
    public function create(Settings $settings): WipopClient
    {
        $merchantId = trim($settings->merchantId);
        $secretKey = trim($settings->secretKey);

        if ($merchantId === '' || $secretKey === '') {
            throw new ClientConfigurationException('Wipop credentials are incomplete.');
        }

        $configuration = new WipopClientConfiguration(
            $settings->liveMode ? Environment::PRODUCTION : Environment::SANDBOX,
            $merchantId,
            $secretKey,
        );

        return new WipopClient($configuration);
    }
}
