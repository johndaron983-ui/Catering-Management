<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Production DB is missing booking_inventory because Version20251211122113 was marked executed with an empty up().
 */
final class Version20260524120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create booking_inventory table if missing (skipped in earlier migration)';
    }

    public function up(Schema $schema): void
    {
        if ($this->tableExists('booking_inventory')) {
            return;
        }

        if (!$this->tableExists('booking') || !$this->tableExists('inventory')) {
            $this->abortIf(true, 'Required tables booking and inventory must exist before creating booking_inventory.');
        }

        $this->addSql('CREATE TABLE booking_inventory (id INT AUTO_INCREMENT NOT NULL, booking_id INT NOT NULL, inventory_id INT NOT NULL, quantity_used DOUBLE PRECISION NOT NULL, added_at DATETIME DEFAULT NULL, INDEX IDX_221F81353301C60 (booking_id), INDEX IDX_221F81359EEA759 (inventory_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE booking_inventory ADD CONSTRAINT FK_221F81353301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking_inventory ADD CONSTRAINT FK_221F81359EEA759 FOREIGN KEY (inventory_id) REFERENCES inventory (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        if (!$this->tableExists('booking_inventory')) {
            return;
        }

        if ($this->foreignKeyExists('booking_inventory', 'FK_221F81353301C60')) {
            $this->addSql('ALTER TABLE booking_inventory DROP FOREIGN KEY FK_221F81353301C60');
        }

        if ($this->foreignKeyExists('booking_inventory', 'FK_221F81359EEA759')) {
            $this->addSql('ALTER TABLE booking_inventory DROP FOREIGN KEY FK_221F81359EEA759');
        }

        $this->addSql('DROP TABLE booking_inventory');
    }

    private function tableExists(string $table): bool
    {
        return $this->connection->createSchemaManager()->tablesExist([$table]);
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
}
