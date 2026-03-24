<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260326232818 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX uq_daily_steps_user_date ON daily_steps (user_id, date)');
        $this->addSql('ALTER INDEX uq_mesocycle_invite_code RENAME TO UNIQ_81A88D356F21F112');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uq_daily_steps_user_date');
        $this->addSql('ALTER INDEX uniq_81a88d356f21f112 RENAME TO uq_mesocycle_invite_code');
    }
}
