<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sync production schema: earlier migrations were marked "skipped" but columns were never created.
 */
final class Version20260522140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sync missing columns for booking, services, inventory, product, and supplier on production';
    }

    public function up(Schema $schema): void
    {
        $this->addCreatedBy('booking', 'FK_E00CEDDEB03A8386', 'IDX_E00CEDDEB03A8386');
        $this->addCreatedBy('services', 'FK_7332E169B03A8386', 'IDX_7332E169B03A8386');
        $this->addCreatedBy('inventory', 'FK_B12D4A36B03A8386', 'IDX_B12D4A36B03A8386');
        $this->addCreatedBy('product', 'FK_D34A04ADB03A8386', 'IDX_D34A04ADB03A8386');
        $this->addCreatedBy('supplier', 'FK_9B2A6C7EB03A8386', 'IDX_9B2A6C7EB03A8386');

        $this->addColumn('inventory', 'supplier_id', 'INT DEFAULT NULL');
        $this->addIndex('inventory', 'IDX_B12D4A362ADD6D8C', 'supplier_id');
        $this->addForeignKey('inventory', 'FK_B12D4A362ADD6D8C', 'supplier_id', 'supplier', 'id');

        $this->addColumn('inventory', 'product_id', 'INT DEFAULT NULL');
        $this->addIndex('inventory', 'IDX_B12D4A364584665A', 'product_id');
        $this->addForeignKey('inventory', 'FK_B12D4A364584665A', 'product_id', 'product', 'id');

        $this->addColumn('product', 'image_path', 'VARCHAR(255) DEFAULT NULL');
        $this->addColumn('supplier', 'image_path', 'VARCHAR(255) DEFAULT NULL');
        $this->addColumn('supplier', 'product', 'VARCHAR(255) DEFAULT NULL');

        $this->addActivityLogRecordColumns();
    }

    public function down(Schema $schema): void
    {
    }

    private function tableExists(string $table): bool
    {
        return $this->connection->createSchemaManager()->tablesExist([$table]);
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }

        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return $count > 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        );

        return $count > 0;
    }

    private function foreignKeyExists(string $table, string $fk): bool
    {
        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $fk, 'FOREIGN KEY']
        );

        return $count > 0;
    }

    private function addColumn(string $table, string $column, string $definition): void
    {
        if ($this->columnExists($table, $column)) {
            return;
        }

        $this->addSql(sprintf('ALTER TABLE `%s` ADD `%s` %s', $table, $column, $definition));
    }

    private function addIndex(string $table, string $index, string $column): void
    {
        if (!$this->columnExists($table, $column) || $this->indexExists($table, $index)) {
            return;
        }

        $this->addSql(sprintf('CREATE INDEX %s ON `%s` (`%s`)', $index, $table, $column));
    }

    private function addForeignKey(
        string $table,
        string $fk,
        string $column,
        string $refTable,
        string $refColumn,
    ): void {
        if (!$this->columnExists($table, $column) || $this->foreignKeyExists($table, $fk)) {
            return;
        }

        $this->addSql(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT %s FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`)',
            $table,
            $fk,
            $column,
            $refTable,
            $refColumn
        ));
    }

    private function addCreatedBy(string $table, string $fk, string $idx): void
    {
        $this->addColumn($table, 'created_by_id', 'INT DEFAULT NULL');
        $this->addIndex($table, $idx, 'created_by_id');
        $this->addForeignKey($table, $fk, 'created_by_id', 'user', 'id');
    }

    private function addActivityLogRecordColumns(): void
    {
        if (!$this->tableExists('activity_logs')) {
            return;
        }

        $this->addColumn('activity_logs', 'record_type', 'VARCHAR(100) DEFAULT NULL');
        $this->addColumn('activity_logs', 'record_id', 'INT DEFAULT NULL');
    }
}
