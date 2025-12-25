<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Security and Performance Improvements Migration.
 *
 * This migration:
 * 1. Renames password_reset_requests.token to token_hash (security: hash stored tokens)
 * 2. Adds performance indexes for frequently queried columns
 */
final class Version20251225000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Security improvements: hash reset tokens, add performance indexes';
    }

    public function up(Schema $schema): void
    {
        // Rename token column to token_hash for password reset requests
        // Note: The existing plain tokens will need to be invalidated or re-hashed
        $this->addSql('ALTER TABLE matre_password_reset_requests CHANGE token token_hash VARCHAR(100) NOT NULL');

        // Add performance indexes for test_runs
        $this->addSql('CREATE INDEX IDX_TEST_RUN_STATUS ON matre_test_runs (status)');
        $this->addSql('CREATE INDEX IDX_TEST_RUN_ENV_STATUS ON matre_test_runs (environment_id, status)');
        $this->addSql('CREATE INDEX IDX_TEST_RUN_CREATED ON matre_test_runs (created_at)');

        // Add performance index for test_results
        $this->addSql('CREATE INDEX IDX_TEST_RESULT_RUN_STATUS ON matre_test_results (test_run_id, status)');

        // Invalidate all existing password reset tokens (they were stored in plain text)
        // Users will need to request new reset tokens
        $this->addSql('DELETE FROM matre_password_reset_requests WHERE 1=1');
    }

    public function down(Schema $schema): void
    {
        // Revert token_hash back to token
        $this->addSql('ALTER TABLE matre_password_reset_requests CHANGE token_hash token VARCHAR(100) NOT NULL');

        // Remove performance indexes
        $this->addSql('DROP INDEX IDX_TEST_RUN_STATUS ON matre_test_runs');
        $this->addSql('DROP INDEX IDX_TEST_RUN_ENV_STATUS ON matre_test_runs');
        $this->addSql('DROP INDEX IDX_TEST_RUN_CREATED ON matre_test_runs');
        $this->addSql('DROP INDEX IDX_TEST_RESULT_RUN_STATUS ON matre_test_results');
    }
}
