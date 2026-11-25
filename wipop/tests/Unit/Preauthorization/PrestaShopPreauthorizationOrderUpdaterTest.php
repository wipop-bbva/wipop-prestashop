<?php

declare(strict_types=1);

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses, Generic.Files.LineLength.TooLong

namespace {
    if (!class_exists(Configuration::class, false)) {
        final class Configuration
        {
            public static function get(string $key): int
            {
                return $key === 'PS_OS_PAYMENT' ? 2 : 6;
            }
        }
    }

    if (!class_exists(Currency::class, false)) {
        final class Currency
        {
            public int $id;
            public string $iso_code = 'EUR';

            public function __construct(int $id)
            {
                $this->id = $id;
            }
        }
    }

    if (!class_exists(Order::class, false)) {
        final class Order
        {
            /** @var array<int, array<string, string>> */
            public static array $payments = [];
            public int $id;
            public int $id_currency = 1;
            public int $id_customer = 34;
            public int $current_state = 14;

            public function __construct(int $id)
            {
                $this->id = $id;
            }

            /**
             * @return object[]
             */
            public function getOrderPaymentCollection(): array
            {
                return array_map(
                    static fn (array $payment): object => (object) ['transaction_id' => $payment['transaction_id']],
                    self::$payments
                );
            }

            public function addOrderPayment(
                string $amount,
                string $paymentMethod,
                string $transactionId,
                Currency $currency,
                string $date,
                mixed $invoice = null,
                ?int $employeeId = null,
            ): bool {
                self::$payments[] = [
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                    'transaction_id' => $transactionId,
                ];

                return true;
            }

            public static function reset(): void
            {
                self::$payments = [];
            }
        }
    }

    if (!class_exists(OrderHistory::class, false)) {
        final class OrderHistory
        {
            public static ?int $lastStateId = null;
            public static ?bool $lastUseExistingPayment = null;
            public static ?bool $lastAutodate = null;
            public static ?bool $lastEmailAutodate = null;
            public static bool $emailResult = true;
            public int $id = 0;
            public int $id_order;

            public function changeIdOrderState(int $stateId, int $orderId, bool $useExistingPayment = false): void
            {
                self::$lastStateId = $stateId;
                self::$lastUseExistingPayment = $useExistingPayment;
            }

            public function add(bool $autodate = true): bool
            {
                self::$lastAutodate = $autodate;
                $this->id = 123;

                return true;
            }

            public function addWithemail(bool $autodate = true): bool
            {
                self::$lastEmailAutodate = $autodate;

                return $this->add($autodate) && self::$emailResult;
            }

            public static function reset(): void
            {
                self::$lastStateId = null;
                self::$lastUseExistingPayment = null;
                self::$lastAutodate = null;
                self::$lastEmailAutodate = null;
                self::$emailResult = true;
            }
        }
    }

    if (!class_exists(Message::class, false)) {
        final class Message
        {
            public string $message = '';
            public int $id_order = 0;
            public int $id_customer = 0;
            public bool $private = false;
            public int $id_employee = 0;

            public function add(): bool
            {
                return true;
            }
        }
    }

    if (!class_exists(Validate::class, false)) {
        final class Validate
        {
            public static function isLoadedObject(object $object): bool
            {
                return isset($object->id) && (int) $object->id > 0;
            }
        }
    }
}

namespace PrestaShop\PrestaShop\Core\CommandBus {
    if (!interface_exists(CommandBusInterface::class, false)) {
        interface CommandBusInterface
        {
            public function handle(object $command): mixed;
        }
    }
}

namespace Symfony\Contracts\Translation {
    if (!interface_exists(TranslatorInterface::class, false)) {
        interface TranslatorInterface
        {
            /**
             * @param array<string, string> $parameters
             */
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string;

            public function getLocale(): string;
        }
    }
}

namespace PrestaShop\Module\Wipop\Tests\Unit\Preauthorization {
    use PHPUnit\Framework\TestCase;
    use PrestaShop\Module\Wipop\Log\LoggerInterface;
    use PrestaShop\Module\Wipop\Order\OrderPrivateNoteWriter;
    use PrestaShop\Module\Wipop\Preauthorization\PrestaShopPreauthorizationOrderUpdater;
    use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
    use Symfony\Contracts\Translation\TranslatorInterface;

    final class PrestaShopPreauthorizationOrderUpdaterTest extends TestCase
    {
        public function testItLinksTheCapturePaymentWhenChangingToPaidState(): void
        {
            \Order::reset();
            \OrderHistory::reset();

            (new PrestaShopPreauthorizationOrderUpdater($this->orderNotes()))->markCaptured(
                56,
                1.0,
                'EUR',
                'tx_capture',
                7
            );

            self::assertSame([[
                'amount' => '1.000000',
                'payment_method' => 'Wipop',
                'transaction_id' => 'tx_capture',
            ]], \Order::$payments);
            self::assertSame(2, \OrderHistory::$lastStateId);
            self::assertTrue(\OrderHistory::$lastUseExistingPayment);
            self::assertTrue(\OrderHistory::$lastEmailAutodate);
            self::assertTrue(\OrderHistory::$lastAutodate);
        }

        public function testItKeepsExistingPaymentTransitionWhenCommandBusIsAvailable(): void
        {
            \Order::reset();
            \OrderHistory::reset();
            \Order::$payments[] = ['transaction_id' => 'tx_capture'];
            $commandBus = new class implements CommandBusInterface {
                public int $calls = 0;

                public function handle(object $command): mixed
                {
                    ++$this->calls;

                    return null;
                }
            };

            (new PrestaShopPreauthorizationOrderUpdater($this->orderNotes(), $commandBus))->markCaptured(
                56,
                1.0,
                'EUR',
                'tx_capture',
                7
            );

            self::assertSame(0, $commandBus->calls);
            self::assertTrue(\OrderHistory::$lastUseExistingPayment);
            self::assertTrue(\OrderHistory::$lastEmailAutodate);
        }

        public function testItDoesNotFailCapturedTransitionWhenOnlyEmailFails(): void
        {
            \Order::reset();
            \OrderHistory::reset();
            \OrderHistory::$emailResult = false;

            (new PrestaShopPreauthorizationOrderUpdater($this->orderNotes()))->markCaptured(
                56,
                1.0,
                'EUR',
                'tx_capture',
                7
            );

            self::assertTrue(\OrderHistory::$lastUseExistingPayment);
            self::assertTrue(\OrderHistory::$lastEmailAutodate);
        }

        private function orderNotes(): OrderPrivateNoteWriter
        {
            return new OrderPrivateNoteWriter(
                $this->createMock(LoggerInterface::class),
                new class implements TranslatorInterface {
                    /**
                     * @param array<string, string> $parameters
                     */
                    public function trans(
                        string $id,
                        array $parameters = [],
                        ?string $domain = null,
                        ?string $locale = null,
                    ): string {
                        return strtr($id, $parameters);
                    }

                    public function getLocale(): string
                    {
                        return 'en';
                    }
                }
            );
        }
    }
}
