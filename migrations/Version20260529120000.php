<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Allow user registration without an email address (NULL in unique email column).
 */
final class Version20260529120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make user.email nullable for optional registration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE user SET email = NULL WHERE email = ''");
        $this->addSql('ALTER TABLE user CHANGE email email VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE user SET email = '' WHERE email IS NULL");
        $this->addSql('ALTER TABLE user CHANGE email email VARCHAR(255) NOT NULL');
    }
}
