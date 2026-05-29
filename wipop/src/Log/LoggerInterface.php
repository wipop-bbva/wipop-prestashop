<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Log;

interface LoggerInterface
{
    /**
     * @param array<string, int|string> $context
     */
    public function error(string $message, array $context = []): void;
}
