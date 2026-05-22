<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Production DB was missing columns because earlier migrations were marked "skipped".
 */
final class Version20260522120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing created_by_id columns and activity_logs record columns';
    }

    public function up(Schema $schema): void
    {
        $this->addCreatedByColumn('booking');
        $this->addCreatedByColumn('services');
        $this->addCreatedByColumn('inventory');
        $this->addCreatedByColumn('product');
        $this->addCreatedByColumn('supplier');
        $this->addActivityLogRecordColumns();
    }

    public function down(Schema $schema): void
    {
        // Intentionally empty — do not drop columns that may hold data.
    }

    private function addCreatedByColumn(string $table): void
    {
        if (!$this->tableExists($table) || $this->columnExists($table, 'created_by_id')) {
            return;
        }

        $fkName = 'FK_'.strtoupper($table).'_CREATED_BY';
        $idxName = 'IDX_'.strtoupper($table).'_CREATED_BY';

        $this->addSql(sprintf('ALTER TABLE `%s` ADD created_by_id INT DEFAULT NULL', $table));
        $this->addSql(sprintf('CREATE INDEX %s ON `%s` (created_by_id)', $idxName, $table));
        $this->addSql(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT %s FOREIGN KEY (created_by_id) REFERENCES `user` (id)',
            $table,
            $fkName
        ));
    }

    private function addActivityLogRecordColumns(): void
    {
        if (!$this->tableExists('activity_logs')) {
            return;
        }

        if (!$this->columnExists('activity_logs', 'record_type')) {
            $this->addSql('ALTER TABLE activity_logs ADD record_type VARCHAR(100) DEFAULT NULL');
        }

        if (!$this->columnExists('activity_logs', 'record_id')) {
            $this->addSql('ALTER TABLE activity_logs ADD record_id INT DEFAULT NULL');
        }
    }

    private function tableExists(string $table): bool
    {
        return $this->connection->createSchemaManager()->tablesExist([$table]);
    }

    private function columnExists(string $table, string $column): bool
    {
        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return $count > 0;
    }
}
