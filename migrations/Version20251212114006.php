<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251212114006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create global_env_variables table for shared environment variables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE resymf_global_env_variables (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, value LONGTEXT NOT NULL, used_in_tests VARCHAR(500) DEFAULT NULL, description VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_GLOBAL_ENV_VAR_NAME (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE resymf_global_env_variables');
    }
}
