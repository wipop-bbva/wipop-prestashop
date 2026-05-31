<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Configuration;

final class ConfigKeys
{
    public const SETTINGS = 'WIPOP_SETTINGS';
    public const AVAILABLE_GATEWAYS_CACHE = 'WIPOP_AVAILABLE_GATEWAYS_CACHE';
    public const AUTHORIZED_ORDER_STATE_ID = 'WIPOP_AUTHORIZED_ORDER_STATE_ID';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::SETTINGS,
            self::AVAILABLE_GATEWAYS_CACHE,
            self::AUTHORIZED_ORDER_STATE_ID,
        ];
    }
}
