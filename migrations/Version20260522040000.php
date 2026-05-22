<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Legacy accounts (fixtures / pre-verification) have no verification_token; allow them to log in.
 */
final class Version20260522040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mark legacy users without a verification token as email-verified';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE user SET is_verified = 1 WHERE verification_token IS NULL AND is_verified = 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE user SET is_verified = 0 WHERE verification_token IS NULL');
    }
}
