<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Core;

use Dr2g\ErpLibs\Core\CircuitBreaker;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerTest extends TestCase
{
    private float $now = 1_000.0;

    private function breaker(int $threshold = 2, float $cooldown = 10.0): CircuitBreaker
    {
        return new CircuitBreaker($threshold, $cooldown, function () {
            return $this->now;
        });
    }

    private static function failing(): \Closure
    {
        return static fn () => throw new \RuntimeException('boom');
    }

    private function trip(CircuitBreaker $breaker): void
    {
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->call(self::failing());
            } catch (\RuntimeException) {
            }
        }
    }

    public function testOpensAfterThresholdAndRejectsCalls(): void
    {
        $breaker = $this->breaker();
        $this->trip($breaker);

        self::assertSame(CircuitBreaker::OPEN, $breaker->state());

        $this->expectExceptionMessage('Circuit breaker is open');

        $breaker->call(static fn () => 'never');
    }

    public function testClosesAgainAfterCooldownWhenTheCallSucceeds(): void
    {
        $breaker = $this->breaker();
        $this->trip($breaker);

        $this->now += 11;

        self::assertSame('ok', $breaker->call(static fn () => 'ok'));
        self::assertSame(CircuitBreaker::CLOSED, $breaker->state());
        self::assertSame(0, $breaker->failures());
    }

    public function testSuccessResetsFailureCount(): void
    {
        $breaker = $this->breaker(threshold: 3);

        try {
            $breaker->call(self::failing());
        } catch (\RuntimeException) {
        }

        $breaker->call(static fn () => 'ok');

        self::assertSame(0, $breaker->failures());
        self::assertSame(CircuitBreaker::CLOSED, $breaker->state());
    }
}
