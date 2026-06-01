<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Preauthorization;

interface WipopPreauthorizationClientInterface
{
    public function capture(string $authorizationTransactionId, float $amount): PreauthorizationResult;

    public function reverse(string $authorizationTransactionId): PreauthorizationResult;
}
