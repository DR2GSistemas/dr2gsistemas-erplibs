<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Validation;

use Dr2g\ErpLibs\Core\DatabaseManager;
use Dr2g\ErpLibs\Exceptions\ValidationException;
use Dr2g\ErpLibs\Models\Attributes\Column;
use Dr2g\ErpLibs\Models\Attributes\Table;
use Dr2g\ErpLibs\Models\Model;
use Dr2g\ErpLibs\Testing\FakeConnection;
use Dr2g\ErpLibs\Validation\Rule;
use Dr2g\ErpLibs\Validation\Validator;
use PHPUnit\Framework\TestCase;

#[Table(name: 'accounts')]
final class Account extends Model
{
    #[Column(type: 'VARCHAR(100)', validation: ['required', 'min:3'])]
    public string $name;

    #[Column(type: 'VARCHAR(190)', validation: ['required', 'email'])]
    public string $email;
}

final class ValidatorTest extends TestCase
{
    public function testValidateReturnsMessagesPerAttribute(): void
    {
        $errors = Validator::validate(
            ['name' => 'ab', 'email' => 'nope'],
            ['name' => ['required', 'min:3'], 'email' => ['email']]
        );

        self::assertSame(
            [
                'name' => ['must be at least 3 characters'],
                'email' => ['must be a valid email'],
            ],
            $errors
        );
    }

    public function testAssertThrowsWithStructuredErrors(): void
    {
        try {
            Validator::assert(['email' => null], ['email' => 'required']);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['email' => ['is required']], $e->errors);
        }
    }

    public function testCustomRulesCanBeRegistered(): void
    {
        Rule::extend('alpha_dash', static fn ($value) => preg_match('/^[a-z_-]+$/', (string) $value) ? null : 'must be alpha-dash');

        self::assertNull(Rule::validate('alpha_dash', 'azul-erp'));
        self::assertSame('must be alpha-dash', Rule::validate('alpha_dash', 'azul erp'));
    }

    public function testUnknownRuleFailsLoudly(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Rule::validate('nope', 'x');
    }

    public function testModelReadsRulesFromColumnAttributes(): void
    {
        self::assertSame(
            ['name' => ['required', 'min:3'], 'email' => ['required', 'email']],
            Account::getValidationRules()
        );
    }

    public function testSaveThrowsWhenDeclaredRulesFail(): void
    {
        $connection = new FakeConnection();
        $manager = new DatabaseManager(['default' => 'test', 'connections' => ['test' => []]]);
        (new \ReflectionProperty(DatabaseManager::class, 'connections'))->setValue($manager, ['test' => $connection]);
        Model::setDbManager($manager);

        $this->expectException(ValidationException::class);

        Account::create(['name' => 'ab', 'email' => 'bad']);
    }
}
