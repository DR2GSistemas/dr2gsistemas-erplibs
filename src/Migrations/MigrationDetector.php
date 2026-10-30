<?php

namespace Dr2g\ErpLibs\Migrations;

use Dr2g\ErpLibs\Models\Attributes\Column;
use Dr2g\ErpLibs\Models\Attributes\Table;
use ReflectionClass;

/**
 * Generates a create-table migration from a model's attributes when the table
 * does not exist yet.
 */
class MigrationDetector
{
    public function __construct(
        private readonly MigrationRepository $repository,
        private readonly string $migrationsPath
    ) {
    }

    /**
     * @param list<class-string> $models
     * @return list<string> generated filenames
     */
    public function detectChanges(array $models): array
    {
        $migrations = [];

        foreach ($models as $modelClass) {
            $reflection = new ReflectionClass($modelClass);
            $tableName = $this->getTableName($reflection);

            if (!$this->tableExists($tableName)) {
                $migrations[] = $this->generateCreateMigration($reflection, $tableName);
            }
        }

        return $migrations;
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function getTableName(ReflectionClass $reflection): string
    {
        $attributes = $reflection->getAttributes(Table::class);

        if ($attributes !== []) {
            return $attributes[0]->newInstance()->name;
        }

        return strtolower($reflection->getShortName()) . 's';
    }

    private function tableExists(string $table): bool
    {
        return $this->repository->connection()->select('SHOW TABLES LIKE ?', [$table]) !== [];
    }

    /**
     * @param ReflectionClass<object> $model
     */
    private function generateCreateMigration(ReflectionClass $model, string $table): string
    {
        $className = 'Create' . str_replace(' ', '', ucwords(str_replace('_', ' ', $table))) . 'Table';
        $filename = date('Y_m_d_His') . "_create_{$table}_table.php";

        $content = $this->renderMigrationTemplate(
            $className,
            $table,
            $this->extractColumnsFromAttributes($model)
        );

        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }

        file_put_contents("{$this->migrationsPath}/{$filename}", $content);

        return $filename;
    }

    /**
     * @param ReflectionClass<object> $model
     * @return list<array{name: string, type: string, nullable: bool, default: mixed}>
     */
    private function extractColumnsFromAttributes(ReflectionClass $model): array
    {
        $columns = [];

        foreach ($model->getProperties() as $property) {
            foreach ($property->getAttributes(Column::class) as $attribute) {
                $column = $attribute->newInstance();

                $columns[] = [
                    'name' => $property->getName(),
                    'type' => $column->type,
                    'nullable' => $column->nullable,
                    'default' => $column->default,
                ];
            }
        }

        return $columns;
    }

    /**
     * @param list<array{name: string, type: string, nullable: bool, default: mixed}> $columns
     */
    private function renderMigrationTemplate(string $className, string $table, array $columns): string
    {
        $definitions = '';

        foreach ($columns as $column) {
            $method = $this->mapTypeToMethod($column['type']);
            $nullable = $column['nullable'] ? '->nullable()' : '';
            $default = $column['default'] !== null ? "->default({$column['default']})" : '';

            $definitions .= "            \$table->{$method}('{$column['name']}'){$nullable}{$default};\n";
        }

        $template = <<<'TEMPLATE'
<?php

use Dr2g\ErpLibs\Migrations\Migration;

class %CLASS% extends Migration
{
    public function up(): void
    {
        $this->schema->create('%TABLE%', function ($table) {
            $table->id();
%COLUMNS%            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->drop('%TABLE%');
    }
}
TEMPLATE;

        return str_replace(
            ['%CLASS%', '%TABLE%', '%COLUMNS%'],
            [$className, $table, $definitions],
            $template
        );
    }

    private function mapTypeToMethod(string $type): string
    {
        $type = strtoupper($type);

        return match (true) {
            str_contains($type, 'VARCHAR') => 'string',
            str_contains($type, 'TEXT') => 'text',
            str_contains($type, 'BIGINT') => 'bigInteger',
            str_contains($type, 'INT') => 'integer',
            str_contains($type, 'DECIMAL') => 'decimal',
            str_contains($type, 'BOOLEAN') => 'boolean',
            str_contains($type, 'JSON') => 'json',
            str_contains($type, 'DATETIME') => 'dateTime',
            str_contains($type, 'TIMESTAMP') => 'timestamp',
            default => 'string',
        };
    }
}
