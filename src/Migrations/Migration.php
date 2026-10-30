<?php

namespace Dr2g\ErpLibs\Migrations;

abstract class Migration
{
    protected SchemaBuilder $schema;
    protected string $migrationsPath;

    public function __construct(SchemaBuilder $schema, ?string $migrationsPath = null)
    {
        $this->schema = $schema;
        $this->migrationsPath = $migrationsPath ?? dirname(__DIR__, 2) . '/migrations';
    }

    abstract public function up(): void;
    abstract public function down(): void;

    /**
     * Conexión activa, para migraciones que necesiten SQL específico del motor.
     */
    protected function connection(): \Dr2g\ErpLibs\Core\Connection
    {
        return $this->schema->connection();
    }
}
