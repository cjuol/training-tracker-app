<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260326094052 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add invite_code column to mesocycle table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mesocycle ADD COLUMN invite_code VARCHAR(12) DEFAULT NULL');
        $this->addSql("UPDATE mesocycle SET invite_code = substring(md5(random()::text), 1, 10)");
        $this->addSql('ALTER TABLE mesocycle ALTER COLUMN invite_code SET NOT NULL');
        $this->addSql('ALTER TABLE mesocycle ADD CONSTRAINT uq_mesocycle_invite_code UNIQUE (invite_code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mesocycle DROP CONSTRAINT IF EXISTS uq_mesocycle_invite_code');
        $this->addSql('ALTER TABLE mesocycle DROP COLUMN invite_code');
    }
}
