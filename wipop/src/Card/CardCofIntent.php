<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Card;

enum CardCofIntent: string
{
    case NONE = 'none';
    case SAVE = 'save';
    case USE_EXISTING = 'use_existing';

    public static function fromValue(mixed $value): self
    {
        if (!is_scalar($value)) {
            return self::NONE;
        }

        return self::tryFrom(trim((string) $value)) ?? self::NONE;
    }
}
