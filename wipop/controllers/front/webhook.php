<?php

require_once dirname(__DIR__, 2) . '/autoload.php';

use PrestaShop\Module\Wipop\Card\StoredCardRepository;
use PrestaShop\Module\Wipop\Card\StoredCardSynchronizer;
use PrestaShop\Module\Wipop\Exception\SettingsStorageException;
use PrestaShop\Module\Wipop\Exception\WebhookCredentialsException;
use PrestaShop\Module\Wipop\Exception\WebhookException;
use PrestaShop\Module\Wipop\Log\Logger;
use PrestaShop\Module\Wipop\Order\OrderPrivateNoteWriter;
use PrestaShop\Module\Wipop\Payment\PaymentAttemptRepository;
use PrestaShop\Module\Wipop\Payment\PaymentEventRepository;
use PrestaShop\Module\Wipop\Preauthorization\PrestaShopPreauthorizationOrderUpdater;
use PrestaShop\Module\Wipop\Recurring\MySqlRecurringNamedLock;
use PrestaShop\Module\Wipop\Recurring\RecurringChargeRepository;
use PrestaShop\Module\Wipop\Recurring\RecurringChargeWebhookSynchronizer;
use PrestaShop\Module\Wipop\Recurring\RecurringDateCalculator;
use PrestaShop\Module\Wipop\Recurring\RecurringInitialPaymentSynchronizer;
use PrestaShop\Module\Wipop\Recurring\RecurringSubscriptionLock;
use PrestaShop\Module\Wipop\Recurring\RecurringSubscriptionRepository;
use PrestaShop\Module\Wipop\Recurring\RecurringSubscriptionStopper;
use PrestaShop\Module\Wipop\Recurring\RecurringWebhookSynchronizer;
use PrestaShop\Module\Wipop\Webhook\WebhookAuth;
use PrestaShop\Module\Wipop\Webhook\WebhookAuthStatus;
use PrestaShop\Module\Wipop\Webhook\WebhookOrderSynchronizer;
use PrestaShop\Module\Wipop\Webhook\WebhookPaymentValidator;
use PrestaShop\Module\Wipop\Webhook\WebhookPreauthorizationOperationSynchronizer;
use PrestaShop\Module\Wipop\Webhook\WebhookRefundExtractor;
use PrestaShop\Module\Wipop\Webhook\WebhookRefundSynchronizer;
use PrestaShop\Module\Wipop\Webhook\WebhookSettingsRepository;
use PrestaShop\Module\Wipop\Webhook\WebhookTransactionHandler;
use PrestaShop\Module\Wipop\Webhook\WebhookTransactionHydrator;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

class WipopWebhookModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $logger = new Logger();

        try {
            $this->ensurePostRequest();
            $payload = $this->decodePayload($this->requestBody());
            $webhookAuth = new WebhookAuth(new WebhookSettingsRepository());
            $settings = $webhookAuth->ensureCredentialsStored();
            $isVerificationEvent = $this->isVerificationEvent($payload);
            $isAuthenticated = $this->authorizeRequest($webhookAuth, $settings, $isVerificationEvent);

            if ($isVerificationEvent) {
                $this->handleVerificationPayload($webhookAuth, $payload, $logger);
                $this->respond(200, 'OK');
            }

            $this->webhookTransactionHandler($logger)->handle($payload);
            $webhookAuth->markConnectedIfPending($isAuthenticated);

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
    private function authorizeRequest(WebhookAuth $webhookAuth, array $settings, bool $isVerificationEvent): bool
    {
        $state = $webhookAuth->state($settings);
        $authorizationStatus = $webhookAuth->authorizationStatus($settings);

        if ($authorizationStatus === WebhookAuthStatus::INVALID_BASIC) {
            throw new WebhookException('Invalid webhook authentication.', 401);
        }

        if ($authorizationStatus === WebhookAuthStatus::VALID_BASIC) {
            return true;
        }

        if ($state === WebhookAuth::STATE_CONNECTED) {
            throw new WebhookException('Webhook authentication is required.', 401);
        }

        if ($isVerificationEvent) {
            return false;
        }

        throw new WebhookException('Webhook authentication is required.', 401);
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

    private function webhookTransactionHandler(Logger $logger): WebhookTransactionHandler
    {
        if (!$this->module instanceof PaymentModule) {
            throw new RuntimeException('Wipop webhook controller requires a payment module instance.');
        }

        $paymentAttemptRepository = PaymentAttemptRepository::createDefault();
        $paymentEventRepository = PaymentEventRepository::createDefault();
        $paymentValidator = new WebhookPaymentValidator();
        $recurringSubscriptions = RecurringSubscriptionRepository::createDefault();
        $orderNotes = new OrderPrivateNoteWriter($logger, Context::getContext()->getTranslator());
        $recurringStopper = new RecurringSubscriptionStopper(
            $recurringSubscriptions,
            new RecurringSubscriptionLock(new MySqlRecurringNamedLock()),
            $logger
        );
        $orderUpdater = new PrestaShopPreauthorizationOrderUpdater(
            $orderNotes,
            SymfonyContainer::getInstance()?->get('prestashop.core.command_bus')
        );

        return new WebhookTransactionHandler(
            new WebhookTransactionHydrator(),
            $paymentAttemptRepository,
            new WebhookOrderSynchronizer(
                $paymentAttemptRepository,
                $this->module,
                $paymentValidator,
                $orderNotes
            ),
            new WebhookRefundExtractor(),
            new WebhookRefundSynchronizer(
                $paymentAttemptRepository,
                $paymentEventRepository,
                $paymentValidator,
                $recurringStopper,
                $orderNotes
            ),
            new WebhookPreauthorizationOperationSynchronizer(
                $paymentEventRepository,
                $paymentValidator,
                $orderUpdater
            ),
            new StoredCardSynchronizer(StoredCardRepository::createDefault()),
            $this->recurringWebhookSynchronizer($logger, $recurringSubscriptions),
            $logger
        );
    }

    private function recurringWebhookSynchronizer(
        Logger $logger,
        RecurringSubscriptionRepository $subscriptions,
    ): RecurringWebhookSynchronizer {
        $dateCalculator = new RecurringDateCalculator();

        return new RecurringWebhookSynchronizer(
            new RecurringInitialPaymentSynchronizer(
                $subscriptions,
                StoredCardRepository::createDefault(),
                $dateCalculator,
                $logger
            ),
            new RecurringChargeWebhookSynchronizer(
                RecurringChargeRepository::createDefault(),
                $subscriptions,
                $dateCalculator,
                $logger
            )
        );
    }
}
