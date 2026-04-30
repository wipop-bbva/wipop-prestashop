<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Webhook;

enum WebhookAuthStatus: string
{
    case MISSING = 'missing';
    case VALID_BASIC = 'valid_basic';
    case INVALID_BASIC = 'invalid_basic';
    case OTHER_SCHEME = 'other_scheme';
}
