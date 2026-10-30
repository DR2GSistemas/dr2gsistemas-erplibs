<?php

namespace Dr2g\ErpLibs\CLI\Commands;

use Dr2g\ErpLibs\CLI\Command;

class MakeMigrationCommand extends Command
{
    public function getName(): string
    {
        return 'make:migration';
    }

    public function getDescription(): string
    {
        return 'Create a new migration file';
    }

    /** @param list<string> $arguments */
    public function handle(array $arguments): void
    {
        if (empty($arguments)) {
            echo "Usage: make:migration <name>\n";
            return;
        }

        $name = $arguments[0];
        $timestamp = date('Y_m_d_His');

        if (str_starts_with(strtolower($name), 'create_')) {
            $table = str_replace('create_', '', strtolower($name));
            $table = str_replace('_table', '', $table);
            $className = 'Create' . str_replace(' ', '', ucwords(str_replace('_', ' ', $table))) . 'Table';
            $filename = "{$timestamp}_create_{$table}_table.php";

            $content = $this->generateCreateStub($className, $table);
        } else {
            $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
            $filename = "{$timestamp}_{$name}.php";
            $content = $this->generateBlankStub($className);
        }

        if (!is_dir('migrations')) {
            mkdir('migrations', 0755, true);
        }

        $path = "migrations/{$filename}";
        file_put_contents($path, $content);

        echo "[OK] Migration created: {$filename}\n";
    }

    private function generateCreateStub(string $className, string $table): string
    {
        $code = <<<'TEMPLATE'
<?php

use Dr2g\ErpLibs\Migrations\Migration;

class %CLASS% extends Migration
{
    public function up(): void
    {
        $this->schema->create('%TABLE%', function ($table) {
            $table->id();
            // Add columns here
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        $this->schema->drop('%TABLE%');
    }
}
TEMPLATE;

        return str_replace(
            ['%CLASS%', '%TABLE%'],
            [$className, $table],
            $code
        );
    }

    private function generateBlankStub(string $className): string
    {
        $code = <<<'TEMPLATE'
<?php

use Dr2g\ErpLibs\Migrations\Migration;

class %CLASS% extends Migration
{
    public function up(): void
    {
        // Write your migration here
    }
    
    public function down(): void
    {
        // Write your rollback here
    }
}
TEMPLATE;

        return str_replace('%CLASS%', $className, $code);
    }
}
