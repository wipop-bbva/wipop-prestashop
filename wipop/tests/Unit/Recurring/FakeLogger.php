<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Recurring;

use PrestaShop\Module\Wipop\Log\LoggerInterface;

final class FakeLogger implements LoggerInterface
{
    public int $errors = 0;

    public function error(string $message, array $context = []): void
    {
        ++$this->errors;
    }
}
