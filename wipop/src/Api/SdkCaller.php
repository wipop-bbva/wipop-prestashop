<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Api;

use PrestaShop\Module\Wipop\Exception\ApiCallException;
use PrestaShop\Module\Wipop\Log\Logger;
use Wipop\Exception\WipopException;

final class SdkCaller
{
    public function __construct(private Logger $logger)
    {
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     *
     * @throws ApiCallException
     */
    public function call(string $operation, callable $callback)
    {
        try {
            return $callback();
        } catch (WipopException $exception) {
            $context = ['operation' => $operation];
            $apiCode = $exception->getApiCode();

            if ($apiCode !== null && $apiCode !== '') {
                $context['api_code'] = $apiCode;
            }

            $this->logger->error(
                sprintf('Wipop API error during %s: %s', $operation, $exception->getMessage()),
                $context
            );

            $message = sprintf('Unable to complete the Wipop %s: %s', $operation, $exception->getMessage());

            throw new ApiCallException($message, 0, $exception);
        } catch (\Throwable $throwable) {
            $this->logger->error(
                sprintf('Unexpected Wipop error during %s: %s', $operation, $throwable->getMessage()),
                ['operation' => $operation],
            );

            throw new ApiCallException('Unexpected error while communicating with Wipop.', 0, $throwable);
        }
    }
}
