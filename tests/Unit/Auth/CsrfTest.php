<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Auth;

use Dr2g\ErpLibs\Auth\Csrf\CsrfTokenManager;
use Dr2g\ErpLibs\Auth\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    public function testTokenIsStablePerSession(): void
    {
        $csrf = new CsrfTokenManager(new ArraySession());

        self::assertSame($csrf->token(), $csrf->token());
        self::assertSame(64, strlen($csrf->token()));
    }

    public function testValidatesTheIssuedToken(): void
    {
        $csrf = new CsrfTokenManager(new ArraySession());

        self::assertTrue($csrf->isValid($csrf->token()));
    }

    public function testRejectsWrongMissingOrEmptyTokens(): void
    {
        $csrf = new CsrfTokenManager(new ArraySession());

        self::assertFalse($csrf->isValid('otro'));
        self::assertFalse($csrf->isValid(null));
        self::assertFalse($csrf->isValid(''));
    }

    public function testTokenFromAnotherSessionIsRejected(): void
    {
        $first = new CsrfTokenManager(new ArraySession());
        $second = new CsrfTokenManager(new ArraySession());

        self::assertFalse($second->isValid($first->token()));
    }

    public function testFieldEscapesTheToken(): void
    {
        $csrf = new CsrfTokenManager(new ArraySession());

        $field = $csrf->field();

        self::assertStringContainsString('name="_token"', $field);
        self::assertStringContainsString($csrf->token(), $field);
    }

    public function testRotateInvalidatesThePreviousToken(): void
    {
        $csrf = new CsrfTokenManager(new ArraySession());
        $old = $csrf->token();

        $new = $csrf->rotate();

        self::assertNotSame($old, $new);
        self::assertFalse($csrf->isValid($old));
        self::assertTrue($csrf->isValid($new));
    }

    public function testNoTokenInSessionMeansInvalid(): void
    {
        self::assertFalse((new CsrfTokenManager(new ArraySession()))->isValid('cualquiera'));
    }
}
