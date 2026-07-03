<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Checkout;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Checkout\ReturnUrlToken;

final class ReturnUrlTokenTest extends TestCase
{
    public function testItCreatesSignedAttemptToken(): void
    {
        self::assertSame(
            hash_hmac('sha256', '42', 'test-secret'),
            (new ReturnUrlToken('test-secret'))->create(42)
        );
    }

    public function testItMatchesOnlyTheExpectedAttemptAndSecret(): void
    {
        $token = new ReturnUrlToken('test-secret');
        $signedAttempt = $token->create(42);

        self::assertTrue($token->matches(42, $signedAttempt));
        self::assertFalse($token->matches(41, $signedAttempt));
        self::assertFalse((new ReturnUrlToken('other-secret'))->matches(42, $signedAttempt));
        self::assertFalse($token->matches(0, $signedAttempt));
    }
}
