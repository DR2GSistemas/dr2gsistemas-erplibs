<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Config;

use Dr2g\ErpLibs\Config\DotEnv;
use PHPUnit\Framework\TestCase;

final class DotEnvTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/dr2g_env_' . bin2hex(random_bytes(4)) . '.env';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);

        foreach (['DR2G_TEST_A', 'DR2G_TEST_B', 'DR2G_TEST_C', 'DR2G_TEST_D', 'DR2G_TEST_E', 'DR2G_TEST_F'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    private function write(string $contents): void
    {
        file_put_contents($this->file, $contents);
    }

    public function testParsesSimplePairs(): void
    {
        $this->write("DR2G_TEST_A=uno\nDR2G_TEST_B = dos \n");

        $values = DotEnv::load($this->file);

        self::assertSame('uno', $values['DR2G_TEST_A']);
        self::assertSame('dos', $values['DR2G_TEST_B']);
    }

    public function testIgnoresCommentsAndBlankLines(): void
    {
        $this->write("# comentario\n\nDR2G_TEST_A=valor  # nota al final\n");

        $values = DotEnv::load($this->file);

        self::assertSame(['DR2G_TEST_A' => 'valor'], $values);
    }

    public function testQuotedValuesAreUnquoted(): void
    {
        $this->write("DR2G_TEST_A=\"con espacios\"\nDR2G_TEST_B='literal # no comentario'\n");

        $values = DotEnv::load($this->file);

        self::assertSame('con espacios', $values['DR2G_TEST_A']);
        self::assertSame('literal # no comentario', $values['DR2G_TEST_B']);
    }

    public function testDoubleQuotesInterpretEscapes(): void
    {
        $this->write('DR2G_TEST_A="linea1\nlinea2"' . "\n");

        self::assertSame("linea1\nlinea2", DotEnv::load($this->file)['DR2G_TEST_A']);
    }

    public function testExportPrefixIsAccepted(): void
    {
        $this->write("export DR2G_TEST_A=exportado\n");

        self::assertSame('exportado', DotEnv::load($this->file)['DR2G_TEST_A']);
    }

    public function testExistingEnvironmentWins(): void
    {
        putenv('DR2G_TEST_C=del-sistema');
        $this->write("DR2G_TEST_C=del-archivo\n");

        DotEnv::load($this->file);

        self::assertSame('del-sistema', getenv('DR2G_TEST_C'));
    }

    public function testMissingFileReturnsEmptyArray(): void
    {
        self::assertSame([], DotEnv::load($this->file . '.no-existe'));
    }

    public function testInvalidKeysAreSkipped(): void
    {
        $this->write("1INVALIDO=no\nCLAVE-VALIDA=no\n\nDR2G_TEST_D=si\n");

        $values = DotEnv::load($this->file);

        self::assertArrayNotHasKey('1INVALIDO', $values);
        self::assertSame('si', $values['DR2G_TEST_D']);
    }

    public function testEmptyValueStaysEmpty(): void
    {
        $this->write("DR2G_TEST_E=\nDR2G_TEST_F=   \n");

        $values = DotEnv::load($this->file);

        self::assertSame('', $values['DR2G_TEST_E']);
        self::assertSame('', $values['DR2G_TEST_F']);
    }
}
