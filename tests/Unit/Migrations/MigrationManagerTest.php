<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Migrations;

use Dr2g\ErpLibs\Migrations\MigrationManager;
use Dr2g\ErpLibs\Migrations\MigrationRepository;
use Dr2g\ErpLibs\Testing\FakeConnection;
use PHPUnit\Framework\TestCase;

final class MigrationManagerTest extends TestCase
{
    private string $path;
    private FakeConnection $connection;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/dr2g_test_' . bin2hex(random_bytes(4));
        mkdir($this->path, 0755, true);
        $this->connection = new FakeConnection();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->path}/*.php") ?: []);
        rmdir($this->path);
    }

    private function manager(): MigrationManager
    {
        return new MigrationManager(
            $this->connection,
            new MigrationRepository($this->connection),
            $this->path
        );
    }

    public function testMigrateExecutesDdlAndReturnsBatch(): void
    {
        file_put_contents("{$this->path}/2026_01_01_000000_create_users_table.php", <<<'PHP'
        <?php
        use Dr2g\ErpLibs\Migrations\Migration;
        class CreateUsersTable extends Migration
        {
            public function up(): void
            {
                $this->schema->create('users', function ($table) {
                    $table->id();
                    $table->string('email', 191)->unique();
                    $table->decimal('balance', 10, 2)->default(0);
                    $table->boolean('active')->default(true);
                    $table->unsignedBigInteger('team_id');
                    $table->foreignIdFor('role_id');
                    $table->index(['email', 'active']);
                    $table->timestamps();
                });
            }
            public function down(): void { $this->schema->drop('users'); }
        }
        PHP);

        $result = $this->manager()->migrate();
        $ddl = $this->connection->ddl();

        self::assertSame('success', $result['status']);
        self::assertSame(1, $result['count']);
        self::assertSame(1, $result['batch']);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS users', $ddl);
        self::assertStringContainsString('id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', $ddl);
        self::assertStringContainsString('UNIQUE INDEX users_email_unique (email)', $ddl);
        self::assertStringContainsString('INDEX users_email_active_index (email, active)', $ddl);
        self::assertStringContainsString('active BOOLEAN NOT NULL DEFAULT TRUE', $ddl);
        self::assertStringContainsString('balance DECIMAL(10, 2) NOT NULL DEFAULT 0', $ddl);
        self::assertStringContainsString('role_id BIGINT UNSIGNED NOT NULL', $ddl);
        self::assertStringContainsString('INSERT INTO migrations (migration, batch, migrated_at)', $ddl);
    }

    public function testNothingToMigrateWhenRepositoryReportsAllFiles(): void
    {
        $connection = new FakeConnection(['all' => [['migration' => '2026_01_01_000000_a.php']]]);
        file_put_contents("{$this->path}/2026_01_01_000000_a.php", '<?php');

        $manager = new MigrationManager($connection, new MigrationRepository($connection), $this->path);

        self::assertSame('nothing_to_migrate', $manager->migrate()['status']);
    }

    public function testMigrationFileWithoutClassFailsWithClearError(): void
    {
        file_put_contents("{$this->path}/2026_01_01_000000_empty.php", "<?php\n// nothing here\n");

        $this->expectExceptionMessage('No class found in migration file');

        $this->manager()->migrate();
    }

    public function testAlterTableAddsColumnsIndexesAndForeignKeys(): void
    {
        file_put_contents("{$this->path}/2026_01_01_000001_alter_posts.php", <<<'PHP'
        <?php
        use Dr2g\ErpLibs\Migrations\Migration;
        class AlterPosts extends Migration
        {
            public function up(): void
            {
                $this->schema->table('posts', function ($table) {
                    $table->string('slug', 120)->unique();
                    $table->boolean('published')->default(false);
                    $table->foreignId('user_id')->references('id')->on('users')->cascadeOnDelete()->constrain();
                });
            }
            public function down(): void {}
        }
        PHP);

        $this->manager()->migrate();
        $ddl = $this->connection->ddl();

        self::assertStringContainsString('ALTER TABLE posts ADD COLUMN slug VARCHAR(120) NOT NULL', $ddl);
        self::assertStringContainsString('CREATE UNIQUE INDEX posts_slug_unique ON posts (slug)', $ddl);
        self::assertStringContainsString('published BOOLEAN NOT NULL DEFAULT FALSE', $ddl);
        self::assertStringContainsString('ADD CONSTRAINT fk_posts_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE', $ddl);
    }
}
