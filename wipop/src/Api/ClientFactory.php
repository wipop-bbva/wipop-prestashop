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
        return new WipopClient($this->createConfiguration($settings));
    }

    /**
     * @throws ClientConfigurationException
     */
    public function createConfiguration(Settings $settings): WipopClientConfiguration
    {
        $merchantId = trim($settings->merchantId);
        $secretKey = trim($settings->secretKey);

        if ($merchantId === '' || $secretKey === '') {
            throw new ClientConfigurationException('Wipop credentials are incomplete.');
        }

        return new WipopClientConfiguration(
            $settings->liveMode ? Environment::PRODUCTION : Environment::SANDBOX,
            $merchantId,
            $secretKey,
        );
    }
}
