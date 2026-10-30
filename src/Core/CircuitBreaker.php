<?php

namespace Dr2g\ErpLibs\Core;

/**
 * Stops calling a failing dependency after N failures and retries after a
 * cooldown. Time is injectable so the behaviour can be tested without sleeping.
 */
class CircuitBreaker
{
    public const CLOSED = 'closed';
    public const OPEN = 'open';
    public const HALF_OPEN = 'half_open';

    private int $failures = 0;
    private ?float $openedAt = null;
    private string $state = self::CLOSED;

    /**
     * @param (\Closure(): float)|null $clock returns the current epoch seconds
     */
    public function __construct(
        private readonly int $threshold = 5,
        private readonly float $cooldown = 30.0,
        private readonly ?\Closure $clock = null
    ) {
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function call(callable $operation): mixed
    {
        if ($this->state === self::OPEN && !$this->cooldownElapsed()) {
            throw new \RuntimeException('Circuit breaker is open; call rejected.');
        }

        if ($this->state === self::OPEN) {
            $this->state = self::HALF_OPEN;
        }

        try {
            $result = $operation();
            $this->recordSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    public function state(): string
    {
        return $this->state;
    }

    public function failures(): int
    {
        return $this->failures;
    }

    public function reset(): void
    {
        $this->failures = 0;
        $this->openedAt = null;
        $this->state = self::CLOSED;
    }

    private function recordSuccess(): void
    {
        $this->failures = 0;
        $this->openedAt = null;
        $this->state = self::CLOSED;
    }

    private function recordFailure(): void
    {
        $this->failures++;

        if ($this->failures >= $this->threshold) {
            $this->state = self::OPEN;
            $this->openedAt = $this->now();
        }
    }

    private function cooldownElapsed(): bool
    {
        return $this->openedAt !== null && ($this->now() - $this->openedAt) >= $this->cooldown;
    }

    private function now(): float
    {
        return $this->clock === null ? microtime(true) : ($this->clock)();
    }
}
