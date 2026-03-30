<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add birth_date and height_cm columns to the user table.
 */
final class Version20260329000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add birth_date and height_cm demographic columns to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD birth_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD height_cm DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP birth_date');
        $this->addSql('ALTER TABLE "user" DROP height_cm');
    }
}
