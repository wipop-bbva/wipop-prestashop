<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Checkout;

use function str_replace;
use function str_starts_with;
use function strtolower;
use function trim;

enum WipopLanguage: string
{
    case SPANISH = 'es-ES';
    case CATALAN = 'ca-ES';
    case BASQUE = 'eu-ES';
    case GALICIAN = 'gl-ES';
    case ENGLISH = 'en-GB';

    public static function fromPrestashopLocale(?string $locale): self
    {
        $normalizedLocale = strtolower(str_replace('_', '-', trim((string) $locale)));
        if ($normalizedLocale === '') {
            return self::SPANISH;
        }

        return match (true) {
            str_starts_with($normalizedLocale, 'ca') => self::CATALAN,
            str_starts_with($normalizedLocale, 'eu') => self::BASQUE,
            str_starts_with($normalizedLocale, 'gl') => self::GALICIAN,
            str_starts_with($normalizedLocale, 'en') => self::ENGLISH,
            str_starts_with($normalizedLocale, 'es') => self::SPANISH,
            default => self::SPANISH,
        };
    }
}
