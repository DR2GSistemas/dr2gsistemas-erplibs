<?php

namespace Dr2g\ErpLibs\Log;

/**
 * PSR-3 compatible logger with a swappable sink. The default sink writes to
 * stderr so nothing depends on a logging implementation at runtime.
 */
class Logger
{
    public const EMERGENCY = 'emergency';
    public const ALERT = 'alert';
    public const CRITICAL = 'critical';
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const NOTICE = 'notice';
    public const INFO = 'info';
    public const DEBUG = 'debug';

    private const LEVELS = [
        self::DEBUG => 0,
        self::INFO => 1,
        self::NOTICE => 2,
        self::WARNING => 3,
        self::ERROR => 4,
        self::CRITICAL => 5,
        self::ALERT => 6,
        self::EMERGENCY => 7,
    ];

    /** @var null|\Closure(string, array<string, mixed>): void */
    private ?\Closure $sink;

    /** @param null|callable(string, array<string, mixed>): void $sink */
    public function __construct(
        private readonly string $level = self::WARNING,
        ?callable $sink = null
    ) {
        $this->sink = $sink === null ? null : \Closure::fromCallable($sink);
    }

    /** @param array<string, mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        if (self::LEVELS[$level] < self::LEVELS[$this->level]) {
            return;
        }

        if ($this->sink !== null) {
            ($this->sink)($level, ['message' => $message, 'context' => $context]);
            return;
        }

        $interpolated = preg_replace_callback(
            '/\{(\w+)\}/',
            static fn (array $m) => (string) ($context[$m[1]] ?? $m[0]),
            $message
        );

        fwrite(STDERR, sprintf("[%s] %s %s\n", strtoupper($level), date('Y-m-d H:i:s'), $interpolated));
    }

    /** @param array<string, mixed> $context */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function alert(string $message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function notice(string $message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }
}
