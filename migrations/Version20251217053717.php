<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251217053717 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert environment (string) to environments (JSON array) for multi-env support';
    }

    public function up(Schema $schema): void
    {
        // Step 1: Add new JSON column
        $this->addSql('ALTER TABLE matre_global_env_variables ADD environments JSON DEFAULT NULL');

        // Step 2: Migrate data - convert single string to JSON array
        // NULL stays NULL (global), string becomes ["string"]
        $this->addSql('UPDATE matre_global_env_variables SET environments = JSON_ARRAY(environment) WHERE environment IS NOT NULL');

        // Step 3: Drop old column and constraint
        $this->addSql('DROP INDEX UNIQ_GLOBAL_ENV_VAR_NAME_ENV ON matre_global_env_variables');
        $this->addSql('ALTER TABLE matre_global_env_variables DROP environment');

        // Step 4: Add new index
        $this->addSql('CREATE INDEX IDX_GLOBAL_ENV_VAR_NAME ON matre_global_env_variables (name)');
    }

    public function down(Schema $schema): void
    {
        // Step 1: Add old column back
        $this->addSql('ALTER TABLE matre_global_env_variables ADD environment VARCHAR(50) DEFAULT NULL');

        // Step 2: Migrate data - take first element from JSON array
        $this->addSql('UPDATE matre_global_env_variables SET environment = JSON_UNQUOTE(JSON_EXTRACT(environments, "$[0]")) WHERE environments IS NOT NULL AND JSON_LENGTH(environments) > 0');

        // Step 3: Drop new column and index
        $this->addSql('DROP INDEX IDX_GLOBAL_ENV_VAR_NAME ON matre_global_env_variables');
        $this->addSql('ALTER TABLE matre_global_env_variables DROP environments');

        // Step 4: Restore old constraint
        $this->addSql('CREATE UNIQUE INDEX UNIQ_GLOBAL_ENV_VAR_NAME_ENV ON matre_global_env_variables (name, environment)');
    }
}
