<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add index for watchdog queries on test_runs (status + updated_at).
 */
final class Version20260121100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index for watchdog queries on status and updated_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_test_runs_status_updated ON matre_test_runs (status, updated_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_test_runs_status_updated ON matre_test_runs');
    }
}
