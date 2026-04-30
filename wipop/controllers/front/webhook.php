<?php

require_once dirname(__DIR__, 2) . '/autoload.php';

use PrestaShop\Module\Wipop\Exception\SettingsStorageException;
use PrestaShop\Module\Wipop\Exception\WebhookCredentialsException;
use PrestaShop\Module\Wipop\Exception\WebhookException;
use PrestaShop\Module\Wipop\Log\Logger;
use PrestaShop\Module\Wipop\Webhook\WebhookAuth;
use PrestaShop\Module\Wipop\Webhook\WebhookAuthStatus;
use PrestaShop\Module\Wipop\Webhook\WebhookSettingsRepository;

class WipopWebhookModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $logger = $this->logger();

        try {
            $this->ensurePostRequest();
            $payload = $this->decodePayload($this->requestBody());
            $webhookAuth = $this->webhookAuth();
            $settings = $webhookAuth->ensureCredentialsStored();
            $isAuthenticated = $this->authorizeRequest($webhookAuth, $settings);

            if ($this->isVerificationEvent($payload)) {
                $this->handleVerificationPayload($webhookAuth, $payload, $logger);
                $this->respond(200, 'OK');
            }

            $webhookAuth->markConnectedIfPending($isAuthenticated);

            $logger->info('Webhook payload acknowledged.', [
                'authenticated' => $isAuthenticated,
            ]);
            $this->respond(200, 'OK');
        } catch (WebhookException $exception) {
            $logger->error($exception->getMessage(), [
                'status_code' => $exception->getStatusCode(),
            ]);
            $this->respond($exception->getStatusCode(), $exception->getMessage());
        } catch (WebhookCredentialsException | SettingsStorageException $exception) {
            $logger->error('Webhook internal configuration error: ' . $exception->getMessage(), [
                'error_class' => $exception::class,
            ]);
            $this->respond(500, 'Error processing the webhook.');
        } catch (Throwable $throwable) {
            $logger->error('Unexpected webhook error: ' . $throwable->getMessage(), [
                'error_class' => $throwable::class,
            ]);
            $this->respond(500, 'Error processing the webhook.');
        }
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function authorizeRequest(WebhookAuth $webhookAuth, array $settings): bool
    {
        $state = $webhookAuth->state($settings);
        $authorizationStatus = $webhookAuth->authorizationStatus($settings);

        if ($authorizationStatus === WebhookAuthStatus::INVALID_BASIC) {
            throw new WebhookException('Invalid webhook authentication.', 401);
        }

        if ($authorizationStatus === WebhookAuthStatus::VALID_BASIC) {
            return true;
        }

        if ($webhookAuth->isAuthorizationRequired($state)) {
            throw new WebhookException('Webhook authentication is required.', 401);
        }

        return false;
    }

    private function ensurePostRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (strtoupper((string) $method) !== 'POST') {
            throw new WebhookException('Method not allowed', 405);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $body): array
    {
        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new WebhookException('Could not decode the body payload', 400, $exception);
        }

        if (!is_array($payload)) {
            throw new WebhookException('Invalid body.', 400);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isVerificationEvent(array $payload): bool
    {
        return strtolower((string) ($payload['type'] ?? '')) === 'verification';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function handleVerificationPayload(WebhookAuth $webhookAuth, array $payload, Logger $logger): void
    {
        $eventId = trim((string) ($payload['id'] ?? ''));
        $verificationCode = trim((string) ($payload['verification_code'] ?? ''));

        if ($eventId === '' || $verificationCode === '') {
            throw new WebhookException('Invalid verification payload.', 400);
        }

        $updated = $webhookAuth->markVerificationEvent($eventId, $verificationCode);
        $logger->info('Webhook verification event received.', [
            'event_id' => $eventId,
            'updated' => $updated,
        ]);
    }

    private function requestBody(): string
    {
        $body = file_get_contents('php://input');

        return $body === false ? '' : $body;
    }

    private function respond(int $statusCode, string $message): void
    {
        if ($statusCode === 401) {
            header('WWW-Authenticate: Basic realm="Wipop webhook"');
        }

        http_response_code($statusCode);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
        exit;
    }

    private function webhookAuth(): WebhookAuth
    {
        return new WebhookAuth(new WebhookSettingsRepository());
    }

    private function logger(): Logger
    {
        return new Logger();
    }
}
