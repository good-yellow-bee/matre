<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename all tables from resymf_* to matre_* prefix.
 * Part of project rename from ReSymf-CMS to MATRE.
 */
final class Version20251212150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename all tables from resymf_* to matre_* prefix (MATRE rebrand)';
    }

    public function up(Schema $schema): void
    {
        // Core tables
        $this->addSql('RENAME TABLE resymf_users TO matre_users');
        $this->addSql('RENAME TABLE resymf_settings TO matre_settings');
        $this->addSql('RENAME TABLE resymf_cron_jobs TO matre_cron_jobs');
        $this->addSql('RENAME TABLE resymf_password_reset_requests TO matre_password_reset_requests');

        // Test automation tables
        $this->addSql('RENAME TABLE resymf_test_environments TO matre_test_environments');
        $this->addSql('RENAME TABLE resymf_test_suites TO matre_test_suites');
        $this->addSql('RENAME TABLE resymf_test_runs TO matre_test_runs');
        $this->addSql('RENAME TABLE resymf_test_results TO matre_test_results');
        $this->addSql('RENAME TABLE resymf_test_reports TO matre_test_reports');
        $this->addSql('RENAME TABLE resymf_global_env_variables TO matre_global_env_variables');
    }

    public function down(Schema $schema): void
    {
        // Revert to original names
        $this->addSql('RENAME TABLE matre_users TO resymf_users');
        $this->addSql('RENAME TABLE matre_settings TO resymf_settings');
        $this->addSql('RENAME TABLE matre_cron_jobs TO resymf_cron_jobs');
        $this->addSql('RENAME TABLE matre_password_reset_requests TO resymf_password_reset_requests');

        $this->addSql('RENAME TABLE matre_test_environments TO resymf_test_environments');
        $this->addSql('RENAME TABLE matre_test_suites TO resymf_test_suites');
        $this->addSql('RENAME TABLE matre_test_runs TO resymf_test_runs');
        $this->addSql('RENAME TABLE matre_test_results TO resymf_test_results');
        $this->addSql('RENAME TABLE matre_test_reports TO resymf_test_reports');
        $this->addSql('RENAME TABLE matre_global_env_variables TO resymf_global_env_variables');
    }
}
