<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Auth;

use Dr2g\ErpLibs\Auth\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function testStoresAndReadsValues(): void
    {
        $session = new ArraySession();

        $session->set('user_id', 7);

        self::assertSame(7, $session->get('user_id'));
        self::assertTrue($session->has('user_id'));
        self::assertNull($session->get('missing'));
        self::assertSame('x', $session->get('missing', 'x'));
    }

    public function testForgetAndFlush(): void
    {
        $session = new ArraySession();

        $session->set('a', 1);
        $session->forget('a');

        self::assertFalse($session->has('a'));

        $session->set('b', 2);
        $session->flush();

        self::assertFalse($session->has('b'));
    }

    public function testPullRemovesTheValue(): void
    {
        $session = new ArraySession();

        $session->set('flash', 'ok');

        self::assertSame('ok', $session->pull('flash'));
        self::assertFalse($session->has('flash'));
    }

    public function testRegenerateChangesTheId(): void
    {
        $session = new ArraySession();
        $before = $session->getId();

        self::assertTrue($session->regenerate());

        self::assertNotSame($before, $session->getId());
    }

    public function testRegenerateCanDropOldData(): void
    {
        $session = new ArraySession();
        $session->set('user_id', 1);

        $session->regenerate(true);

        self::assertFalse($session->has('user_id'));
    }

    public function testStartIsIdempotent(): void
    {
        $session = new ArraySession();

        $session->start();
        $id = $session->getId();
        $session->start();

        self::assertTrue($session->isStarted());
        self::assertSame($id, $session->getId());
    }
}
