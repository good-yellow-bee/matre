<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251212052912 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create test automation tables (environments, suites, runs, results, reports)';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        // Core tables - use IF NOT EXISTS for tables that may already exist
        $this->addSql('CREATE TABLE IF NOT EXISTS resymf_cron_jobs (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, command VARCHAR(255) NOT NULL, cron_expression VARCHAR(100) NOT NULL, is_active TINYINT NOT NULL, last_run_at DATETIME DEFAULT NULL, last_status VARCHAR(20) DEFAULT NULL, last_output LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_CRONJOB_ACTIVE (is_active), UNIQUE INDEX UNIQ_CRONJOB_NAME (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE IF NOT EXISTS resymf_password_reset_requests (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(100) NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, is_used TINYINT NOT NULL, ip_address VARCHAR(255) DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_2079041C5F37A13B (token), INDEX IDX_2079041CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE IF NOT EXISTS resymf_settings (id INT AUTO_INCREMENT NOT NULL, site_name VARCHAR(255) NOT NULL, admin_panel_title VARCHAR(255) NOT NULL, seo_description LONGTEXT DEFAULT NULL, seo_keywords VARCHAR(255) DEFAULT NULL, default_locale VARCHAR(10) NOT NULL, headless_mode TINYINT NOT NULL, enforce2fa TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE IF NOT EXISTS resymf_users (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(25) NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, totp_secret VARCHAR(255) DEFAULT NULL, is_totp_enabled TINYINT NOT NULL, UNIQUE INDEX UNIQ_USERNAME (username), UNIQUE INDEX UNIQ_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE IF NOT EXISTS messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        // Test automation tables - always new
        $this->addSql('CREATE TABLE IF NOT EXISTS resymf_test_environments (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, code VARCHAR(20) NOT NULL, region VARCHAR(10) NOT NULL, base_url VARCHAR(500) NOT NULL, backend_name VARCHAR(100) NOT NULL, admin_username VARCHAR(100) DEFAULT NULL, admin_password VARCHAR(255) DEFAULT NULL, env_variables JSON NOT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_TEST_ENV_ACTIVE (is_active), INDEX IDX_TEST_ENV_CODE_REGION (code, region), UNIQUE INDEX UNIQ_TEST_ENV_NAME (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE IF NOT EXISTS resymf_test_reports (id INT AUTO_INCREMENT NOT NULL, report_type VARCHAR(20) NOT NULL, file_path VARCHAR(500) NOT NULL, public_url VARCHAR(500) DEFAULT NULL, generated_at DATETIME NOT NULL, expires_at DATETIME DEFAULT NULL, test_run_id INT NOT NULL, INDEX IDX_TEST_REPORT_RUN (test_run_id), INDEX IDX_TEST_REPORT_TYPE (report_type), INDEX IDX_TEST_REPORT_EXPIRES (expires_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE IF NOT EXISTS resymf_test_results (id INT AUTO_INCREMENT NOT NULL, test_name VARCHAR(255) NOT NULL, test_id VARCHAR(50) DEFAULT NULL, status VARCHAR(20) NOT NULL, duration DOUBLE PRECISION DEFAULT NULL, error_message LONGTEXT DEFAULT NULL, screenshot_path VARCHAR(500) DEFAULT NULL, allure_result_path VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, test_run_id INT NOT NULL, INDEX IDX_TEST_RESULT_RUN (test_run_id), INDEX IDX_TEST_RESULT_STATUS (status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE IF NOT EXISTS resymf_test_runs (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, test_filter VARCHAR(255) DEFAULT NULL, started_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, triggered_by VARCHAR(20) NOT NULL, error_message LONGTEXT DEFAULT NULL, output LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, environment_id INT NOT NULL, suite_id INT DEFAULT NULL, INDEX IDX_4B626F934FFCB518 (suite_id), INDEX IDX_TEST_RUN_STATUS (status), INDEX IDX_TEST_RUN_ENV (environment_id), INDEX IDX_TEST_RUN_CREATED (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE IF NOT EXISTS resymf_test_suites (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, type VARCHAR(30) NOT NULL, description LONGTEXT DEFAULT NULL, test_pattern VARCHAR(255) NOT NULL, cron_expression VARCHAR(100) DEFAULT NULL, estimated_duration INT DEFAULT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_TEST_SUITE_ACTIVE (is_active), INDEX IDX_TEST_SUITE_TYPE (type), UNIQUE INDEX UNIQ_TEST_SUITE_NAME (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        // Add FK constraints only if they don't exist
        $this->addForeignKeyIfNotExists($schemaManager, 'resymf_password_reset_requests', 'user_id', 'resymf_users', 'id', 'ON DELETE CASCADE');
        $this->addForeignKeyIfNotExists($schemaManager, 'resymf_test_reports', 'test_run_id', 'resymf_test_runs', 'id', 'ON DELETE CASCADE');
        $this->addForeignKeyIfNotExists($schemaManager, 'resymf_test_results', 'test_run_id', 'resymf_test_runs', 'id', 'ON DELETE CASCADE');
        $this->addForeignKeyIfNotExists($schemaManager, 'resymf_test_runs', 'environment_id', 'resymf_test_environments', 'id', 'ON DELETE CASCADE');
        $this->addForeignKeyIfNotExists($schemaManager, 'resymf_test_runs', 'suite_id', 'resymf_test_suites', 'id', 'ON DELETE SET NULL');
    }

    private function addForeignKeyIfNotExists(
        \Doctrine\DBAL\Schema\AbstractSchemaManager $schemaManager,
        string $table,
        string $column,
        string $refTable,
        string $refColumn,
        string $onDelete
    ): void {
        if (!$schemaManager->tablesExist([$table])) {
            return;
        }

        $fks = $schemaManager->listTableForeignKeys($table);
        foreach ($fks as $fk) {
            $localCols = array_map('strtolower', $fk->getLocalColumns());
            if (in_array(strtolower($column), $localCols, true)) {
                // FK already exists for this column
                return;
            }
        }

        $fkName = 'FK_' . strtoupper(substr(md5($table . $column), 0, 12));
        $this->addSql("ALTER TABLE {$table} ADD CONSTRAINT {$fkName} FOREIGN KEY ({$column}) REFERENCES {$refTable} ({$refColumn}) {$onDelete}");
    }

    public function down(Schema $schema): void
    {
        // Drop FKs with dynamic names - use information_schema
        $this->addSql('SET @fk_name = (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'resymf_password_reset_requests\' AND COLUMN_NAME = \'user_id\' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1)');
        $this->addSql('SET @sql = IF(@fk_name IS NOT NULL, CONCAT(\'ALTER TABLE resymf_password_reset_requests DROP FOREIGN KEY \', @fk_name), \'SELECT 1\')');
        $this->addSql('PREPARE stmt FROM @sql');
        $this->addSql('EXECUTE stmt');
        $this->addSql('DEALLOCATE PREPARE stmt');

        $this->addSql('DROP TABLE IF EXISTS resymf_test_reports');
        $this->addSql('DROP TABLE IF EXISTS resymf_test_results');
        $this->addSql('DROP TABLE IF EXISTS resymf_test_runs');
        $this->addSql('DROP TABLE IF EXISTS resymf_test_suites');
        $this->addSql('DROP TABLE IF EXISTS resymf_test_environments');
        $this->addSql('DROP TABLE IF EXISTS resymf_cron_jobs');
        $this->addSql('DROP TABLE IF EXISTS resymf_password_reset_requests');
        $this->addSql('DROP TABLE IF EXISTS resymf_settings');
        $this->addSql('DROP TABLE IF EXISTS resymf_users');
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
    }
}
