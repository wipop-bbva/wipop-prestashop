<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Configuration;

final class ConfigKeys
{
    public const SETTINGS = 'WIPOP_SETTINGS';
    public const AVAILABLE_GATEWAYS_CACHE = 'WIPOP_AVAILABLE_GATEWAYS_CACHE';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::SETTINGS,
            self::AVAILABLE_GATEWAYS_CACHE,
        ];
    }
}
