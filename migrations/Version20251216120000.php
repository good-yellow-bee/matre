<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Performance indexes for test runs and results.
 */
final class Version20251216120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add performance indexes: composite (environment_id, status), suite_id, test_results.created_at';
    }

    public function up(Schema $schema): void
    {
        // Composite index for queries filtering by environment AND status
        $this->addSql('CREATE INDEX IDX_TEST_RUN_ENV_STATUS ON matre_test_runs (environment_id, status)');

        // Index for suite_id foreign key queries
        $this->addSql('CREATE INDEX IDX_TEST_RUN_SUITE ON matre_test_runs (suite_id)');

        // Index for ORDER BY created_at queries on test results
        $this->addSql('CREATE INDEX IDX_TEST_RESULT_CREATED ON matre_test_results (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_TEST_RUN_ENV_STATUS ON matre_test_runs');
        $this->addSql('DROP INDEX IDX_TEST_RUN_SUITE ON matre_test_runs');
        $this->addSql('DROP INDEX IDX_TEST_RESULT_CREATED ON matre_test_results');
    }
}
