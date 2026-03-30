<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create daily_wellness_metrics table for Fitbit Premium wellness data (HRV, SpO2, BR, skin temp).
 */
final class Version20260329000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create daily_wellness_metrics table with UNIQUE(user_id, date) constraint';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE daily_wellness_metrics (
            id SERIAL NOT NULL,
            user_id INT NOT NULL,
            date DATE NOT NULL,
            rmssd DOUBLE PRECISION DEFAULT NULL,
            deep_rmssd DOUBLE PRECISION DEFAULT NULL,
            hrv_intraday_data JSON DEFAULT NULL,
            spo2_avg DOUBLE PRECISION DEFAULT NULL,
            spo2_min DOUBLE PRECISION DEFAULT NULL,
            spo2_max DOUBLE PRECISION DEFAULT NULL,
            breathing_rate DOUBLE PRECISION DEFAULT NULL,
            skin_temperature_relative DOUBLE PRECISION DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uq_wellness_user_date ON daily_wellness_metrics (user_id, date)');
        $this->addSql('CREATE INDEX idx_wellness_user_date ON daily_wellness_metrics (user_id, date DESC)');
        $this->addSql('ALTER TABLE daily_wellness_metrics ADD CONSTRAINT fk_wellness_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE daily_wellness_metrics DROP CONSTRAINT fk_wellness_user');
        $this->addSql('DROP TABLE daily_wellness_metrics');
    }
}
