<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Configuration;

use Configuration as PrestaShopConfiguration;

final class SettingsRepository
{
    public function installDefaults(): bool
    {
        $result = true;

        foreach (ConfigKeys::defaults() as $key => $value) {
            $result = $result && PrestaShopConfiguration::updateValue($key, $value);
        }

        return $result;
    }

    public function deleteAll(): bool
    {
        $result = true;

        foreach (ConfigKeys::all() as $key) {
            $result = $result && PrestaShopConfiguration::deleteByName($key);
        }

        return $result;
    }

    public function get(): Settings
    {
        $defaults = ConfigKeys::defaults();

        return new Settings(
            (bool) PrestaShopConfiguration::get(ConfigKeys::LIVE_MODE, $defaults[ConfigKeys::LIVE_MODE]),
            trim((string) PrestaShopConfiguration::get(ConfigKeys::MERCHANT_ID, $defaults[ConfigKeys::MERCHANT_ID])),
            trim((string) PrestaShopConfiguration::get(ConfigKeys::SECRET_KEY, $defaults[ConfigKeys::SECRET_KEY])),
            trim((string) PrestaShopConfiguration::get(
                ConfigKeys::PUBLIC_SECRET_KEY,
                $defaults[ConfigKeys::PUBLIC_SECRET_KEY]
            )),
            (int) PrestaShopConfiguration::get(ConfigKeys::TERMINAL_ID, $defaults[ConfigKeys::TERMINAL_ID])
        );
    }

    public function save(Settings $settings): bool
    {
        return PrestaShopConfiguration::updateValue(ConfigKeys::LIVE_MODE, $settings->liveMode)
            && PrestaShopConfiguration::updateValue(ConfigKeys::MERCHANT_ID, $settings->merchantId)
            && PrestaShopConfiguration::updateValue(ConfigKeys::SECRET_KEY, $settings->secretKey)
            && PrestaShopConfiguration::updateValue(ConfigKeys::PUBLIC_SECRET_KEY, $settings->publicSecretKey)
            && PrestaShopConfiguration::updateValue(ConfigKeys::TERMINAL_ID, $settings->terminalId);
    }
}
