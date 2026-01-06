<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260106105959 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE matre_test_results ADD output_file_path VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE matre_test_runs ADD current_test_name VARCHAR(255) DEFAULT NULL, ADD total_tests INT DEFAULT NULL, ADD completed_tests INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE matre_test_results DROP output_file_path');
        $this->addSql('ALTER TABLE matre_test_runs DROP current_test_name, DROP total_tests, DROP completed_tests');
    }
}
