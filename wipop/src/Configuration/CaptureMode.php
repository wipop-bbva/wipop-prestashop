<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Configuration;

enum CaptureMode: string
{
    case IMMEDIATE = 'immediate';
    case MANUAL = 'manual';

    public static function fromValue(mixed $value): self
    {
        if (!is_scalar($value)) {
            return self::IMMEDIATE;
        }

        return self::tryFrom(trim((string) $value)) ?? self::IMMEDIATE;
    }
}
