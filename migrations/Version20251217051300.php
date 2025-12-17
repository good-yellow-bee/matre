<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251217051300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add environment field to GlobalEnvVariable for per-environment variable filtering';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_GLOBAL_ENV_VAR_NAME ON matre_global_env_variables');
        $this->addSql('ALTER TABLE matre_global_env_variables ADD environment VARCHAR(50) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_GLOBAL_ENV_VAR_NAME_ENV ON matre_global_env_variables (name, environment)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_GLOBAL_ENV_VAR_NAME_ENV ON matre_global_env_variables');
        $this->addSql('ALTER TABLE matre_global_env_variables DROP environment');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_GLOBAL_ENV_VAR_NAME ON matre_global_env_variables (name)');
    }
}
