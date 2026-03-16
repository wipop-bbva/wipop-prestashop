<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Log;

final class Logger
{
    /**
     * @param array<string, int|string> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(1, $message, $context);
    }

    /**
     * @param array<string, int|string> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(2, $message, $context);
    }

    /**
     * @param array<string, int|string> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(3, $message, $context);
    }

    /**
     * @param array<string, int|string> $context
     */
    private function log(int $severity, string $message, array $context): void
    {
        $formattedMessage = '[wipop] ' . $message;

        if ($context !== []) {
            $formattedContext = json_encode($context);
            if (is_string($formattedContext)) {
                $formattedMessage .= ' ' . $formattedContext;
            }
        }

        \PrestaShopLogger::addLog($formattedMessage, $severity, null, 'Wipop');
    }
}
