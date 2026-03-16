<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Configuration;

final class ConfigKeys
{
    public const LIVE_MODE = 'WIPOP_LIVE_MODE';
    public const MERCHANT_ID = 'WIPOP_MERCHANT_ID';
    public const SECRET_KEY = 'WIPOP_SECRET_KEY';
    public const PUBLIC_SECRET_KEY = 'WIPOP_PUBLIC_SECRET_KEY';
    public const TERMINAL_ID = 'WIPOP_TERMINAL_ID';

    /**
     * @return array<string, bool|int|string>
     */
    public static function defaults(): array
    {
        return [
            self::LIVE_MODE => false,
            self::MERCHANT_ID => '',
            self::SECRET_KEY => '',
            self::PUBLIC_SECRET_KEY => '',
            self::TERMINAL_ID => Settings::DEFAULT_TERMINAL_ID,
        ];
    }

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::LIVE_MODE,
            self::MERCHANT_ID,
            self::SECRET_KEY,
            self::PUBLIC_SECRET_KEY,
            self::TERMINAL_ID,
        ];
    }
}
