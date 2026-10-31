<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Auth;

use Dr2g\ErpLibs\Auth\LoginThrottle;
use Dr2g\ErpLibs\Cache\ArrayCache;
use PHPUnit\Framework\TestCase;

final class LoginThrottleTest extends TestCase
{
    /**
     * @return array{LoginThrottle, \stdClass}
     */
    private function throttle(array $options = []): array
    {
        $clock = new \stdClass();
        $clock->now = 1_700_000_000;

        $throttle = new LoginThrottle(
            new ArrayCache(),
            $options,
            static fn (): int => $clock->now
        );

        return [$throttle, $clock];
    }

    public function testAllowsUpToTheLimit(): void
    {
        [$throttle] = $this->throttle(['per_ip' => 3]);

        self::assertTrue($throttle->attempt('ana@example.com', '1.1.1.1')['allowed']);
        self::assertTrue($throttle->attempt('ana@example.com', '1.1.1.1')['allowed']);
        self::assertTrue($throttle->attempt('ana@example.com', '1.1.1.1')['allowed']);

        $blocked = $throttle->attempt('ana@example.com', '1.1.1.1');

        self::assertFalse($blocked['allowed']);
        self::assertGreaterThan(0, $blocked['retry_after']);
    }

    public function testTheLimitIsPerEmailAndIpPair(): void
    {
        [$throttle] = $this->throttle(['per_ip' => 1]);

        $throttle->attempt('ana@example.com', '1.1.1.1');

        // Otro email desde la misma IP: cuenta aparte.
        self::assertTrue($throttle->attempt('luis@example.com', '1.1.1.1')['allowed']);
        // Mismo email desde otra IP: cuenta aparte.
        self::assertTrue($throttle->attempt('ana@example.com', '2.2.2.2')['allowed']);
    }

    public function testThePerEmailWindowBlocksSprayingFromManyIps(): void
    {
        [$throttle] = $this->throttle(['per_ip' => 100, 'per_email' => 2]);

        $throttle->attempt('ana@example.com', '1.1.1.1');
        $throttle->attempt('ana@example.com', '2.2.2.2');

        $blocked = $throttle->attempt('ana@example.com', '3.3.3.3');

        self::assertFalse($blocked['allowed']);
    }

    public function testClearResetsTheCounters(): void
    {
        [$throttle] = $this->throttle(['per_ip' => 1]);

        $throttle->attempt('ana@example.com', '1.1.1.1');
        self::assertFalse($throttle->attempt('ana@example.com', '1.1.1.1')['allowed']);

        $throttle->clear('ana@example.com', '1.1.1.1');

        self::assertTrue($throttle->attempt('ana@example.com', '1.1.1.1')['allowed']);
    }

    public function testTheEmailIsCaseInsensitive(): void
    {
        [$throttle] = $this->throttle(['per_ip' => 1]);

        $throttle->attempt('Ana@Example.com', '1.1.1.1');

        self::assertFalse($throttle->attempt('ana@example.com', '1.1.1.1')['allowed']);
    }

    public function testTheWindowRollsOver(): void
    {
        [$throttle, $clock] = $this->throttle(['per_ip' => 1, 'window' => 60]);

        $throttle->attempt('ana@example.com', '1.1.1.1');
        self::assertFalse($throttle->attempt('ana@example.com', '1.1.1.1')['allowed']);

        $clock->now += 61;

        self::assertTrue($throttle->attempt('ana@example.com', '1.1.1.1')['allowed']);
    }
}
