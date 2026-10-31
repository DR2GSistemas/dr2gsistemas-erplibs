<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Auth;

use Dr2g\ErpLibs\Auth\Password\Hasher;
use PHPUnit\Framework\TestCase;

final class HasherTest extends TestCase
{
    public function testHashesAndVerifies(): void
    {
        $hasher = new Hasher();
        $hash = $hasher->hash('secret-123');

        self::assertNotSame('secret-123', $hash);
        self::assertTrue($hasher->verify('secret-123', $hash));
        self::assertFalse($hasher->verify('otra', $hash));
    }

    public function testEmptyHashNeverVerifies(): void
    {
        self::assertFalse((new Hasher())->verify('x', ''));
    }

    public function testHashesAreSalted(): void
    {
        $hasher = new Hasher();

        self::assertNotSame($hasher->hash('misma'), $hasher->hash('misma'));
    }

    public function testNeedsRehashWithAnObsoleteAlgorithm(): void
    {
        $hasher = new Hasher(PASSWORD_BCRYPT);

        self::assertTrue($hasher->needsRehash(password_hash('x', PASSWORD_BCRYPT, ['cost' => 4])));
    }
}
