<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add excluded_tests field to test suites';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE matre_test_suites ADD excluded_tests LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE matre_test_suites DROP excluded_tests');
    }
}
