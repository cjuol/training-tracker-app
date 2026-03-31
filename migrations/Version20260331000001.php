<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260331000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add rest_seconds column to session_exercise table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session_exercise ADD rest_seconds INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session_exercise DROP COLUMN rest_seconds');
    }
}
