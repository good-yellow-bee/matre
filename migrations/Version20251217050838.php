<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251217050838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expand usedInTests column to TEXT for ActionGroup resolution';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE matre_global_env_variables CHANGE used_in_tests used_in_tests LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE matre_global_env_variables CHANGE used_in_tests used_in_tests VARCHAR(500) DEFAULT NULL');
    }
}
