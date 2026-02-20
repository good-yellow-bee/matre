<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add executed_by_id FK to test runs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE matre_test_runs ADD executed_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE matre_test_runs ADD CONSTRAINT FK_test_run_executed_by FOREIGN KEY (executed_by_id) REFERENCES matre_users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_TEST_RUN_EXECUTED_BY ON matre_test_runs (executed_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE matre_test_runs DROP FOREIGN KEY FK_test_run_executed_by');
        $this->addSql('DROP INDEX IDX_TEST_RUN_EXECUTED_BY ON matre_test_runs');
        $this->addSql('ALTER TABLE matre_test_runs DROP executed_by_id');
    }
}
