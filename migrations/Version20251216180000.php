<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add async process tracking fields to test_runs.
 */
final class Version20251216180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add process_pid and output_file_path for async test execution';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE matre_test_runs ADD process_pid INT DEFAULT NULL');
        $this->addSql('ALTER TABLE matre_test_runs ADD output_file_path VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE matre_test_runs DROP process_pid');
        $this->addSql('ALTER TABLE matre_test_runs DROP output_file_path');
    }
}
