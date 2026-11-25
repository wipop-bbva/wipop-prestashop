<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Recurring;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Recurring\RecurringAutoRunner;

final class RecurringAutoRunnerTest extends TestCase
{
    public function testItSkipsUntilIntervalHasElapsed(): void
    {
        $state = new FakeRecurringAutoRunnerState(time());
        $runner = new FakeRecurringDueChargeRunner($state);
        $lock = new FakeRecurringNamedLock();

        $this->autoRunner($runner, $state, $lock)->maybeRun();

        self::assertSame(0, $lock->calls);
        self::assertSame(0, $runner->calls);
    }

    public function testItSkipsWhenGlobalLockIsHeld(): void
    {
        $state = new FakeRecurringAutoRunnerState(null);
        $runner = new FakeRecurringDueChargeRunner($state);
        $lock = new FakeRecurringNamedLock();
        $lock->available = false;

        $this->autoRunner($runner, $state, $lock)->maybeRun();

        self::assertSame(1, $lock->calls);
        self::assertSame(0, $runner->calls);
    }

    public function testItRevalidatesIntervalInsideTheLock(): void
    {
        $state = new FakeRecurringAutoRunnerState(null);
        $runner = new FakeRecurringDueChargeRunner($state);
        $lock = new FakeRecurringNamedLock();
        $lock->beforeCallback = static function () use ($state): void {
            $state->lastAttemptAt = time();
        };

        $this->autoRunner($runner, $state, $lock)->maybeRun();

        self::assertSame(1, $lock->calls);
        self::assertSame(0, $runner->calls);
        self::assertSame(0, $state->markCalls);
    }

    public function testItMarksAttemptBeforeRunningDueCharges(): void
    {
        $state = new FakeRecurringAutoRunnerState(null);
        $runner = new FakeRecurringDueChargeRunner($state);
        $lock = new FakeRecurringNamedLock();

        $this->autoRunner($runner, $state, $lock)->maybeRun();

        self::assertSame(1, $runner->calls);
        self::assertSame(1, $runner->lastLimit);
        self::assertTrue($runner->attemptWasMarkedBeforeRun);
        self::assertSame(1, $state->markCalls);
    }

    public function testItLogsRunnerErrorsWithoutPropagatingThem(): void
    {
        $state = new FakeRecurringAutoRunnerState(null);
        $runner = new FakeRecurringDueChargeRunner($state);
        $runner->throws = true;
        $logger = new FakeLogger();

        $this->autoRunner($runner, $state, new FakeRecurringNamedLock(), $logger)->maybeRun();

        self::assertSame(1, $runner->calls);
        self::assertSame(1, $logger->errors);
    }

    private function autoRunner(
        FakeRecurringDueChargeRunner $runner,
        FakeRecurringAutoRunnerState $state,
        FakeRecurringNamedLock $lock,
        ?FakeLogger $logger = null,
    ): RecurringAutoRunner {
        return new RecurringAutoRunner($runner, $state, $lock, $logger ?? new FakeLogger());
    }
}
